/* DP Google Drive — front-end: drill-down folder navigation with deep links. */
( function () {
	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function escapeHtml( value ) {
		return String( value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function parseTrail() {
		var params = new URLSearchParams( window.location.search );
		var raw = params.get( 'dp_gdrive_trail' );
		if ( ! raw ) {
			return null;
		}

		var out = [];
		raw.split( ',' ).forEach( function ( part ) {
			var bits = part.split( '~' );
			if ( bits.length === 2 && bits[ 0 ] ) {
				out.push( { id: decodeURIComponent( bits[ 0 ] ), name: decodeURIComponent( bits[ 1 ] ) } );
			}
		} );

		return out.length ? out : null;
	}

	function trailParam( stack ) {
		return stack.map( function ( level ) {
			return encodeURIComponent( level.id ) + '~' + encodeURIComponent( level.label );
		} ).join( ',' );
	}

	ready( function () {
		var config = window.dpGdriveData || {};
		var i18n = config.i18n || {};
		var sections = [];

		function fetchLevel( id, nonce, bookmark ) {
			var body = new URLSearchParams();
			body.append( 'action', 'dp_gdrive_expand' );
			body.append( 'nonce', nonce );
			body.append( 'id', id );
			if ( bookmark ) {
				body.append( 'bookmark', bookmark );
			}

			return fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( json && json.success && json.data && json.data.html ) {
						return json.data.html;
					}
					throw new Error( 'failed' );
				} );
		}

		function initSection( section ) {
			var view = section.querySelector( '.dp-gdrive-view' );
			var breadcrumb = section.querySelector( '.dp-gdrive-breadcrumb' );
			if ( ! view || ! breadcrumb ) {
				return;
			}

			var rootTitle = section.getAttribute( 'data-title' ) || '';
			var rootId = section.getAttribute( 'data-id' ) || '';
			var rootPost = section.getAttribute( 'data-post' ) || '';
			var rootHtml = view.innerHTML;
			var wrap = view.querySelector( '.dp-gdrive-table-wrap' );
			var rootNonce = wrap ? wrap.getAttribute( 'data-nonce' ) || '' : '';

			var loadingHtml = '<div class="dp-gdrive-table-wrap"><p class="dp-gdrive-loading">' + ( i18n.loading || 'Loading…' ) + '</p></div>';
			var errorHtml = '<p class="dp-gdrive-error">' + ( i18n.error || 'Error' ) + '</p>';
			var stack = [];

			function rootLevel() {
				return { id: rootId, label: rootTitle, html: rootHtml };
			}

			function applyTrail( trail ) {
				if ( ! trail || ! trail.length || trail[ 0 ].id !== rootId ) {
					stack = [ rootLevel() ];
					return;
				}

				stack = trail.map( function ( entry, index ) {
					return {
						id: entry.id,
						label: index === 0 ? rootTitle : entry.name,
						html: index === 0 ? rootHtml : null
					};
				} );
			}

			function load( index, done ) {
				var entry = stack[ index ];
				if ( ! entry || entry.html !== null ) {
					if ( done ) {
						done();
					}
					return;
				}

				fetchLevel( entry.id, rootNonce, rootPost )
					.then( function ( html ) {
						entry.html = html;
						if ( done ) {
							done();
						}
					} )
					.catch( function () {
						entry.html = errorHtml;
						if ( done ) {
							done();
						}
					} );
			}

			function renderBreadcrumb() {
				if ( stack.length < 2 ) {
					breadcrumb.innerHTML = '';
					return;
				}

				var parts = stack.map( function ( level, index ) {
					var label = escapeHtml( level.label );

					if ( index === stack.length - 1 ) {
						return '<span class="dp-gdrive-breadcrumb__current">' + label + '</span>';
					}

					return '<button type="button" class="dp-gdrive-breadcrumb__link" data-index="' + index + '">' + label + '</button>';
				} );

				breadcrumb.innerHTML = parts.join( '<span class="dp-gdrive-breadcrumb__sep" aria-hidden="true">/</span>' );
			}

			function render() {
				var index = stack.length - 1;
				var top = stack[ index ];

				if ( top.html === null ) {
					view.innerHTML = loadingHtml;
					load( index, render );
				} else {
					view.innerHTML = top.html;
				}

				renderBreadcrumb();
			}

			function syncUrl() {
				var params = new URLSearchParams( window.location.search );
				params.set( 'dp_gdrive_trail', trailParam( stack ) );
				var url = window.location.pathname + '?' + params.toString() + window.location.hash;
				window.history.pushState( { dpGdrive: trailParam( stack ) }, '', url );
			}

			view.addEventListener( 'click', function ( event ) {
				var dir = event.target.closest ? event.target.closest( '.dp-gdrive-dir' ) : null;
				if ( ! dir ) {
					return;
				}

				event.preventDefault();

				stack.push( {
					id: dir.getAttribute( 'data-id' ),
					label: dir.getAttribute( 'data-name' ) || '',
					html: null
				} );

				syncUrl();
				render();
			} );

			breadcrumb.addEventListener( 'click', function ( event ) {
				var link = event.target.closest ? event.target.closest( '.dp-gdrive-breadcrumb__link' ) : null;
				if ( ! link ) {
					return;
				}

				var index = parseInt( link.getAttribute( 'data-index' ), 10 );
				if ( isNaN( index ) || index < 0 || index >= stack.length ) {
					return;
				}

				stack = stack.slice( 0, index + 1 );
				syncUrl();
				render();
			} );

			applyTrail( parseTrail() );
			render();

			sections.push( { applyTrail: applyTrail, render: render } );
		}

		Array.prototype.forEach.call( document.querySelectorAll( '.dp-gdrive-bookmark[data-dp-gdrive]' ), initSection );

		window.addEventListener( 'popstate', function () {
			var trail = parseTrail();
			sections.forEach( function ( entry ) {
				entry.applyTrail( trail );
				entry.render();
			} );
		} );

		document.addEventListener(
			'error',
			function ( event ) {
				var img = event.target;
				if ( img && img.classList && img.classList.contains( 'dp-gdrive-icon' ) ) {
					img.style.visibility = 'hidden';
				}
			},
			true
		);
	} );
} )();

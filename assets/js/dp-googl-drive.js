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

		function initCard( card ) {
			var btn = card.querySelector( '.dp-gdrive-catalog-card__open' );
			var panel = card.querySelector( '.dp-gdrive-catalog-card__panel' );
			if ( ! btn || ! panel ) {
				return;
			}

			btn.addEventListener( 'click', function () {
				if ( ! panel.hidden ) {
					panel.hidden = true;
					btn.setAttribute( 'aria-expanded', 'false' );
					return;
				}

				panel.hidden = false;
				btn.setAttribute( 'aria-expanded', 'true' );

				if ( panel.getAttribute( 'data-loaded' ) === '1' ) {
					return;
				}

				panel.setAttribute( 'data-loaded', '1' );
				panel.innerHTML = '<p class="dp-gdrive-loading">' + ( i18n.loading || 'Loading…' ) + '</p>';

				var body = new URLSearchParams();
				body.append( 'action', 'dp_gdrive_bookmark' );
				body.append( 'nonce', card.getAttribute( 'data-nonce' ) || '' );
				body.append( 'id', card.getAttribute( 'data-post' ) || '' );

				fetch( config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( json ) {
						if ( ! json || ! json.success || ! json.data || ! json.data.html ) {
							throw new Error( 'failed' );
						}

						panel.innerHTML = json.data.html;
						var section = panel.querySelector( '.dp-gdrive-bookmark' );
						if ( section ) {
							initSection( section );
						}
					} )
					.catch( function () {
						panel.setAttribute( 'data-loaded', '0' );
						panel.innerHTML = '<p class="dp-gdrive-error">' + ( i18n.error || 'Error' ) + '</p>';
					} );
			} );
		}

		function initPeek( dialog ) {
			var btn = dialog.querySelector( '[data-dp-gdrive-peek]' );
			var url = dialog.querySelector( 'input[name="dp_gdrive_url"]' );
			var title = dialog.querySelector( 'input[name="dp_gdrive_title"]' );
			var status = dialog.querySelector( '[data-dp-gdrive-peek-status]' );
			if ( ! btn || ! url ) {
				return;
			}

			var label = btn.textContent;
			var busy = false;

			function setStatus( text, isError, suggestion ) {
				if ( ! status ) {
					return;
				}

				status.textContent = '';
				status.classList.toggle( 'dp-gdrive-peek-status--error', !! isError );

				if ( text ) {
					status.appendChild( document.createTextNode( text ) );
				}

				if ( suggestion ) {
					var use = document.createElement( 'button' );
					use.type = 'button';
					use.className = 'dp-gdrive-peek-status__use';
					use.textContent = i18n.peekUse || 'Use this name';
					use.addEventListener( 'click', function () {
						if ( title ) {
							title.value = suggestion;
						}
						setStatus( i18n.peekFilled || '', false, '' );
					} );

					status.appendChild( document.createTextNode( ' ' ) );
					status.appendChild( use );
				}
			}

			btn.addEventListener( 'click', function () {
				if ( busy ) {
					return;
				}

				var value = ( url.value || '' ).trim();
				if ( ! value ) {
					setStatus( i18n.peekEmpty || '', true, '' );
					url.focus();
					return;
				}

				busy = true;
				btn.disabled = true;
				btn.textContent = i18n.peekLoading || label;
				setStatus( '', false, '' );

				var body = new URLSearchParams();
				body.append( 'action', 'dp_gdrive_peek' );
				body.append( 'nonce', config.peekNonce || '' );
				body.append( 'url', value );

				fetch( config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( json ) {
						if ( ! json || ! json.success || ! json.data ) {
							throw new Error( ( json && json.data && json.data.message ) || i18n.peekError || 'Error' );
						}

						var data = json.data;
						var suggestion = '';
						var current = title ? title.value.trim() : '';

						if ( title && data.title ) {
							if ( '' === current ) {
								title.value = data.title;
							} else if ( current !== data.title ) {
								suggestion = data.title;
							}
						}

						setStatus( data.message || '', false, suggestion );
					} )
					.catch( function ( error ) {
						setStatus( ( error && error.message ) || i18n.peekError || 'Error', true, '' );
					} )
					.then( function () {
						busy = false;
						btn.disabled = false;
						btn.textContent = label;
					} );
			} );
		}

		function initSubmitDialog( wrapper ) {
			var trigger = wrapper.querySelector( '[data-dp-gdrive-open]' );
			var dialog = wrapper.querySelector( '[data-dp-gdrive-dialog]' );
			if ( ! trigger || ! dialog ) {
				return;
			}

			var supportsModal = typeof dialog.showModal === 'function';

			if ( supportsModal ) {
				dialog.classList.add( 'dp-gdrive-dialog--enhanced' );
				dialog.removeAttribute( 'open' );
			} else {
				dialog.classList.add( 'dp-gdrive-dialog--enhanced', 'dp-gdrive-dialog--inline' );
			}

			function open() {
				if ( supportsModal ) {
					if ( dialog.open ) {
						return;
					}
					dialog.showModal();
				} else {
					dialog.setAttribute( 'open', '' );
				}

				document.documentElement.classList.add( 'dp-gdrive-dialog-open' );
				trigger.setAttribute( 'aria-expanded', 'true' );

				var first = dialog.querySelector( 'input[name="dp_gdrive_url"]' ) || dialog.querySelector( 'input, textarea, select' );
				if ( first ) {
					window.setTimeout( function () {
						first.focus();
					}, 30 );
				}
			}

			function close() {
				if ( supportsModal && dialog.open ) {
					dialog.close();
				} else {
					dialog.removeAttribute( 'open' );
				}

				document.documentElement.classList.remove( 'dp-gdrive-dialog-open' );
				trigger.setAttribute( 'aria-expanded', 'false' );
				trigger.focus();
			}

			trigger.addEventListener( 'click', function ( event ) {
				if ( supportsModal ) {
					event.preventDefault();
				}
				open();
			} );

			var closer = dialog.querySelector( '[data-dp-gdrive-close]' );
			if ( closer ) {
				closer.addEventListener( 'click', close );
			}

			dialog.addEventListener( 'click', function ( event ) {
				if ( event.target === dialog ) {
					close();
				}
			} );

			dialog.addEventListener( 'close', function () {
				document.documentElement.classList.remove( 'dp-gdrive-dialog-open' );
				trigger.setAttribute( 'aria-expanded', 'false' );
			} );

			if ( dialog.getAttribute( 'data-open-on-load' ) === '1' ) {
				window.setTimeout( open, 50 );
			}

			initPeek( dialog );
		}

		function initList( list ) {
			var loadmore = list.nextElementSibling;
			if ( ! loadmore || ! loadmore.hasAttribute( 'data-dp-gdrive-loadmore' ) ) {
				return;
			}

			var btn = loadmore.querySelector( '.dp-gdrive-loadmore__btn' );
			var status = loadmore.querySelector( '.dp-gdrive-loadmore__status' );
			var loadingText = status ? status.textContent : '';
			var nonce = list.getAttribute( 'data-nonce' ) || '';
			var perPage = parseInt( list.getAttribute( 'data-per-page' ), 10 ) || 5;
			var offset = parseInt( list.getAttribute( 'data-offset' ), 10 ) || 0;
			var total = parseInt( list.getAttribute( 'data-total' ), 10 ) || 0;
			var showTitle = list.getAttribute( 'data-show-title' ) === '1' ? '1' : '0';
			var view = list.getAttribute( 'data-view' ) || 'catalog';
			var q = list.getAttribute( 'data-q' ) || '';
			var items = list.querySelector( '[data-dp-gdrive-items]' ) || list;
			var busy = false;

			function showButton() {
				if ( btn ) {
					btn.hidden = false;
				}
				if ( status ) {
					status.textContent = loadingText;
					status.hidden = true;
				}
			}

			function load() {
				if ( busy || offset >= total ) {
					return;
				}

				busy = true;
				if ( btn ) {
					btn.hidden = true;
				}
				if ( status ) {
					status.textContent = loadingText;
					status.hidden = false;
				}

				var body = new URLSearchParams();
				body.append( 'action', 'dp_gdrive_list' );
				body.append( 'nonce', nonce );
				body.append( 'offset', offset );
				body.append( 'per_page', perPage );
				body.append( 'show_title', showTitle );
				body.append( 'view', view );
				body.append( 'q', q );

				fetch( config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( json ) {
						if ( ! json || ! json.success || ! json.data ) {
							throw new Error( 'failed' );
						}

						var holder = document.createElement( 'div' );
						holder.innerHTML = json.data.html;
						var added = [];

						while ( holder.firstChild ) {
							var node = holder.firstChild;
							holder.removeChild( node );
							items.appendChild( node );
							if ( node.nodeType === 1 && node.classList && ( node.classList.contains( 'dp-gdrive-bookmark' ) || node.classList.contains( 'dp-gdrive-catalog-card' ) ) ) {
								added.push( node );
							}
						}

						added.forEach( function ( node ) {
							if ( node.classList.contains( 'dp-gdrive-catalog-card' ) ) {
								initCard( node );
							} else {
								initSection( node );
							}
						} );

						offset = parseInt( json.data.offset, 10 ) || offset;
						total = parseInt( json.data.total, 10 ) || total;
						busy = false;

						if ( ! json.data.hasMore || offset >= total ) {
							if ( loadmore.parentNode ) {
								loadmore.parentNode.removeChild( loadmore );
							}
						} else {
							showButton();
						}
					} )
					.catch( function () {
						busy = false;
						if ( btn ) {
							btn.hidden = false;
						}
						if ( status ) {
							status.textContent = i18n.listError || 'Error';
							status.hidden = false;
						}
					} );
			}

			if ( btn ) {
				btn.addEventListener( 'click', load );
			}

			if ( 'IntersectionObserver' in window ) {
				var observer = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting ) {
							load();
						}
					} );
				}, { rootMargin: '300px 0px' } );

				observer.observe( loadmore );
			}
		}

		Array.prototype.forEach.call( document.querySelectorAll( '.dp-gdrive-bookmark[data-dp-gdrive]' ), initSection );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-dp-gdrive-card]' ), initCard );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-dp-gdrive-submit]' ), initSubmitDialog );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-dp-gdrive-list]' ), initList );

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

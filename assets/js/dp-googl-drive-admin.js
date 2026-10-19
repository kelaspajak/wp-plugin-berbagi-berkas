/* DP Google Drive — editor helper.
   Shows the link field matching the chosen type and reveals the role picker
   only when the audience is "roles". */
( function () {
	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var box = document.querySelector( '.dp-gdrive-metabox' );
		if ( ! box ) {
			return;
		}

		var typeRadios = box.querySelectorAll( 'input[name="dp_gdrive_type"]' );
		var folderField = box.querySelector( '.dp-gdrive-metabox__field--folder' );
		var fileField = box.querySelector( '.dp-gdrive-metabox__field--file' );
		var filesField = box.querySelector( '.dp-gdrive-metabox__files' );
		var audienceSelect = box.querySelector( 'select[name="dp_gdrive_audience"]' );
		var rolesField = box.querySelector( '.dp-gdrive-metabox__roles' );

		function syncType() {
			var value = 'folder';
			typeRadios.forEach( function ( radio ) {
				if ( radio.checked ) {
					value = radio.value;
				}
			} );
			if ( folderField ) {
				folderField.style.display = ( 'folder' === value ) ? '' : 'none';
			}
			if ( fileField ) {
				fileField.style.display = ( 'file' === value ) ? '' : 'none';
			}
			if ( filesField ) {
				filesField.style.display = ( 'folder' === value ) ? '' : 'none';
			}
		}

		function syncAudience() {
			if ( ! audienceSelect || ! rolesField ) {
				return;
			}
			rolesField.style.display = ( 'roles' === audienceSelect.value ) ? '' : 'none';
		}

		function syncFile( checkbox ) {
			var wrap = checkbox.closest ? checkbox.closest( '.dp-gdrive-file' ) : null;
			var roles = wrap ? wrap.querySelector( '.dp-gdrive-file__roles' ) : null;
			if ( roles ) {
				roles.hidden = checkbox.checked;
			}
		}

		typeRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', syncType );
		} );

		if ( audienceSelect ) {
			audienceSelect.addEventListener( 'change', syncAudience );
		}

		box.querySelectorAll( '.dp-gdrive-file__public input[type="checkbox"]' ).forEach( function ( checkbox ) {
			checkbox.addEventListener( 'change', function () {
				syncFile( checkbox );
			} );
			syncFile( checkbox );
		} );

		syncType();
		syncAudience();
	} );
} )();

/**
 * Algolia Sync admin UI — index repeater + recursive AJAX full sync.
 */
( function () {
	'use strict';

	var config = window.tribeAlgoliaSync || {};
	var env = config.environment || 'production';
	var syncing = false;
	var beforeUnloadHandler = null;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
			return;
		}
		document.addEventListener( 'DOMContentLoaded', fn );
	}

	function previewLabel( baseName ) {
		var template = ( config.i18n && config.i18n.indexPreview ) || 'Resolves to: %s';
		if ( ! baseName ) {
			return '';
		}
		return template.replace( '%s', baseName + '_' + env );
	}

	function updatePreview( row ) {
		var input = row.querySelector( '.tribe-algolia-sync-index-input' );
		var preview = row.querySelector( '.tribe-algolia-sync-index-preview' );
		if ( ! input || ! preview ) {
			return;
		}
		preview.textContent = previewLabel( input.value.trim() );
	}

	function bindRow( row ) {
		var input = row.querySelector( '.tribe-algolia-sync-index-input' );
		var remove = row.querySelector( '.tribe-algolia-sync-remove-index' );

		if ( input ) {
			input.addEventListener( 'input', function () {
				updatePreview( row );
			} );
			updatePreview( row );
		}

		if ( remove ) {
			remove.addEventListener( 'click', function () {
				var container = document.getElementById( 'tribe-algolia-sync-indexes' );
				if ( ! container ) {
					return;
				}
				if ( container.querySelectorAll( '.tribe-algolia-sync-index-row' ).length <= 1 ) {
					if ( input ) {
						input.value = '';
						updatePreview( row );
					}
					return;
				}
				row.remove();
			} );
		}
	}

	function initRepeater() {
		var container = document.getElementById( 'tribe-algolia-sync-indexes' );
		var addButton = document.getElementById( 'tribe-algolia-sync-add-index' );
		var template = document.getElementById( 'tribe-algolia-sync-index-row-template' );

		if ( ! container || ! addButton || ! template ) {
			return;
		}

		container.querySelectorAll( '.tribe-algolia-sync-index-row' ).forEach( bindRow );

		addButton.addEventListener( 'click', function () {
			var node = template.content.cloneNode( true );
			var row = node.querySelector( '.tribe-algolia-sync-index-row' );
			if ( ! row ) {
				return;
			}
			container.appendChild( row );
			bindRow( row );
			var input = row.querySelector( '.tribe-algolia-sync-index-input' );
			if ( input ) {
				input.focus();
			}
		} );
	}

	function stateLabel( state ) {
		var map = {
			idle: ( config.i18n && config.i18n.stateIdle ) || 'Idle',
			running: ( config.i18n && config.i18n.stateRunning ) || 'Running',
			completed: ( config.i18n && config.i18n.stateCompleted ) || 'Completed',
			failed: ( config.i18n && config.i18n.stateFailed ) || 'Failed'
		};
		return map[ state ] || state;
	}

	function formatTime( timestamp ) {
		if ( ! timestamp ) {
			return '';
		}
		var date = new Date( timestamp * 1000 );
		if ( Number.isNaN( date.getTime() ) ) {
			return '';
		}
		return date.toISOString().replace( 'T', ' ' ).slice( 0, 19 );
	}

	function setHidden( el, hidden ) {
		if ( ! el ) {
			return;
		}
		if ( hidden ) {
			el.setAttribute( 'hidden', 'hidden' );
		} else {
			el.removeAttribute( 'hidden' );
		}
	}

	function updateStatus( payload ) {
		var root = document.getElementById( 'tribe-algolia-sync-status' );
		if ( ! root || ! payload ) {
			return;
		}

		var state = payload.state || '';
		root.setAttribute( 'data-state', state );
		if ( state === 'failed' ) {
			root.classList.add( 'is-failed' );
		} else {
			root.classList.remove( 'is-failed' );
		}

		var stateEl = root.querySelector( '.tribe-algolia-sync-status-state' );
		if ( stateEl ) {
			stateEl.textContent = stateLabel( state || 'idle' );
		}

		var stageWrap = root.querySelector( '.tribe-algolia-sync-status-stage-wrap' );
		var stageEl = root.querySelector( '.tribe-algolia-sync-status-stage' );
		if ( stageEl ) {
			stageEl.textContent = payload.stage === 'full_sync'
				? ( ( config.i18n && config.i18n.stageFullSync ) || 'Full sync' )
				: ( payload.stage || '' );
		}
		setHidden( stageWrap, !payload.stage );

		var messageEl = root.querySelector( '.tribe-algolia-sync-status-message' );
		if ( messageEl && payload.message ) {
			messageEl.textContent = payload.message;
			if ( state === 'failed' ) {
				messageEl.classList.add( 'tribe-algolia-sync-status-error' );
			} else {
				messageEl.classList.remove( 'tribe-algolia-sync-status-error' );
			}
		}

		var progressEl = root.querySelector( '.tribe-algolia-sync-status-progress' );
		var processedEl = root.querySelector( '.tribe-algolia-sync-status-processed' );
		var totalWrap = root.querySelector( '.tribe-algolia-sync-status-total-wrap' );
		var totalEl = root.querySelector( '.tribe-algolia-sync-status-total' );
		var processed = payload.processed || 0;
		var total = payload.total || 0;

		if ( processedEl ) {
			processedEl.textContent = String( processed );
		}
		if ( totalEl ) {
			totalEl.textContent = String( total );
		}
		setHidden( progressEl, !( processed > 0 || total > 0 ) );
		setHidden( totalWrap, !( total > 0 ) );

		var startedWrap = root.querySelector( '.tribe-algolia-sync-status-started-wrap' );
		var startedEl = root.querySelector( '.tribe-algolia-sync-status-started' );
		if ( startedEl && payload.started ) {
			startedEl.textContent = formatTime( payload.started );
		}
		setHidden( startedWrap, !( payload.started > 0 ) );

		var updatedWrap = root.querySelector( '.tribe-algolia-sync-status-updated-wrap' );
		var updatedEl = root.querySelector( '.tribe-algolia-sync-status-updated' );
		if ( updatedEl && payload.updated ) {
			updatedEl.textContent = formatTime( payload.updated );
		}
		setHidden( updatedWrap, !( payload.updated > 0 ) );

		var finishedWrap = root.querySelector( '.tribe-algolia-sync-status-finished-wrap' );
		var finishedEl = root.querySelector( '.tribe-algolia-sync-status-finished' );
		if ( finishedEl && payload.finished ) {
			finishedEl.textContent = formatTime( payload.finished );
		}
		setHidden( finishedWrap, !( payload.finished > 0 ) );

		updateLog( payload.log || [] );
	}

	function updateLog( entries ) {
		var wrap = document.getElementById( 'tribe-algolia-sync-log-wrap' );
		var list = document.getElementById( 'tribe-algolia-sync-log' );
		if ( ! wrap || ! list ) {
			return;
		}

		list.innerHTML = '';

		if ( ! entries.length ) {
			setHidden( wrap, true );
			return;
		}

		entries.forEach( function ( entry ) {
			var li = document.createElement( 'li' );
			li.className = 'tribe-algolia-sync-log-' + ( entry.level || 'info' );

			var time = document.createElement( 'span' );
			time.className = 'tribe-algolia-sync-log-time';
			time.textContent = formatTime( entry.time || 0 );

			li.appendChild( time );
			li.appendChild( document.createTextNode( entry.message || '' ) );
			list.appendChild( li );
		} );

		setHidden( wrap, false );
	}

	function setSyncUi( isRunning ) {
		var button = document.getElementById( 'tribe-algolia-sync-run' );
		var warning = document.querySelector( '.tribe-algolia-sync-warning' );

		if ( button ) {
			button.disabled = isRunning || ! config.canSync;
		}

		if ( warning ) {
			if ( isRunning ) {
				warning.classList.add( 'is-running' );
			} else {
				warning.classList.remove( 'is-running' );
			}
		}

		if ( isRunning ) {
			enableBeforeUnload();
		} else {
			disableBeforeUnload();
		}
	}

	function enableBeforeUnload() {
		if ( beforeUnloadHandler ) {
			return;
		}
		beforeUnloadHandler = function ( event ) {
			event.preventDefault();
			event.returnValue = ( config.i18n && config.i18n.stayOnPage ) || '';
			return event.returnValue;
		};
		window.addEventListener( 'beforeunload', beforeUnloadHandler );
	}

	function disableBeforeUnload() {
		if ( ! beforeUnloadHandler ) {
			return;
		}
		window.removeEventListener( 'beforeunload', beforeUnloadHandler );
		beforeUnloadHandler = null;
	}

	function request( action ) {
		var body = new window.FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce || '' );

		return window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json().then( function ( json ) {
				return {
					ok: response.ok,
					json: json
				};
			} );
		} );
	}

	function runBatches() {
		return request( config.actions.batch ).then( function ( result ) {
			var payload = ( result.json && result.json.data ) || ( result.json && result.json.data === undefined && result.json ) || {};

			if ( ! result.json || ! result.json.success ) {
				payload = ( result.json && result.json.data ) || {};
				updateStatus( payload );
				setSyncUi( false );
				syncing = false;
				window.alert( ( payload.message ) || ( config.i18n && config.i18n.failed ) || 'Sync failed.' );
				return;
			}

			updateStatus( payload );

			if ( payload.done ) {
				setSyncUi( false );
				syncing = false;
				return;
			}

			return runBatches();
		} );
	}

	function initSyncButton() {
		var button = document.getElementById( 'tribe-algolia-sync-run' );
		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( syncing ) {
				return;
			}

			if ( ! config.canSync ) {
				window.alert( ( config.i18n && config.i18n.notReady ) || '' );
				return;
			}

			syncing = true;
			setSyncUi( true );
			updateStatus( {
				state: 'running',
				message: ( config.i18n && config.i18n.syncing ) || 'Sync in progress…',
				processed: 0,
				total: 0,
				updated: Math.floor( Date.now() / 1000 )
			} );

			request( config.actions.start ).then( function ( result ) {
				var payload = ( result.json && result.json.data ) || {};

				if ( ! result.json || ! result.json.success ) {
					updateStatus( payload );
					setSyncUi( false );
					syncing = false;
					window.alert( ( payload.message ) || ( config.i18n && config.i18n.failed ) || 'Sync failed.' );
					return;
				}

				updateStatus( payload );
				return runBatches();
			} ).catch( function ( error ) {
				setSyncUi( false );
				syncing = false;
				window.alert( error && error.message ? error.message : ( ( config.i18n && config.i18n.failed ) || 'Sync failed.' ) );
			} );
		} );
	}

	function initBeforeUnload() {
		if ( config.isRunning ) {
			enableBeforeUnload();
		}
	}

	ready( function () {
		initRepeater();
		initSyncButton();
		initBeforeUnload();
	} );
}() );

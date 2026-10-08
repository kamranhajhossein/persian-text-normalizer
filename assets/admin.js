/* Persian Text Normalizer – admin screen. No dependencies. */
( function () {
	'use strict';

	var cfg = window.PTN;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;
	var $ = function ( id ) {
		return document.getElementById( id );
	};

	function post( data ) {
		var body = new FormData();
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( r ) {
				if ( ! r || ! r.success ) {
					throw new Error( 'ptn' );
				}
				return r.data;
			} );
	}

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return str.replace( /%(\d)\$d/g, function ( m, i ) {
			return String( args[ i - 1 ] );
		} );
	}

	// Show invisible characters so the effect is visible.
	function reveal( text ) {
		var span = document.createElement( 'span' );
		span.textContent = text;
		return span.innerHTML.replace( /‌/g, '<mark title="ZWNJ">·</mark>' );
	}

	/* ---------- Live tester ---------- */
	var tryBox = $( 'ptn-try' );
	var timer;
	function preview() {
		post( { action: 'ptn_preview', text: tryBox.value } )
			.then( function ( d ) {
				$( 'ptn-try-out' ).innerHTML = reveal( d.stored );
				$( 'ptn-try-search' ).innerHTML = reveal( d.search );
				$( 'ptn-try-digits' ).textContent = d.digits;
			} )
			.catch( function () {} );
	}
	if ( tryBox ) {
		tryBox.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( preview, 250 );
		} );
		preview();
	}

	/* ---------- Bulk fixer ---------- */
	var scanBtn = $( 'ptn-scan' );
	var fixBtn = $( 'ptn-fix' );
	var bar = $( 'ptn-progress' );
	var status = $( 'ptn-status' );
	var samples = $( 'ptn-samples' );

	function busy( on ) {
		scanBtn.disabled = on;
		fixBtn.disabled = on;
		bar.hidden = ! on && bar.value >= 100;
	}

	function addSamples( list ) {
		list.forEach( function ( s ) {
			if ( samples.children.length >= 10 ) {
				return;
			}
			var li = document.createElement( 'li' );
			var fields = s.fields
				.map( function ( f ) {
					return t.fieldsMap[ f ] || f;
				} )
				.join( '، ' );
			li.textContent = '#' + s.id + ' ' + ( s.title || '' ) + ' — ' + fields + ' ';
			if ( s.edit ) {
				var a = document.createElement( 'a' );
				a.href = s.edit;
				a.textContent = t.edit;
				li.appendChild( a );
			}
			samples.appendChild( li );
		} );
	}

	function run( fix ) {
		if ( fix && ! window.confirm( t.confirm ) ) {
			return;
		}
		var flag = fix ? 1 : '';
		var changed = 0;
		var total = 0;
		var batch = 50;

		samples.innerHTML = '';
		bar.value = 0;
		bar.hidden = false;
		status.textContent = fix ? t.fixing : t.scanning;
		busy( true );

		function next( offset ) {
			if ( offset >= total ) {
				return post( { action: 'ptn_bulk', step: 'terms', fix: flag } );
			}
			return post( { action: 'ptn_bulk', step: 'posts', offset: offset, fix: flag } ).then( function ( d ) {
				changed += d.changed;
				addSamples( d.samples );
				bar.value = Math.min( 100, Math.round( ( ( offset + batch ) / total ) * 100 ) );
				return d.processed < batch ? next( total ) : next( offset + batch );
			} );
		}

		post( { action: 'ptn_bulk', step: 'start', fix: flag } )
			.then( function ( d ) {
				total = d.total;
				batch = d.batch;
				return next( 0 );
			} )
			.then( function ( terms ) {
				bar.value = 100;
				if ( ! changed && ! terms.changed ) {
					status.textContent = t.clean;
				} else {
					status.textContent = fix
						? fmt( t.fixDone, changed, terms.changed )
						: fmt( t.scanDone, changed, total, terms.changed );
				}
			} )
			.catch( function () {
				status.textContent = t.error;
			} )
			.then( function () {
				busy( false );
			} );
	}

	if ( scanBtn ) {
		scanBtn.addEventListener( 'click', function () {
			run( false );
		} );
		fixBtn.addEventListener( 'click', function () {
			run( true );
		} );
	}
} )();

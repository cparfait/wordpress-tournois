/* We Game Tournoi — script d'administration */
( function () {
	'use strict';

	function ready() {
		// Passe automatiquement le statut à « Terminé » dès qu'un score décisif est saisi.
		var forms = document.querySelectorAll( '.wgt-match-form' );

		Array.prototype.forEach.call( forms, function ( form ) {
			var select = form.querySelector( 'select[name="status"]' );
			if ( ! select ) {
				return;
			}

			form.addEventListener( 'input', function ( event ) {
				if ( 'number' !== event.target.type ) {
					return;
				}
				if ( 'pending' === select.value ) {
					select.value = 'live';
				}
			} );
		} );

		initPreviewFrame();
		initFormatFields();
		initWizard();
		initCopyButtons();
	}

	/**
	 * Libellé traduit, avec repli en français.
	 */
	function adminText( key, fallback ) {
		if ( 'undefined' !== typeof WGT_ADMIN && WGT_ADMIN.i18n && WGT_ADMIN.i18n[ key ] ) {
			return WGT_ADMIN.i18n[ key ];
		}
		return fallback;
	}

	/**
	 * Boutons « Copier » des liens publics.
	 */
	function initCopyButtons() {
		var buttons = document.querySelectorAll( '[data-wgt-copy]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var row = button.closest( '.wgt-link__row' );
				var input = row ? row.querySelector( 'input' ) : null;
				if ( ! input ) {
					return;
				}
				input.select();
				input.setSelectionRange( 0, 99999 );
				var done = false;
				try {
					done = document.execCommand( 'copy' );
				} catch ( e ) {
					done = false;
				}
				if ( ! done && navigator.clipboard ) {
					navigator.clipboard.writeText( input.value );
					done = true;
				}
				if ( done ) {
					var previous = button.textContent;
					button.textContent = adminText( 'copied', 'Copié' );
					window.setTimeout( function () {
						button.textContent = previous;
					}, 1500 );
				}
			} );
		} );
	}

	/**
	 * Assistant de création : navigation par étapes, champs conditionnels
	 * selon le format, récapitulatif calculé.
	 */
	function initWizard() {
		var form = document.querySelector( '[data-wgt-wizard]' );
		if ( ! form ) {
			return;
		}

		var panels = form.querySelectorAll( '[data-wgt-step]' );
		var links = form.querySelectorAll( '[data-wgt-step-link]' );
		var prev = form.querySelector( '[data-wgt-prev]' );
		var next = form.querySelector( '[data-wgt-next]' );
		var submit = form.querySelector( '[data-wgt-submit]' );
		var total = panels.length;
		var current = 1;
		var i18n = {};

		var raw = form.querySelector( '[data-wgt-wizard-i18n]' );
		if ( raw ) {
			try { i18n = JSON.parse( raw.textContent ); } catch ( e ) { i18n = {}; }
		}

		function value( name ) {
			var el = form.querySelector( '[name="' + name + '"]:checked' ) || form.querySelector( '[name="' + name + '"]' );
			return el ? el.value : '';
		}

		function checked( name ) {
			var el = form.querySelector( '[name="' + name + '"]' );
			return !! ( el && el.checked );
		}

		function format( tpl ) {
			var args = Array.prototype.slice.call( arguments, 1 );
			var n = 0;
			return String( tpl ).replace( /%(\d+\$)?d/g, function ( m, pos ) {
				var idx = pos ? parseInt( pos, 10 ) - 1 : n++;
				return args[ idx ];
			} );
		}

		function show( step ) {
			current = Math.max( 1, Math.min( total, step ) );
			Array.prototype.forEach.call( panels, function ( panel ) {
				panel.hidden = parseInt( panel.getAttribute( 'data-wgt-step' ), 10 ) !== current;
			} );
			Array.prototype.forEach.call( links, function ( link ) {
				var n = parseInt( link.getAttribute( 'data-wgt-step-link' ), 10 );
				link.classList.toggle( 'is-current', n === current );
				link.classList.toggle( 'is-done', n < current );
			} );
			prev.style.visibility = current === 1 ? 'hidden' : '';
			next.hidden = current === total;
			submit.hidden = current !== total;
			if ( current === total ) {
				summary();
			}
			window.scrollTo( 0, 0 );
		}

		function validate() {
			var panel = form.querySelector( '[data-wgt-step="' + current + '"]' );
			var fields = panel.querySelectorAll( 'input, select, textarea' );
			var ok = true;
			Array.prototype.forEach.call( fields, function ( field ) {
				if ( ! field.checkValidity() ) {
					ok = false;
					field.reportValidity();
				}
			} );
			return ok;
		}

		function syncFormat() {
			var fmt = value( 'format' );
			form.querySelectorAll( '.wgt-choice' ).forEach( function ( card ) {
				var input = card.querySelector( 'input' );
				card.classList.toggle( 'is-selected', !! ( input && input.checked ) );
			} );
			Array.prototype.forEach.call( form.querySelectorAll( '.wgt-if-groups' ), function ( el ) {
				el.style.display = 'groups' === fmt ? '' : 'none';
			} );
			Array.prototype.forEach.call( form.querySelectorAll( '.wgt-if-double' ), function ( el ) {
				el.style.display = 'double' === fmt ? '' : 'none';
			} );
			Array.prototype.forEach.call( form.querySelectorAll( '.wgt-if-not-double' ), function ( el ) {
				el.style.display = 'double' === fmt ? 'none' : '';
			} );
		}

		/**
		 * Estimation tour par tour, comme le planning réel : chaque tour est
		 * une vague de matchs joués par groupes de « matchs simultanés »,
		 * un BO3 comptant deux créneaux et un BO5 trois.
		 */
		function plan() {
			var fmt = value( 'format' );
			var teams = Math.max( 2, parseInt( value( 'team_count' ), 10 ) || 2 );
			var parallel = Math.max( 1, parseInt( value( 'matches_parallel' ), 10 ) || 1 );
			var duration = Math.max( 5, parseInt( value( 'match_duration' ), 10 ) || 30 );
			var pause = parseInt( value( 'break_minutes' ), 10 ) || 0;
			var rounds = [];

			function bo( key ) {
				return parseInt( value( key ), 10 ) || 1;
			}
			function need( b ) {
				return Math.floor( b / 2 ) + 1;
			}
			function knockout( n, thirdPlace ) {
				var size = 2;
				while ( size < n ) {
					size *= 2;
				}
				var remaining = size;
				var first = true;
				while ( remaining >= 2 ) {
					var count = remaining / 2;
					if ( first ) {
						count = Math.max( 0, n - size / 2 );
						first = false;
					}
					var key = 2 === remaining ? 'bo_final' : ( 4 === remaining ? 'bo_sf' : ( 8 === remaining ? 'bo_qf' : ( 16 === remaining ? 'bo_r16' : 'bo_r64' ) ) );
					if ( count > 0 ) {
						rounds.push( { count: count, need: need( bo( key ) ) } );
					}
					if ( 4 === remaining && thirdPlace ) {
						rounds[ rounds.length - 1 ].third = true;
					}
					remaining /= 2;
				}
			}

			var third = checked( 'third_place' ) && 'double' !== fmt;

			if ( 'groups' === fmt ) {
				var groups = Math.max( 2, Math.min( parseInt( value( 'group_count' ), 10 ) || 2, Math.floor( teams / 2 ) ) );
				var qual = Math.max( 1, parseInt( value( 'qualifiers_per_group' ), 10 ) || 1 );
				var groupMatches = 0;
				for ( var g = 0; g < groups; g++ ) {
					var size = Math.floor( teams / groups ) + ( g < teams % groups ? 1 : 0 );
					groupMatches += size * ( size - 1 ) / 2;
				}
				rounds.push( { count: groupMatches, need: need( bo( 'bo_group' ) ) } );
				knockout( groups * qual, third );
			} else {
				knockout( teams, third );
			}

			if ( 'double' === fmt ) {
				// Repêchage : autant de matchs que d'équipes moins deux, en
				// tours alternés, puis la grande finale.
				var lb = Math.max( 0, teams - 2 );
				var lbRounds = Math.max( 1, 2 * ( Math.ceil( Math.log( teams ) / Math.LN2 ) - 1 ) );
				var per = Math.ceil( lb / lbRounds );
				for ( var r = 0; r < lbRounds && lb > 0; r++ ) {
					var c = Math.min( per, lb );
					rounds.push( { count: c, need: need( bo( 'bo_lb' ) ) } );
					lb -= c;
				}
				rounds.push( { count: 1 + ( checked( 'bracket_reset' ) ? 1 : 0 ), need: need( bo( 'bo_final' ) ) } );
			}

			var matches = 0;
			var minutes = parseInt( value( 'warmup_minutes' ), 10 ) || 0;
			rounds.forEach( function ( round, i ) {
				var count = round.count + ( round.third ? 1 : 0 );
				matches += count;
				minutes += Math.ceil( count / parallel ) * duration * round.need;
				if ( i > 0 ) {
					minutes += pause;
				}
			} );

			return { matches: matches, minutes: minutes };
		}

		function estimateMatches() {
			return plan().matches;
		}

		function estimateEnd() {
			var m = /^(\d{1,2}):(\d{2})$/.exec( value( 'start_time' ) );
			if ( ! m ) {
				return '';
			}
			var end = ( parseInt( m[1], 10 ) * 60 + parseInt( m[2], 10 ) + plan().minutes ) % 1440;
			var h = Math.floor( end / 60 );
			var mi = end % 60;
			return ( h < 10 ? '0' : '' ) + h + ':' + ( mi < 10 ? '0' : '' ) + mi;
		}

		function set( key, text ) {
			var el = form.querySelector( '[data-wgt-sum="' + key + '"]' );
			if ( el ) {
				el.textContent = text || '—';
			}
		}

		function summary() {
			var fmt = value( 'format' );
			var fmtLabel = i18n.formats && i18n.formats[ fmt ] ? i18n.formats[ fmt ] : fmt;
			if ( 'groups' === fmt && i18n.groups ) {
				fmtLabel += ' (' + format( i18n.groups, parseInt( value( 'group_count' ), 10 ) || 2, parseInt( value( 'qualifiers_per_group' ), 10 ) || 1 ) + ')';
			}
			set( 'title', value( 'title' ) + ( value( 'game_name' ) ? ' — ' + value( 'game_name' ) : '' ) );
			set( 'format', fmtLabel );
			set( 'teams', i18n.teams ? format( i18n.teams, parseInt( value( 'team_count' ), 10 ) || 2 ) : value( 'team_count' ) );
			set( 'matches', i18n.matches ? format( i18n.matches, estimateMatches() ) : String( estimateMatches() ) );
			set( 'when', ( value( 'event_date' ) ? value( 'event_date' ) + ' ' : '' ) + ( value( 'start_time' ) ? ( i18n.at || '') + ' ' + value( 'start_time' ) : '' ) );
			set( 'end', estimateEnd() );
			set( 'signup', checked( 'registration_open' ) ? i18n.open : i18n.closed );
			set( 'status', i18n[ value( 'post_status' ) ] || value( 'post_status' ) );
		}

		next.addEventListener( 'click', function () {
			if ( validate() ) {
				show( current + 1 );
			}
		} );
		prev.addEventListener( 'click', function () {
			show( current - 1 );
		} );
		Array.prototype.forEach.call( links, function ( link ) {
			link.addEventListener( 'click', function () {
				var n = parseInt( link.getAttribute( 'data-wgt-step-link' ), 10 );
				if ( n < current || validate() ) {
					show( n );
				}
			} );
		} );
		form.addEventListener( 'change', syncFormat );
		form.addEventListener( 'submit', function ( event ) {
			var title = form.querySelector( '[name="title"]' );
			if ( title && ! title.value.trim() ) {
				event.preventDefault();
				show( 1 );
				title.reportValidity();
			}
		} );

		// Les seizièmes suivent le réglage des premiers tours.
		var mirror = form.querySelector( '[data-wgt-mirror]' );
		if ( mirror ) {
			mirror.addEventListener( 'change', function () {
				var target = form.querySelector( '[name="' + mirror.getAttribute( 'data-wgt-mirror' ) + '"]' );
				if ( target ) {
					target.value = mirror.value;
				}
			} );
		}

		syncFormat();
		show( 1 );
	}

	/**
	 * Masque les réglages de poules hors format « poules ».
	 */
	function initFormatFields() {
		var select = document.querySelector( 'select[name="format"]' );
		var rows = document.querySelectorAll( '.wgt-if-groups' );

		if ( ! select || ! rows.length ) {
			return;
		}

		function sync() {
			var show = 'groups' === select.value;
			Array.prototype.forEach.call( rows, function ( row ) {
				row.style.display = show ? '' : 'none';
			} );
		}

		select.addEventListener( 'change', sync );
		sync();
	}

	/**
	 * Ajuste la hauteur de l'iframe d'aperçu sur son contenu.
	 */
	function initPreviewFrame() {
		var frame = document.getElementById( 'wgt-preview-frame' );
		if ( ! frame ) {
			return;
		}

		frame.style.height = '600px';

		window.addEventListener( 'message', function ( event ) {
			if ( event.origin !== window.location.origin ) {
				return;
			}
			if ( ! event.data || ! event.data.wgtPreviewHeight ) {
				return;
			}
			var h = parseInt( event.data.wgtPreviewHeight, 10 );
			if ( h > 0 && h < 20000 ) {
				frame.style.height = ( h + 8 ) + 'px';
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();

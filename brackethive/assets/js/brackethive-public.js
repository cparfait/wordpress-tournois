/* Brackethive — script public (sans dépendance) */
( function () {
	'use strict';

	/**
	 * Onglets.
	 */
	function initTabs( root ) {
		var buttons = root.querySelectorAll( '[data-brackethive-tab]' );
		var panels = root.querySelectorAll( '[data-brackethive-panel]' );

		Array.prototype.forEach.call( buttons, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var target = btn.getAttribute( 'data-brackethive-tab' );

				Array.prototype.forEach.call( buttons, function ( b ) {
					var active = b === btn;
					b.classList.toggle( 'is-active', active );
					b.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				Array.prototype.forEach.call( panels, function ( p ) {
					p.classList.toggle( 'is-active', p.getAttribute( 'data-brackethive-panel' ) === target );
				} );

				if ( window.history && window.history.replaceState ) {
					window.history.replaceState( null, '', '#brackethive-' + target );
				}
			} );
		} );

		// Ouverture directe via l'ancre.
		var hash = window.location.hash.replace( '#brackethive-', '' );
		if ( hash ) {
			var btn = root.querySelector( '[data-brackethive-tab="' + hash.replace( /[^a-z]/g, '' ) + '"]' );
			if ( btn ) {
				btn.click();
			}
		}
	}

	/**
	 * Libellé traduit, avec repli en français si la clé n'est pas fournie
	 * par wp_localize_script (aperçu d'administration, ancienne version).
	 *
	 * @param {string} key      Clé dans BRACKETHIVE_CFG.i18n.
	 * @param {string} fallback Texte de repli.
	 * @return {string}
	 */
	function i18n( key, fallback ) {
		if ( typeof BRACKETHIVE_CFG !== 'undefined' && BRACKETHIVE_CFG.i18n && BRACKETHIVE_CFG.i18n[ key ] ) {
			return String( BRACKETHIVE_CFG.i18n[ key ] );
		}
		return fallback;
	}

	/**
	 * Heure courante au format HH:MM.
	 *
	 * @return {string}
	 */
	function clock() {
		var now = new Date();
		var h = now.getHours();
		var m = now.getMinutes();
		return ( h < 10 ? '0' : '' ) + h + ':' + ( m < 10 ? '0' : '' ) + m;
	}

	/**
	 * Élément d'état du rafraîchissement, créé à la demande et placé en fin
	 * de vue. Il est retiré avant chaque remplacement du contenu puis remis en
	 * place, afin de ne pas fausser la comparaison avec le HTML reçu.
	 *
	 * @param {Element} node Vue rafraîchie.
	 * @return {Element}
	 */
	function statusElement( node ) {
		var el = node.querySelector( '.brackethive-refresh-status' );
		if ( ! el ) {
			el = document.createElement( 'p' );
			el.className = 'brackethive-refresh-status';
			el.setAttribute( 'aria-live', 'polite' );
			node.appendChild( el );
		}
		return el;
	}

	/**
	 * Met à jour l'état affiché : heure de dernière mise à jour, ou mention
	 * « hors ligne » lorsque le serveur ne répond plus.
	 *
	 * @param {Element} node    Vue rafraîchie.
	 * @param {boolean} offline Vrai si la dernière requête a échoué.
	 */
	function setStatus( node, offline ) {
		var el = statusElement( node );
		node.classList.toggle( 'is-offline', offline );
		if ( offline ) {
			el.textContent = i18n( 'offline', 'Hors ligne — nouvelle tentative en cours' );
		} else {
			el.textContent = i18n( 'updated', 'Mis à jour' ) + ' ' + clock();
		}
	}

	/**
	 * URL de la route de rendu pour une vue donnée. Les options d'affichage
	 * portées par le conteneur (en-tête, ajustement) sont renvoyées telles
	 * quelles pour obtenir exactement le même HTML que le code court.
	 *
	 * @param {Element} node Vue rafraîchie.
	 * @return {string}
	 */
	function renderUrl( node ) {
		var view = node.getAttribute( 'data-brackethive-live' );
		var tournament = node.getAttribute( 'data-brackethive-tournament' ) || '';
		var header = node.getAttribute( 'data-brackethive-header' ) === 'no' ? 'no' : 'yes';
		var fit = node.getAttribute( 'data-brackethive-fit' ) === 'width' ? 'width' : 'screen';
		var url = BRACKETHIVE_CFG.endpoint + ( BRACKETHIVE_CFG.endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) + 'view=' + encodeURIComponent( view );

		if ( tournament ) {
			url += '&tournament=' + encodeURIComponent( tournament );
		}
		url += '&header=' + header + '&fit=' + fit;
		url += '&_=' + Date.now();

		return url;
	}

	/**
	 * Rafraîchit une vue. Résout à vrai en cas de succès, faux sinon.
	 *
	 * @param {Element} node Vue rafraîchie.
	 * @return {Promise}
	 */
	function refreshNode( node ) {
		return fetch( renderUrl( node ), { credentials: 'same-origin' } )
			.then( function ( r ) {
				if ( ! r.ok ) {
					throw new Error( 'HTTP ' + r.status );
				}
				return r.json();
			} )
			.then( function ( data ) {
				if ( ! data || typeof data.html !== 'string' ) {
					throw new Error( 'Réponse vide' );
				}

				var temp = document.createElement( 'div' );
				temp.innerHTML = data.html;
				var fresh = temp.firstElementChild;
				if ( ! fresh ) {
					throw new Error( 'HTML inattendu' );
				}

				// L'élément d'état ne fait pas partie du HTML serveur.
				var status = node.querySelector( '.brackethive-refresh-status' );
				if ( status ) {
					node.removeChild( status );
				}

				if ( fresh.innerHTML !== node.innerHTML ) {
					node.innerHTML = fresh.innerHTML;
					// Le remplacement a détruit la mise à l'échelle.
					fitBrackets();
				}

				setStatus( node, false );
				return true;
			} )
			.catch( function () {
				setStatus( node, true );
				return false;
			} );
	}

	/**
	 * Rafraîchissement automatique des vues marquées data-brackethive-live.
	 *
	 * Après trois échecs consécutifs, l'intervalle double à chaque nouvel
	 * échec, jusqu'à cinq minutes ; il revient à sa valeur de départ dès
	 * qu'une requête aboutit.
	 */
	function initLive() {
		if ( typeof BRACKETHIVE_CFG === 'undefined' || ! BRACKETHIVE_CFG.interval || BRACKETHIVE_CFG.interval < 10 ) {
			return;
		}

		var nodes = document.querySelectorAll( '[data-brackethive-live]' );
		if ( ! nodes.length ) {
			return;
		}

		var base = Number( BRACKETHIVE_CFG.interval ) * 1000;
		var max = 5 * 60 * 1000;
		var current = base;
		var failures = 0;

		function schedule() {
			window.setTimeout( tick, current );
		}

		function tick() {
			if ( document.hidden ) {
				schedule();
				return;
			}

			var pending = [];
			Array.prototype.forEach.call( nodes, function ( node ) {
				pending.push( refreshNode( node ) );
			} );

			Promise.all( pending ).then( function ( results ) {
				var ok = true;
				for ( var i = 0; i < results.length; i++ ) {
					if ( ! results[ i ] ) {
						ok = false;
					}
				}

				if ( ok ) {
					failures = 0;
					current = base;
				} else {
					failures++;
					if ( failures >= 3 ) {
						current = Math.min( current * 2, max );
					}
				}

				schedule();
			} );
		}

		schedule();
	}

	/**
	 * Calcule la hauteur réellement disponible à l'écran pour un tableau.
	 *
	 * @param {Element} wrap    Conteneur du tableau.
	 * @return {number} Hauteur utilisable, en pixels.
	 */
	function availableHeight( wrap ) {
		var wrapRect = wrap.getBoundingClientRect();

		// Position dans le document : ne dépend pas du défilement courant.
		var docTop = wrapRect.top + window.scrollY;
		var above = Math.min( docTop, window.innerHeight * 0.5 );

		// Ce qui suit le tableau : légende, mais aussi la marge basse du
		// conteneur le plus externe (en vue à onglets, les blocs sont
		// imbriqués — s'arrêter au plus proche sous-estimerait la place).
		var view = wrap;
		var node = wrap.parentElement;
		while ( node ) {
			if ( node.classList && node.classList.contains( 'brackethive' ) ) {
				view = node;
			}
			node = node.parentElement;
		}

		var below = view === wrap ? 0 : Math.max( 0, view.getBoundingClientRect().bottom - wrapRect.bottom );

		return window.innerHeight - above - below - 16;
	}

	/**
	 * Fait tenir le tableau à l'écran, sans défilement.
	 *
	 * Deux modes, lus sur data-brackethive-fit-mode :
	 * - « screen » (défaut) : trois étapes, dans cet ordre — taille normale si
	 *   elle suffit, puis mode dense (métadonnées secondaires masquées), puis
	 *   mise à l'échelle pour tenir en largeur ET en hauteur ;
	 * - « width » : seule la largeur est contrainte, la page défile
	 *   verticalement si besoin.
	 * Sans effet quand les tours sont empilés (petits écrans).
	 */
	function fitBrackets() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-brackethive-fit]' ), function ( wrap ) {
			var inner = wrap.querySelector( '.brackethive-bracket-fit' );
			var bracket = wrap.querySelector( '.brackethive-bracket' );

			if ( ! inner || ! bracket ) {
				return;
			}

			// Remise à zéro avant toute mesure.
			inner.style.transform = '';
			inner.style.width = '';
			wrap.style.height = '';
			bracket.classList.remove( 'is-dense' );

			if ( ! wrap.classList.contains( 'is-fit' ) ) {
				return;
			}

			// Mode empilé : chaque match occupe la largeur, rien à ajuster.
			if ( 'column' === window.getComputedStyle( bracket ).flexDirection ) {
				return;
			}

			var available = wrap.clientWidth;
			if ( ! available || ! bracket.scrollWidth ) {
				return;
			}

			var widthOnly = 'width' === wrap.getAttribute( 'data-brackethive-fit-mode' );
			var room = widthOnly ? 0 : availableHeight( wrap );
			var limitHeight = ! widthOnly && room > 260;
			var ratio = 1;

			// 1. Contrainte de largeur.
			if ( bracket.scrollWidth > available ) {
				ratio = available / bracket.scrollWidth;
			}

			// 2. Densification si la hauteur ne suffit toujours pas.
			if ( limitHeight && bracket.scrollHeight * ratio > room ) {
				bracket.classList.add( 'is-dense' );
			}

			// 3. Contrainte de hauteur. En deçà de 0,6 le texte deviendrait
			//    illisible : on préfère alors laisser défiler un peu.
			if ( limitHeight && bracket.scrollHeight * ratio > room ) {
				ratio = Math.max( 0.6, Math.min( ratio, room / bracket.scrollHeight ) );
			}

			if ( ratio >= 1 ) {
				return;
			}

			/*
			 * On élargit la mise en page à « available / ratio » : une fois
			 * réduite, elle occupe exactement la largeur disponible. Sans
			 * cela, réduire pour la hauteur laisserait de grandes marges
			 * vides de chaque côté sur les écrans larges.
			 */
			inner.style.width = Math.round( available / ratio ) + 'px';

			// La largeur ayant changé, la hauteur a pu diminuer : on affine.
			if ( limitHeight && bracket.scrollHeight * ratio > room ) {
				ratio = Math.max( 0.6, room / bracket.scrollHeight );
				inner.style.width = Math.round( available / ratio ) + 'px';
			}

			inner.style.transform = 'scale(' + ratio + ')';
			wrap.style.height = Math.ceil( bracket.scrollHeight * ratio ) + 'px';
		} );
	}

	var fitTimer = null;

	function scheduleFit() {
		window.clearTimeout( fitTimer );
		fitTimer = window.setTimeout( fitBrackets, 120 );
	}

	function ready() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-brackethive-tabs]' ), initTabs );
		fitBrackets();
		initLive();

		window.addEventListener( 'resize', scheduleFit );
		window.addEventListener( 'load', fitBrackets );

		// Le tableau peut être dans un onglet masqué : sa largeur n'est
		// mesurable qu'une fois affiché.
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest && event.target.closest( '[data-brackethive-tab]' ) ) {
				scheduleFit();
			}
		} );
	}

	// Exposé pour le rafraîchissement automatique.
	window.brackethiveFitBrackets = fitBrackets;

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();

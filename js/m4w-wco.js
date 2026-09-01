/* global jQuery, m4wWco */

( function ( $ ) {

	$.m4wWco = {
		'init': function () {
			if ( typeof m4wWco === 'undefined' ) {
				return;
			}

			this.rules = m4wWco.rules || [];
			this.ajaxUrl = m4wWco.ajaxUrl;
			this.nonce = m4wWco.nonce;
			this.popupCookieName = m4wWco.popupCookieName;
			this.i18n = m4wWco.i18n || {};

			this.bindAddedToCart();
			this.bindCartPageUpdates();
			this.checkInlineOffersOnLoad();
		},

		'bindAddedToCart': function () {
			var self = this;

			$( document.body ).on( 'added_to_cart', function ( e, fragments, cart_hash, $button ) {
				var productId = self.getButtonProductId( $button );
				if ( ! productId ) {
					return;
				}

				self.checkAndShowOffers( productId );
			} );
		},

		'bindCartPageUpdates': function () {
			var self = this;

			$( document.body ).on( 'updated_cart_totals', function () {
				self.refreshInlineOffers();
			} );

			$( document.body ).on( 'wc_fragments_refreshed', function () {
				self.refreshInlineOffers();
			} );

			$( document.body ).on( 'removed_from_cart', function () {
				self.refreshInlineOffers();
			} );

			$( document.body ).on( 'added_to_cart', function () {
				setTimeout( function () {
					self.refreshInlineOffers();
				}, 500 );
			} );
		},

		'getButtonProductId': function ( button ) {
			var $button = $( button );
			var productId = parseInt( $button.data( 'variation_id' ), 10 ) || parseInt( $button.data( 'product_id' ), 10 ) || 0;

			return productId > 0 ? productId : 0;
		},

		'checkAndShowOffers': function ( addedProductId ) {
			var self = this;

			$.ajax( {
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'm4w_wco_check_offers',
					nonce: this.nonce,
				},
				success: function ( response ) {
					if ( response.success && response.data.active_rules ) {
						self.showActiveOffers( addedProductId, response.data.active_rules );
					}
				}
			} );
		},

		'showActiveOffers': function ( addedProductId, activeRules ) {
			var self = this;
			addedProductId = parseInt( addedProductId, 10 );

			$.each( activeRules, function ( index, rule ) {
				var triggerProductIds = $.map( rule.trigger_product_ids || [], function ( id ) {
					return parseInt( id, 10 );
				} );

				if ( $.inArray( addedProductId, triggerProductIds ) === -1 ) {
					return;
				}

				var shouldShowToast = !! rule.show_toast;
				var shouldShowPopup = !! rule.show_popup;

				if ( shouldShowToast && ! ( rule.popup_once_per_session && self.hasPopupBeenShown( rule.id ) ) ) {
					self.showToast( rule );
				}
				if ( shouldShowPopup && ! ( rule.popup_once_per_session && self.hasPopupBeenShown( rule.id ) ) ) {
					self.showPopup( rule );
				}
			} );
		},

		'hasPopupBeenShown': function ( ruleId ) {
			var cookieName = this.popupCookieName + '_' + ruleId;
			return document.cookie.indexOf( cookieName + '=' ) !== -1;
		},

		'setPopupShown': function ( ruleId ) {
			var cookieName = this.popupCookieName + '_' + ruleId;
			document.cookie = cookieName + '=1; path=/; SameSite=Lax';
		},

		'scopeCss': function ( css, scope ) {
			var blocks = css.split( '}' );
			var out = '';
			for ( var i = 0; i < blocks.length; i++ ) {
				var block = blocks[ i ].trim();
				if ( ! block ) {
					continue;
				}
				var ob = block.indexOf( '{' );
				if ( ob === -1 ) {
					continue;
				}
				var selectors = block.substring( 0, ob );
				var body = block.substring( ob + 1 );
				var scoped = selectors.split( ',' ).map( function ( sel ) {
					sel = sel.trim();
					if ( ! sel ) {
						return '';
					}
					if ( sel === '.m4w-wco-toast' || sel === '.conditional-offer-toast' || sel === scope ) {
						return scope;
					}
					return scope + ' ' + sel;
				} ).filter( function ( s ) { return s; } ).join( ', ' );
				out += scoped + ' {' + body + '} ';
			}
			return out;
		},

		'showToast': function ( rule ) {
			var self = this;

			var delay = typeof rule.popup_delay !== 'undefined' ? parseFloat( rule.popup_delay ) : 1;
			var timeout = typeof rule.toast_timeout !== 'undefined' ? parseInt( rule.toast_timeout, 10 ) : 8;
			delay = ( isNaN( delay ) || delay < 0 ? 1 : delay ) * 1000;
			timeout = ( isNaN( timeout ) || timeout < 3 ? 8 : timeout ) * 1000;

			setTimeout( function () {
				$.ajax( {
					url: self.ajaxUrl,
					type: 'POST',
					data: {
						action: 'm4w_wco_get_offer_popup_content',
						rule_id: rule.id,
						nonce: self.nonce,
					},
					success: function ( response ) {
						if ( response.success && response.data ) {
							self.renderToast( rule, response.data.content, timeout );
							self.setPopupShown( rule.id );
						}
					}
				} );
			}, delay );
		},

		'renderToast': function ( rule, content, timeout ) {
			var self = this;

			$( '.m4w-wco-toast[data-rule-id="' + rule.id + '"]' ).remove();
			$( '#m4w-wco-toast-css-' + rule.id ).remove();

			var $toast = $( '<div class="m4w-wco-toast conditional-offer-toast m4w-wco-toast-rule-' + rule.id + '" data-rule-id="' + rule.id + '" role="status"></div>' );

			var $inner = $( '<div class="conditional-offer-toast-inner"></div>' ).html( content );
			$toast.append( $inner );

			var $closeBtn = $( '<button type="button" class="m4w-wco-toast-close" aria-label="' + ( self.i18n.close || 'Close' ) + '">&times;</button>' );
			$toast.append( $closeBtn );

			if ( rule.toast_css ) {
				var scope = '.m4w-wco-toast-rule-' + rule.id;
				var scoped = $.m4wWco.scopeCss( rule.toast_css, scope );
				if ( scoped ) {
					$( '<style id="m4w-wco-toast-css-' + rule.id + '">' + scoped + '</style>' ).appendTo( 'head' );
				}
			}

			$( document.body ).append( $toast );

			setTimeout( function () {
				$toast.addClass( 'show' );
			}, 50 );

			setTimeout( function () {
				$toast.addClass( 'hide' );
				setTimeout( function () {
					$toast.remove();
				}, 400 );
			}, timeout );

			$closeBtn.on( 'click', function () {
				$toast.addClass( 'hide' );
				setTimeout( function () {
					$toast.remove();
				}, 400 );
			} );
		},

		'showPopup': function ( rule ) {
			var self = this;

			var delay = typeof rule.popup_delay !== 'undefined' ? parseFloat( rule.popup_delay ) : 1;
			delay = ( isNaN( delay ) || delay < 0 ? 1 : delay ) * 1000;

			setTimeout( function () {
				var $popup = $( '<div class="conditional-offer-popup" data-rule-id="' + rule.id + '">' +
					'<div class="conditional-offer-popup-overlay"></div>' +
					'<div class="conditional-offer-popup-content">' +
					'<button class="conditional-offer-popup-close" aria-label="' + ( self.i18n.close || 'Close' ) + '">&times;</button>' +
					'<div class="conditional-offer-popup-body"><div class="conditional-offer-popup-loading" role="status"><span class="m4w-wco-spinner" aria-hidden="true"></span></div></div>' +
					'</div>' +
					'</div>' );

				$.ajax( {
					url: self.ajaxUrl,
					type: 'POST',
					data: {
						action: 'm4w_wco_get_offer_popup_content',
						rule_id: rule.id,
						nonce: self.nonce,
					},
					success: function ( response ) {
						if ( response.success && response.data ) {
							$popup.find( '.conditional-offer-popup-body' ).html( response.data.content );
							self.setPopupShown( rule.id );
						} else {
							self.closePopup( $popup );
						}
					},
					error: function () {
						self.closePopup( $popup );
					}
				} );

				$( 'body' ).append( $popup );

				$popup.find( '.conditional-offer-popup-close, .conditional-offer-popup-overlay' ).on( 'click', function () {
					self.closePopup( $popup );
				} );

				$popup.on( 'click', 'button, a', function ( e ) {
					if ( $( this ).hasClass( 'conditional-offer-popup-close' ) || $( this ).data( 'prevent-close' ) ) {
						return;
					}
					self.closePopup( $popup );
				} );

				setTimeout( function () {
					$popup.addClass( 'show' );
				}, 10 );

			}, delay );
		},

		'closePopup': function ( $popup ) {
			$popup.removeClass( 'show' );
			setTimeout( function () {
				$popup.remove();
			}, 300 );
		},

		'refreshInlineOffers': function () {
			var self = this;

			if ( ! $( '.conditional-offer-inline' ).length ) {
				return;
			}

			$.ajax( {
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'm4w_wco_check_offers',
					nonce: this.nonce,
				},
				success: function ( response ) {
					if ( response.success && response.data.active_rules ) {
						self.updateInlineOffers( response.data.active_rules );
					}
				}
			} );
		},

		'checkInlineOffersOnLoad': function () {
			if ( $( '.conditional-offer-inline' ).length ) {
				this.refreshInlineOffers();
			}
		},

		'updateInlineOffers': function ( activeRules ) {
			var activeRulesById = {};
			$.each( activeRules, function ( index, rule ) {
				activeRulesById[ parseInt( rule.id, 10 ) ] = rule;
			} );

			$( '.conditional-offer-inline' ).each( function () {
				var $wrapper = $( this );
				var ruleId = parseInt( $wrapper.data( 'rule-id' ), 10 );
				var rule = activeRulesById[ ruleId ];

				if ( rule ) {
					$wrapper.html( rule.content );
					$wrapper.show();
				} else {
					$wrapper.hide();
				}
			} );
		}
	};

} )( jQuery );

jQuery( document ).ready( function () {
	if ( jQuery.m4wWco ) {
		jQuery.m4wWco.init();
	}
} );

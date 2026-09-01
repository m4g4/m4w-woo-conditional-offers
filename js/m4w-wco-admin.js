/* global jQuery, m4wWcoAdmin */

( function ( $ ) {

	$.m4wWcoAdmin = {
		'init': function () {
			if ( typeof m4wWcoAdmin === 'undefined' ) {
				return;
			}

			this.ajaxUrl = m4wWcoAdmin.ajaxUrl;
			this.nonce = m4wWcoAdmin.nonce;
			this.action = m4wWcoAdmin.action;
			this.nonceName = m4wWcoAdmin.nonceName;
			this.i18n = m4wWcoAdmin.i18n || {};

			this.bindEvents();
		},

		'bindEvents': function () {
			var self = this;

			$( '#m4w-wco-add-new' ).on( 'click', function () {
				self.openModal();
			} );

			$( '#m4w-wco-rules-table' ).on( 'click', '.m4w-wco-edit', function () {
				self.openModal( $( this ).data( 'rule-id' ) );
			} );

			$( '#m4w-wco-rules-table' ).on( 'click', '.m4w-wco-delete', function () {
				if ( confirm( self.i18n.deleteConfirm || 'Delete this rule?' ) ) {
					self.deleteRule( $( this ).data( 'rule-id' ) );
				}
			} );

			$( '.m4w-wco-modal-close, .m4w-wco-modal-overlay, .m4w-wco-modal-cancel' ).on( 'click', function () {
				self.closeModal();
			} );

			$( '#m4w-wco-form' ).on( 'submit', function ( e ) {
				e.preventDefault();
				self.saveRule( $( this ) );
			} );

			$( document ).on( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && $( '#m4w-wco-modal' ).is( ':visible' ) ) {
					self.closeModal();
				}
			} );

			$( '#m4w-wco-show_popup, #m4w-wco-show_toast' ).on( 'change', function () {
				self.toggleConditional();
			} );
		},

		'toggleConditional': function () {
			var showPopup = $( '#m4w-wco-show_popup' ).is( ':checked' );
			var showToast = $( '#m4w-wco-show_toast' ).is( ':checked' );
			$( '.m4w-wco-cond-popup' ).toggle( showPopup );
			$( '.m4w-wco-cond-toast' ).toggle( showToast );
		},

		'openModal': function ( ruleId ) {
			var self = this;
			var $modal = $( '#m4w-wco-modal' );
			var $form = $( '#m4w-wco-form' );

			$form[0].reset();
			$( '#m4w-wco-rule-id' ).val( '' );
			$( '#m4w-wco-modal-title' ).text( self.i18n.addNewRule || 'Add New Rule' );

			if ( ! ruleId ) {
				$( '#m4w-wco-show_popup' ).prop( 'checked', true );
				self.toggleConditional();
				$modal.show();
				return;
			}

			var data = {
				action: 'm4w_wco_get_offer_rule',
				rule_id: ruleId,
			};
			data[ self.nonceName ] = self.nonce;

			$.ajax( {
				url: self.ajaxUrl,
				type: 'POST',
				data: data,
				success: function ( response ) {
					if ( response.success && response.data ) {
						self.populateForm( response.data );
						$( '#m4w-wco-modal-title' ).text( self.i18n.editRule || 'Edit Rule' );
						$modal.show();
					} else {
						alert( ( self.i18n.errorLoading || 'Error loading rule:' ) + ' ' + ( response.data || self.i18n.unknownError || 'Unknown error' ) );
					}
				},
				error: function () {
					alert( self.i18n.ajaxErrorLoading || 'AJAX error loading rule' );
				}
			} );
		},

		'populateForm': function ( rule ) {
			$( '#m4w-wco-rule-id' ).val( rule.id );
			$( '#m4w-wco-label' ).val( rule.label );
			$( '#m4w-wco-trigger_ids' ).val( ( rule.trigger_product_ids || [] ).join( ', ' ) );
			$( '#m4w-wco-offer_id' ).val( rule.offer_product_id );
			$( '#m4w-wco-content' ).val( rule.custom_content || '' );
			$( '#m4w-wco-show_popup' ).prop( 'checked', !! rule.show_popup );
			$( '#m4w-wco-show_toast' ).prop( 'checked', !! rule.show_toast );
			this.toggleConditional();
			$( '#m4w-wco-toast_timeout' ).val( typeof rule.toast_timeout !== 'undefined' ? rule.toast_timeout : 8 );
			$( '#m4w-wco-toast_css' ).val( rule.toast_css || '' );
			$( '#m4w-wco-popup_delay' ).val( typeof rule.popup_delay !== 'undefined' ? rule.popup_delay : 1 );
			$( '#m4w-wco-popup_once' ).prop( 'checked', !! rule.popup_once_per_session );
			$( '#m4w-wco-discount_enabled' ).prop( 'checked', !! rule.discount_enabled );
			$( '#m4w-wco-discount_type' ).val( rule.discount_type || 'percentage' );
			$( '#m4w-wco-discount_value' ).val( rule.discount_value || 0 );
			$( '#m4w-wco-discount_apply_to' ).val( rule.discount_apply_to || 'offer_only' );
		},

		'closeModal': function () {
			$( '#m4w-wco-modal' ).hide();
		},

		'saveRule': function ( $form ) {
			var self = this;
			var $submitBtn = $form.find( 'button[type="submit"]' );
			var originalText = $submitBtn.text();
			var formData = $.grep( $form.serializeArray(), function ( field ) {
				return field.name !== 'action' && field.name !== self.nonceName;
			} );

			var checkboxFields = [ 'show_popup', 'show_toast', 'popup_once_per_session', 'discount_enabled' ];
			$.each( checkboxFields, function ( index, fieldName ) {
				var $checkbox = $( '[name="' + fieldName + '"]' ).filter( '[type="checkbox"]' );
				formData = $.grep( formData, function ( field ) {
					return field.name !== fieldName;
				} );
				formData.push( { name: fieldName, value: $checkbox.is( ':checked' ) ? '1' : '0' } );
			} );

			formData.push( { name: 'action', value: 'm4w_wco_save_offer' } );
			formData.push( { name: self.nonceName, value: self.nonce } );

			$submitBtn.prop( 'disabled', true ).text( self.i18n.saving || 'Saving...' );

			$.ajax( {
				url: self.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function ( response ) {
					if ( response.success ) {
						window.location.reload();
					} else {
						alert( ( self.i18n.error || 'Error:' ) + ' ' + ( response.data || self.i18n.unknownError || 'Unknown error' ) );
					}
				},
				error: function () {
					alert( self.i18n.ajaxError || 'AJAX error occurred' );
				},
				complete: function () {
					$submitBtn.prop( 'disabled', false ).text( originalText );
				}
			} );
		},

		'deleteRule': function ( ruleId ) {
			var self = this;
			var data = {
				action: 'm4w_wco_delete_offer',
				rule_id: ruleId,
			};
			data[ self.nonceName ] = self.nonce;

			$.ajax( {
				url: self.ajaxUrl,
				type: 'POST',
				data: data,
				success: function ( response ) {
					if ( response.success ) {
						window.location.reload();
					} else {
						alert( ( self.i18n.error || 'Error:' ) + ' ' + ( response.data || self.i18n.unknownError || 'Unknown error' ) );
					}
				},
				error: function () {
					alert( self.i18n.ajaxError || 'AJAX error occurred' );
				}
			} );
		}
	};

} )( jQuery );

jQuery( document ).ready( function () {
	if ( jQuery.m4wWcoAdmin ) {
		jQuery.m4wWcoAdmin.init();
	}
} );

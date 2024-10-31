jQuery(function ($) {
	$(document).on('click', '.js-open-na-splatky-tb-modal', function () {
		var $modal = $('#na-splatky-tb-modal');
		var $exits = $modal.find('.js-na-splatky-tb-modal-exit');
		var $singleProduct = $('body.single-product');

		$modal.addClass('is-open');

		if ($singleProduct.length > 0) {
			// Calculate and refresh modal when on single product
			var product_id = $singleProduct.find('.cart [name=add-to-cart]').val();
			var quantity = $singleProduct.find('.cart [name=quantity]').val();
			var variation_id = $singleProduct
				.find('.variations_form [name=variation_id]')
				.val();

			// Handle grouped product quantity
			// TODO
			// var $groupedListQty = $singleProduct.find(
			// 	'.woocommerce-grouped-product-list .qty'
			// );
			// if ($groupedListQty.length > 0) {
			// 	var summedQuantity = 0;
			// 	$.each($groupedListQty, function () {
			// 		console.log($(this))
			// 		console.log($(this).val())
			// 		summedQuantity += $(this).val();
			// 	});
			// 	quantity = summedQuantity;
			// }

			refresh_modal(product_id, quantity, variation_id);
		} else {
			// Or just load modal when on checkout
			refresh_modal();
		}

		$exits.on('click', function () {
			close_modal();
		});
	});

	function close_modal() {
		var $modal = $('#na-splatky-tb-modal');
		$modal.removeClass('is-open');
	}

	/**
	 *  Refresh modal AJAX
	 */
	var refresh_ajax = {
		url: wc_tb_nasplatky_params.wc_ajax_url
			.toString()
			.replace('%%endpoint%%', 'get_wc_tb_refreshed_modal'),
		type: 'GET',
		data: {
			nonce: wc_tb_nasplatky_params.nonce,
		},
		beforeSend: function () {
			$('.js-na-splatky-tb-modal-container').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			});
		},
		complete: function () {
			$('.js-na-splatky-tb-modal-container').unblock();
		},
		success: function (data) {
			if (data && data.modal) {
				$('.js-na-splatky-tb-modal-boxes').empty().append(data.modal);
			}
		},
	};

	function refresh_modal(product_id, quantity, variation_id = 0) {
		if (variation_id > 0) {
			refresh_ajax.data.variation_id = variation_id;
		}

		refresh_ajax.data.product_id = product_id;
		refresh_ajax.data.quantity = quantity;
		$.ajax(refresh_ajax);
	}

	// Enable modal button when variation is selected
	$('.variations_form').on('show_variation', function (event, data) {
		$('.js-open-na-splatky-tb-modal').attr('disabled', false);
	});

	// Disable modal button when variation is not selected
	$('.variations_form').on('hide_variation', function () {
		$('.js-open-na-splatky-tb-modal').attr('disabled', 'disabled');
	});

	/**
	 * Save choice to SESSION AJAX
	 */
	var save_choice_ajax = {
		url: wc_tb_nasplatky_params.wc_ajax_url
			.toString()
			.replace('%%endpoint%%', 'set_wc_tb_loan_duration_session'),
		type: 'POST',
		data: {
			loan_duration_choice: false,
			nonce: wc_tb_nasplatky_params.nonce,
		},
		beforeSend: function () {
			$('.js-na-splatky-tb-save-choice').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			});
		},
		complete: function () {
			$('.js-na-splatky-tb-save-choice').unblock();
		},
		success: function () {
			// On single product, we want to redirect user after submitting calculator,
			// toggle value in hidden input
			var $singleProduct = $('body.single-product');
			var $redirectInput = $('[name=wc_tb_nasplatky_cart_redirect]');
			if ($singleProduct.length > 0 && $redirectInput.length > 0) {
				if ($redirectInput) {
					$redirectInput.val(1);

					// Trigger add to cart action
					$('.single_add_to_cart_button').trigger('click');
				}
			} else {
				// Otherwise just save choice and close modal
				close_modal();
			}
		},
		error: function (data) {
			console.error('Error while saving choice');
		},
	};
	$(document).on('click', '.js-na-splatky-tb-save-choice', function (event) {
		var choice = $('[name=wc_tb_nasplatky_choice]:checked').val();

		if (choice) {
			save_choice_ajax.data.loan_duration_choice = choice;
			$.ajax(save_choice_ajax);
		}
	});
});

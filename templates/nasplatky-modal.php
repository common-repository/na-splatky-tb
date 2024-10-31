<?php
namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

$nasplatky_logotext_link = '<a href="https://www.tatrabanka.sk/sk/personal/uvery/na-splatky/#tab-1" class="nasplatky-logotext" target="_blank">' . PRODUCT_NAME . '</a>';
?>

<div class="na-splatky-tb-modal" id="na-splatky-tb-modal">
	<div class="na-splatky-tb-modal__bg js-na-splatky-tb-modal-exit"></div>

	<div class="na-splatky-tb-modal__container js-na-splatky-tb-modal-container">
		<div class="na-splatky-tb-modal__header">
			<span><?php echo __('Installment settings', 'na-splatky-tb'); ?></span>
			<button type="button" class="na-splatky-tb-modal__close js-na-splatky-tb-modal-exit"></button>
		</div>

		<div class="na-splatky-tb-modal__body">
			<div class="na-splatky-tb-modal__notice">
				<?php
                    /* translators: %s = Markup with link to the NaSplatky product page */
                    printf(__('Discover all benefits of paying via %s', 'na-splatky-tb'), $nasplatky_logotext_link);
                ?>
			</div>

			<div class="js-na-splatky-tb-modal-boxes"></div>

			<?php if (isset($cart_totals)) : ?>
				<div class="na-splatky-tb-modal__cart-info">
					<?php echo __('Purchase value', 'na-splatky-tb'); ?>

					<span class="na-splatky-tb-modal__cart-info-price">
						<?php echo esc_html(Plugin::format_price($cart_totals)); ?>
					</span>
				</div>
			<?php endif; ?>
		</div>

	</div>
</div>

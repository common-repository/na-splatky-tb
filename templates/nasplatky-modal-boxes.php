<?php
namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

$nasplatky_logotext = '<span class="nasplatky-logotext">' . PRODUCT_NAME . '</span>';
?>

<?php if ($data) : ?>
	<div class="na-splatky-tb-modal__desc">
		<!-- TODO custom text -->
		<?php echo __('Custom installment amount can be configured in loan application after submitting your order.', 'na-splatky-tb'); ?>
	</div>

	<div class="na-splatky-tb-modal__boxes">
		<?php
        $i = 0;
        foreach ($data as $item) :
            $i++;
            // Default would be second box
            if ($checked_choice) {
                list($checked_class, $checked_duration) = explode(':', $checked_choice);
                $checked = $i == $checked_class;
            } else {
                $checked = $i == 2;
            }
        ?>
			<label class="na-splatky-tb-modal__boxes-col na-splatky-tb-modal-box">
				<input type="radio" name="wc_tb_nasplatky_choice" class="na-splatky-tb-modal-box__radio" value="<?php echo esc_attr($i . ':' . $item->LoanDuration); ?>" <?php echo $checked ? 'checked' : ''; ?> />
				<div class="na-splatky-tb-modal-box__inner">
					<div class="na-splatky-tb-modal-box__title">
						<?php
                        $title = '';
                        switch ($i) {
                            case 1:
                                $title = __('Pay off quickly', 'na-splatky-tb');
                                break;
                            case 2:
                                $title = __('Favorite choice', 'na-splatky-tb');
                                break;
                            case 3:
                                $title = __('Low payments', 'na-splatky-tb');
                                break;
                        }

                        echo esc_attr($title);
                        ?>
					</div>

					<div class="na-splatky-tb-modal-box__body">
						<div class="na-splatky-tb-modal-box__date">
							<?php
                                /* translators: %s = number of months */
                                printf(_n('%s month', '%s months', $item->LoanDuration, 'na-splatky-tb'), $item->LoanDuration);
                            ?>
						</div>

						<div class="na-splatky-tb-modal-box__price">
							<?php echo esc_html(Plugin::format_price($item->InstallmentAmount, ['currency' => null])); ?>

							<span class="na-splatky-tb-modal-box__price-suffix">
								<?php
                                    /* translators: %s = currency code */
                                    printf(__('%s / month', 'na-splatky-tb'), get_woocommerce_currency());
                                ?>
							</span>
						</div>

						<div class="na-splatky-tb-modal-box__params">
							<div class="na-splatky-tb-modal-box__params-row">
								<div class="na-splatky-tb-modal-box__params-title">
									<?php echo __('Loan amount', 'na-splatky-tb'); ?>
								</div>
								<div class="na-splatky-tb-modal-box__params-value">
									<?php echo esc_html(Plugin::format_price($all_totals)); ?>
								</div>
							</div>
							<div class="na-splatky-tb-modal-box__params-row">
								<div class="na-splatky-tb-modal-box__params-title">
									<?php echo __('Monthly payment', 'na-splatky-tb'); ?>
								</div>
								<div class="na-splatky-tb-modal-box__params-value">
									<?php echo esc_html(Plugin::format_price($item->InstallmentAmount)); ?>
								</div>
							</div>
							<div class="na-splatky-tb-modal-box__params-row">
								<div class="na-splatky-tb-modal-box__params-title">
									<?php echo __('Interest rate', 'na-splatky-tb'); ?>
								</div>
								<div class="na-splatky-tb-modal-box__params-value">
									<?php echo esc_html(Plugin::format_price($item->LoanInterestRate, ['currency' => '% p.a.'])); ?>
								</div>
							</div>
							<div class="na-splatky-tb-modal-box__params-row">
								<div class="na-splatky-tb-modal-box__params-title">
									<?php echo __('Loan fee', 'na-splatky-tb'); ?>
								</div>
								<div class="na-splatky-tb-modal-box__params-value">
									<?php echo esc_html(Plugin::format_price($item->LoanFee)); ?>
								</div>
							</div>
							<div class="na-splatky-tb-modal-box__params-row">
								<div class="na-splatky-tb-modal-box__params-title">
									<?php
                                        /* translators: Effective Annual Percentage Rate */
                                        echo __('EAPR', 'na-splatky-tb');
                                    ?>
								</div>
								<div class="na-splatky-tb-modal-box__params-value">
									<?php echo esc_html(Plugin::format_price($item->RPMN, ['currency' => '%'])); ?>
								</div>
							</div>
							<div class="na-splatky-tb-modal-box__params-row">
								<div class="na-splatky-tb-modal-box__params-title">
									<?php echo __('Total amount', 'na-splatky-tb'); ?>
								</div>
								<div class="na-splatky-tb-modal-box__params-value">
									<?php echo esc_html(Plugin::format_price($item->TotalAmount)); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</label>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ($data && $allow_continue) : ?>
	<div class="na-splatky-tb-modal__btn-wrapper">
		<?php if (!empty($product)) : ?>
			<input type="hidden" name="wc_tb_nasplatky_cart_redirect" value="0">
		<?php endif; ?>

		<button type="button" class="na-splatky-tb-modal__btn js-na-splatky-tb-save-choice js-na-splatky-tb-modal-exit">
			<?php
                /* translators: %s = translation of "NaSplatky" with logo */
                printf(__('Continue via %s', 'na-splatky-tb'), $nasplatky_logotext);
            ?>
		</button>
	</div>
<?php endif; ?>

<?php if (!empty($warning_text)) : ?>
	<div class="na-splatky-tb-modal__warning">
		<?php echo esc_html($warning_text); ?>
	</div>
<?php endif; ?>

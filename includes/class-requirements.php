<?php

namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

class Requirements
{
    const MIN_WP = '5';
    const MIN_WC = '4.6';

    public static function check()
    {
        global $wp_version;
        if (empty($wp_version) || version_compare($wp_version, static::MIN_WP, '<')) {
            throw new Requirement_Not_Met_Exception('WordPress >= ' . static::MIN_WP);
        }
        if (! function_exists('WC') || empty(WC()->version) || version_compare(WC()->version, static::MIN_WC, '<')) {
            throw new Requirement_Not_Met_Exception('WooCommerce >= ' . static::MIN_WC);
        }
    }

    public static function report(Requirement_Not_Met_Exception $e)
    {
        add_action('admin_notices', function () use ($e) {
            ?>

			<div class="message error">
			<p><?php printf(
                /* translators: %1$s = formatted string "Na splatkyTB", %2$s = unmet plugin requirement */
                __('Plugin Tatra banka %1$s is enabled but does nothing, because it requires %2$s to work correctly.', 'na-splatky-tb'),
                PRODUCT_NAME,
                $e->getMessage()
            ); ?></p>
			</div>
			<?php
        });
    }
}

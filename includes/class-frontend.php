<?php

namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

class Frontend
{
    public static function init($file)
    {
        // load assets
        add_action('wp_enqueue_scripts', function () {
            if (Plugin::is_gateway_disabled()) {
                // do not enqueue anything when plugin enabled but gateway disabled
                return;
            }

            if (! is_singular('product') && ! is_checkout()) {
                // do not enqueue on pages where it is not needed
                return;
            }

            $plugin_url = plugin_dir_url(dirname(__FILE__));
            // $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
            $suffix = '';

            wp_enqueue_style(Plugin::SLUG, $plugin_url . 'dist/css/main' . $suffix . '.css', [], Plugin::VERSION);
            wp_enqueue_script(Plugin::SLUG, $plugin_url . 'dist/js/main' . $suffix . '.js', ['jquery', 'woocommerce'], Plugin::VERSION, true);

            $l10n = [
                'ajax_url' => WC()->ajax_url(),
                'wc_ajax_url' => \WC_AJAX::get_endpoint('%%endpoint%%'),
                'nonce' => wp_create_nonce('ajax-nonce')
            ];
            wp_localize_script(Plugin::SLUG, 'wc_tb_nasplatky_params', $l10n);
        });

        // Wrap gateway icon to button
        add_filter('woocommerce_gateway_icon', function ($icon, $id) {
            if ($id == Plugin::SLUG) {
                $icon = '<span id="nasplatky" type="button" class="na-splatky-tb-btn na-splatky-tb-btn--checkout js-open-na-splatky-tb-modal" data-modal="modal-one">' . $icon . '</span>';
            }

            return $icon;
        }, 10, 2);

        add_filter('woocommerce_checkout_after_order_review', function () {
            if (Plugin::is_gateway_disabled()) {
                // do not output modal when plugin enabled but gateway disabled
                return;
            }
            echo esc_html(static::get_checkout_nasplatky_modal());
        });

        // Add button and modal after add_to_cart button
        add_filter('woocommerce_after_add_to_cart_button', function () {
            if (Plugin::is_gateway_disabled()) {
                // do not output button+modal when plugin enabled but gateway disabled
                return;
            }
            $logotext = '<span class="nasplatky-logotext">' . PRODUCT_NAME . '</span>';

            echo '
			<div class="na-splatky-tb-btn-wrapper">
				<button id="nasplatky" type="button" class="na-splatky-tb-btn na-splatky-tb-btn--product js-open-na-splatky-tb-modal" data-modal="modal-one">'
                    /* translators: %s = translation of "NaSplatky" with logo */
                     . sprintf(esc_html__('Compute %s', 'na-splatky-tb'), $logotext) .
                '</button>
			</div>
			';

            echo esc_html(static::get_product_nasplatky_modal());
        }, 35);

        // Redirect to cart after submiting calculator in single product
        add_filter('woocommerce_add_to_cart_redirect', function ($url, $adding_to_cart) {
            $redirect = isset($_POST['wc_tb_nasplatky_cart_redirect']) && ! empty($_POST['wc_tb_nasplatky_cart_redirect']) ? true : false;
            if ($adding_to_cart && $redirect) {
                wp_safe_redirect(wc_get_cart_url());
            }

            return $url;
        }, 10, 2);

        // Add AJAX actions
        add_action('wc_ajax_get_wc_tb_refreshed_modal', array( __CLASS__, 'get_wc_tb_refreshed_modal' ));
        add_action('wc_ajax_set_wc_tb_loan_duration_session', array( __CLASS__, 'set_wc_tb_loan_duration_session' ));
    }

    public static function set_wc_tb_loan_duration_session()
    {
        $nonce = isset($_POST['nonce']) && !empty($_POST['nonce']) ? sanitize_key($_POST['nonce']) : '';

        if (! wp_verify_nonce($nonce, 'ajax-nonce')) {
            Plugin::get_gateway()->webikon_logger()->debug('loan duration invalid nonce', $nonce);
            wp_send_json_error('Invalid nonce');
        }

        // Start session if not exists yet
        if (! WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }

        $loan_duration = !empty($_POST['loan_duration_choice']) ? sanitize_text_field($_POST['loan_duration_choice']) : false;

        // Does nothing if session is initialized already
        wc_load_cart();
        // Save choice to WC session
        WC()->session->set('wc_tb_nasplatky_preferred_loan_duration', $loan_duration);
        Plugin::get_gateway()->webikon_logger()->debug('kalkulacka saving checked_choice', $loan_duration);
        // Also set payment method to Nasplatky
        WC()->session->set('chosen_payment_method', Plugin::SLUG);

        wp_send_json_success('Choice saved');
    }

    public static function get_wc_tb_refreshed_modal()
    {
        $nonce = isset($_GET['nonce']) && !empty($_GET['nonce']) ? sanitize_key($_GET['nonce']) : '';

        if (! wp_verify_nonce($nonce, 'ajax-nonce')) {
            Plugin::get_gateway()->webikon_logger()->debug('modal refresh invalid nonce', $_GET['nonce']);
            wp_send_json_error('Invalid nonce');
        }

        $product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
        $quantity = isset($_GET['quantity']) ? (int) $_GET['quantity'] : 0;
        $variation_id = isset($_GET['variation_id']) ? (int) $_GET['variation_id'] : 0;

        // Defaults
        $product_price = 0;
        $max_limit = 2000;
        $min_limit = 100;
        $warning_text = '';
        $product = false;

        // Handle single product
        if ($product_id) {
            $product = wc_get_product($product_id);
            $product_price = wc_get_price_to_display($product);

            // Handle variable product
            if ($product->is_type('variable') && $variation_id) {
                $variation = $product->get_available_variation($variation_id);
                $product_price = isset($variation['display_price']) && !empty($variation['display_price']) ? (float) $variation['display'] : 0;
            }

            if ($quantity) {
                $product_price = $product_price * $quantity;
            }
        }

        // Does nothing if session is initialized already
        wc_load_cart();
        $checked_choice = WC()->session->get('wc_tb_nasplatky_preferred_loan_duration');
        //Plugin::get_gateway()->webikon_logger()->debug('kalkulacka loading checked_choice', $checked_choice);

        $cart_totals = WC()->cart->get_total('raw');
        $all_totals = $cart_totals + $product_price;
        $allow_continue = $all_totals > $min_limit && $all_totals < $max_limit;

        if ($all_totals < $min_limit) {
            /* translators: %s = formatted minimum purchase value */
            $warning_text = sprintf(__('The value of your purchase is below the minimum value %s.', 'na-splatky-tb'), esc_html(Plugin::format_price($min_limit, ['decimals' => 0])));
        } elseif ($all_totals > $max_limit) {
            /* translators: %s = formatted maximum purchase value */
            $warning_text = sprintf(__('The value of your purchase is above the maximum value %s.', 'na-splatky-tb'), esc_html(Plugin::format_price($max_limit, ['decimals' => 0])));
        }

        // Get data from API
        $client = new Client(
            Plugin::get_gateway()->get_option('client_id'),
            Plugin::get_gateway()->get_option('client_secret'),
            Plugin::get_gateway()->webikon_logger()
        );
        $data = $allow_continue
            ? $client->get_precalculation($all_totals)
            : null;

        $template_data = [
            'data' => $data,
            'product' => $product,
            'all_totals' => $all_totals,
            'checked_choice' => $checked_choice,
            'allow_continue' => $allow_continue,
            'warning_text' => $warning_text,
        ];

        ob_start();

        echo esc_html(
            wc_get_template(
                'nasplatky-modal-boxes.php',
                $template_data,
                '',
                plugin_dir_path(dirname(__FILE__)) . 'templates/'
            )
        );

        $modal_html = ob_get_clean();

        $data = [
            'modal' => $modal_html,
        ];

        wp_send_json($data);
    }


    public static function get_checkout_nasplatky_modal()
    {
        $cart_totals = WC()->cart->get_total('raw');

        return wc_get_template(
            'nasplatky-modal.php',
            ['cart_totals' => $cart_totals],
            '',
            plugin_dir_path(dirname(__FILE__)) . 'templates/'
        );
    }

    public static function get_product_nasplatky_modal()
    {
        return wc_get_template(
            'nasplatky-modal.php',
            [],
            '',
            plugin_dir_path(dirname(__FILE__)) . 'templates/'
        );
    }
}

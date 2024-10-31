<?php

namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

use MatejKravjar\BuildingBlocks\WoocommerceLogger_1a\WoocommerceLogger;
use MatejKravjar\BuildingBlocks\PartialLogger_1a\PartialLogger;

class Gateway extends \WC_Payment_Gateway
{
    /**
     * @var object
     */
    private $webikon_logger_instance;

    /**
     * @constructor
     */
    public function __construct($plugin_file)
    {
        $this->id = 'na-splatky-tb';
        /* translators: %s = string "Na splatkyTB" */
        $this->method_title = sprintf(__('Tatra banka %s', 'na-splatky-tb'), 'Na splátkyTB');
        /* translators: %s = string "Na splatkyTB" */
        $this->method_description = sprintf(__('Installment based buy via loan from Tatra banka using %s service', 'na-splatky-tb'), 'Na splátkyTB');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->icon = plugins_url('assets/nasplatky.svg?ver=' . Plugin::VERSION, $plugin_file);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ]);
    }

    /**
     * Plugin logger.
     */
    public function webikon_logger()
    {
        if ($this->webikon_logger_instance === null) {
            $this->webikon_logger_instance = new PartialLogger(new WoocommerceLogger($this->id), [
                'info' => true,
                'debug' => 'yes' === $this->get_option('debug_mode'),
            ]);
        }

        return $this->webikon_logger_instance;
    }

    /**
     * Initialize setting form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'na-splatky-tb'),
                'type' => 'checkbox',
                /* translators: %s: payment method title */
                'label' => sprintf(__('Enable %s Payment', 'na-splatky-tb'), $this->method_title),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'na-splatky-tb'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'na-splatky-tb'),
                'default' => $this->method_title,
            ],
            'description' => [
                'title' => __('Gateway Description', 'na-splatky-tb'),
                'type' => 'textarea',
                'description' => __('Gateway description when selected during checkout.', 'na-splatky-tb'),
                'default' => '',
            ],
            'client_id' => [
                'title' => __('API key', 'na-splatky-tb'),
                'type' => 'text',
                'description' => __('Client ID for communication with API.', 'na-splatky-tb'),
                'default' => '',

            ],
            'client_secret' => [
                'title' => __('Application secret', 'na-splatky-tb'),
                'type' => 'password',
                'description' => __('Client secret for communication with API.', 'na-splatky-tb'),
                'default' => '',
            ],
            'sandbox_mode' => [
                'title' => __('Sandbox Mode', 'na-splatky-tb'),
                'type' => 'checkbox',
                'description' => __('Enable sandbox mode.', 'na-splatky-tb'),
                'default' => 'no',
            ],
            'debug_mode' => [
                'title' => __('Debug Mode', 'na-splatky-tb'),
                'type' => 'checkbox',
                'description' => __('Write extensive information into plugin log.', 'na-splatky-tb'),
                'default' => 'no',
            ],
        ];
    }

    /**
     * Render admin options.
     */
    public function admin_options()
    {
        $email = 'info@platobnebrany.sk';
        echo '<p>', sprintf(
            /* translators: %s: contact email */
            __('Do you have a technical issue with the plugin? Contact us at %s', 'na-splatky-tb'),
            '<a href="' . esc_attr("mailto:$email?" . http_build_query([
                'subject' => __('Support request for plugin "%1$s" at domain "%2$s"', $this->method_title, home_url()),
            ], '', '&')) . '">' . esc_html($email) . '</a>'
        ), '</p>';
        parent::admin_options();
    }

    /**
     * Process payment.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            $this->webikon_logger()->info('Fetching the order has failed', compact('order_id', 'order'));
            return;
        }

        $order_currency = $order->get_currency();
        if ('EUR' !== $order_currency) {
            $this->webikon_logger()->info('Order currency is not supported', compact('order_id', 'order_currency'));
            throw new \RuntimeException(__('Payment gateway does not support selected currency', 'na-splatky-tb'));
        }

        $order->update_meta_data('_webikon_order_number_' . $this->id, $order->get_order_number());

        $client = new Client(
            $this->get_option('client_id'),
            $this->get_option('client_secret'),
            $this->webikon_logger()
        );

        $preferred_loan_duration = WC()->session->wc_tb_nasplatky_preferred_loan_duration;
        list(, $preferred_loan_duration) = explode(':', $preferred_loan_duration);

        $application_url = $client->create_application($order, $preferred_loan_duration);

        if ($application_url) {
            $this->webikon_logger()->debug('Redirecting to external application url', compact('application_url'));
            // saving order_number and application_id and application_status meta
            $order->save();
            WC()->session->set('wc_tb_nasplatky_preferred_loan_duration', false);
            return [ 'result' => 'success', 'redirect' => $application_url ];
        }

        throw new \RuntimeException(__('Unable to proceed with redirect to application', 'na-splatky-tb'));
    }
}

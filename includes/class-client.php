<?php

namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

class Client
{
    const HOST = 'https://api.tatrabanka.sk';

    private $client_id;
    private $client_secret;
    private $logger;
    private $mode;

    public function __construct($client_id, $client_secret, $logger)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->logger = $logger;
        $this->mode = Plugin::get_gateway()->get_option('sandbox_mode') == 'yes' ? 'sandbox' : 'production';
        $this->logger->debug('Using mode ' . $this->mode);
    }

    public static function get_request_options($body, $content_type, $access_token)
    {
        $headers = [];
        if (null !== $content_type) {
            $headers['Content-Type'] = $content_type;
        }
        if (null !== $access_token) {
            $headers['Authorization'] = "Bearer $access_token";
        }
        $options = [
            'sslcertificates' => __DIR__ . '/TB_root.pem',
            'user-agent' => 'na-splatky-tb@' . Plugin::VERSION,
            'headers' => $headers,
        ];
        if (null !== $body) {
            $options['body'] = $body;
        }
        return $options;
    }

    public function round_number($number)
    {
        return round($number, min(2, wc_get_price_decimals()));
    }

    public function get_token()
    {
        $body = http_build_query([
            'client_id' => trim($this->client_id),
            'client_secret' => trim($this->client_secret),
            'grant_type' => 'client_credentials',
            'scope' => 'PAY_LATER',
        ], '', '&');

        $response = wp_remote_post(
            static::HOST . '/paylater/'.$this->mode.'/auth/oauth/v2/token',
            static::get_request_options(
                $body,
                'application/x-www-form-urlencoded',
                null
            )
        );

        if (is_wp_error($response)) {
            $this->logger->info('Error get_token response', $response);
            return;
        }
        $body = isset($response['body']) && !empty($response['body']) ? $response['body'] : '';

        $json = json_decode($body);
        if (! $json || empty($json->access_token)) {
            $this->logger->info('There is missing access_token in the response', esc_html($body));
            return;
        }
        return $json->access_token;
    }

    public function create_application($order, $preferred_loan_duration)
    {
        $access_token = $this->get_token();

         // Prevent woocommerce inconsistencies in calculating totals
        add_filter('wc_get_price_decimals', function () {
            return 4;
        });

        $data = [
            'order' => [
                'orderNo' => $order->get_order_number(),
                'totalAmount' => $this->round_number(floatval($order->get_total())),
                'orderItems' => array_map(function ($item) {
                    $item_name = $item->get_name();
                    $item_name = str_replace('|', ' ', $item_name);

                    if (strlen($item_name) > 255) {
                        $item_name = substr($item_name, 0, 251) . ' ...';
                    }

                    if ($item instanceof \WC_Order_Item_Coupon) {
                        $total_item_price = (floatval($item->get_discount()) + floatval($item->get_discount_tax())) * (-1);
                    } elseif ($item instanceof \WC_Order_Item_Product) {
                        $total_item_price = (floatval($item->get_subtotal()) + floatval($item->get_subtotal_tax()));
                    } else {
                        $total_item_price = (floatval($item->get_total()) + floatval($item->get_total_tax()));
                    }

                    return [
                        'quantity' => $item->get_quantity(),
                        'totalItemPrice' => $this->round_number($total_item_price),
                        'itemDetail' => [
                            'itemDetailSK' => [
                                'itemName' => $item_name,
                            ],
                        ],
                    ];
                }, array_values($order->get_items([ 'line_item', 'shipping', 'fee', 'coupon' ]))),
                'orderPaymentData' => [
                    'orderPaymentDetail' => [
                        'endToEndId' => strval($order->get_id()),
                    ]
                ],
            ],
            'applicant' => [
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                // no need to handle unicode, those characters do not pass WC_Validation::is_phone()
                'phone' => preg_replace('/[^0-9+]/', '', $order->get_billing_phone()),
            ],
        ];
		
        if ($preferred_loan_duration) {
            if (is_numeric($preferred_loan_duration)) {
                $data['order']['preferredLoanDuration'] = intval($preferred_loan_duration);
            } else {
                $this->logger->debug('Invalid preferred loan duration (ignored)', compact('preferred_loan_duration'));
            }
        }
        $body = json_encode([
            'financeApplication' => $data,
            'redirectUrl' => home_url() . '/wc-api/na-splatky-tb-redirect',
            'webhookUrl' => home_url() . '/wc-api/na-splatky-tb-webhook',
        ]);

        $this->logger->debug('Create application request body', $body);

        $options = static::get_request_options(
            $body,
            'application/json',
            $access_token
        );
        $options['headers']['X-Request-ID'] = wp_generate_uuid4();

        $response = wp_remote_post(
            static::HOST . '/paylater/'.$this->mode.'/v1/applications',
            $options
        );

        if (is_wp_error($response)) {
            $this->logger->info('Error create_application response', $response);
            return;
        }

        $body = isset($response['body']) && !empty($response['body']) ? $response['body'] : '';
        $json = json_decode($body);
        if (! $json) {
            $this->logger->info('Unable to decode create_application JSON response', $body);
            return;
        }
        if (! empty($json->errorCode)) {
            switch ($json->errorCode) {
            case 'TOT_AMNT_LOW':
                throw new \RuntimeException(sprintf(
                    /* translators: %1$s = minimal order amount, %2$s = formatted string "Na splatkyTB" */
                    __('The order total is too low (below %1$s) for purchase through Tatra banka %2$s', 'na-splatky-tb'),
                    wc_price(100, 'EUR'),
                    PRODUCT_NAME
                ));
            case 'TOT_AMNT_HIGH':
                throw new \RuntimeException(sprintf(
                    /* translators: %1$s = maximal order amount, %2$s = formatted string "Na splatkyTB" */
                    __('The order total is too high (above %s) for purchase through Tatra banka %2$s', 'na-splatky-tb'),
                    wc_price(2000, 'EUR'),
                    PRODUCT_NAME
                ));
            case 'INVALID_PARAMETER':
                if (! empty($json->errorDescription)) {
                    if (0 === strpos($json->errorDescription, 'Invalid input parameters, financeApplication.applicant.phone: does not match the regex pattern ')) {
                        throw new \RuntimeException(sprintf(
                            /* translators: %s = example number in international format */
                            __('Please, provide phone number in international format, e.g. %s (spaces are not required)', 'na-splatky-tb'),
                            '+421 987 654 321'
                        ));
                    }
                }
                break;
            }
            $this->logger->info('Response contains unknown error code', $body);
            return;
        }
        if (empty($json->applicationProcessUrl)) {
            $this->logger->info('Missing applicationProcessUrl in the response', $body);
            return;
        }
        if (empty($json->applicationId)) {
            $this->logger->info('Missing applicationId in the response', $body);
            return;
        }
        $order->update_meta_data('na-splatky-tb-application-id', $json->applicationId);
        $order->update_meta_data('na-splatky-tb-application-status', 'NEW');
        return $json->applicationProcessUrl;
    }

    public function get_application_status($application_id)
    {
        $access_token = $this->get_token();

        if (empty($application_id) || preg_match('/[^0-9a-f-]/', $application_id)) {
            $this->logger->info('Provided application ID is empty or contains invalid characters', $application_id);
            return;
        }

        $url = static::HOST . '/paylater/'.$this->mode.'/v1/applications/' . $application_id . '/status';

        $this->logger->debug('Create get application status request url', $url);
        $options = static::get_request_options(
            null,
            null,
            $access_token
        );
        $options['headers']['X-Request-ID'] = wp_generate_uuid4();
        $response = wp_remote_get($url, $options);

        $body = isset($response['body']) && !empty($response['body']) ? $response['body'] : '';
        if (is_wp_error($response)) {
            $this->logger->info('Error get_application_status response', $response);
            return;
        }
        $this->logger->debug('Dump of get_application_status response', [
            'code' => wp_remote_retrieve_response_code($response),
            'body' => wp_remote_retrieve_body($response),
            'headers' => wp_remote_retrieve_headers($response),
        ]);
        $json = json_decode($body);
        if (! $json) {
            $this->logger->info('Unable to decode get_application_status JSON response', $body);
            return;
        }
        if (empty($json->applicationStatus)) {
            $this->logger->info('Missing applicationStatus in the response', $body);
            return;
        }
        return $json->applicationStatus;
    }

    public function get_precalculation($amount)
    {
        $access_token = $this->get_token();
        $body = json_encode([ 'loanAmount' => floatval(number_format($amount, 2))]);
        $options = static::get_request_options(
            $body,
            'application/json',
            $access_token
        );
        $options['headers']['X-Request-ID'] = wp_generate_uuid4();
        $options['method'] = 'PUT';

        $response = wp_remote_post(
            static::HOST . '/paylater/'.$this->mode.'/v1/applications/precalculation',
            $options
        );

        if (is_wp_error($response)) {
            $this->logger->info('Error get_precalculation response', $response);
            return;
        }

        $body = isset($response['body']) && !empty($response['body']) ? $response['body'] : '';
        $json = json_decode($body);
        if (! $json || ! is_array($json) || count($json) < 3) {
            $this->logger->info('Precalculation is not an array of at least 3 elements', $body);
            return;
        }
        //$this->logger->debug( 'Precalculation items count', count( $json ) );
        $pref = array_values(array_filter($json, function ($option) {
            return ! empty($option->Preference);
        }));
        if (count($pref) < 3) {
            $this->logger->debug('Invalid precalculation data: less than 3 options');
            return;
        }
        if ($pref[0]->MainPreference || ! $pref[1]->MainPreference || $pref[2]->MainPreference) {
            $this->logger->debug('Invalid precalculation data: main preference mask 010 is not met', $pref);
            return;
        }
        return array_slice($pref, 0, 3);
    }
}

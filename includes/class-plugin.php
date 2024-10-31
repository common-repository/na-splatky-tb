<?php

namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

class Plugin {
	const SLUG = 'na-splatky-tb';
	const PRECALCULATION = self::SLUG . '-precalculation';
	const PRECALCULATION_NONCE = self::PRECALCULATION . '-nonce';
	const VERSION = '1.0.3';

	static $file;
	static $gateway;

	public static function get_gateway() {
		if ( ! static::$gateway ) {
			static::$gateway = new Gateway( static::$file );
		}
		return static::$gateway;
	}

	public static function is_gateway_disabled() {
		return 'yes' !== static::get_gateway()->get_option('enabled');
	}

	public static function run( $file ) {
		static::$file = $file;

		add_action( 'init', [ __CLASS__, 'init' ] );
	}

	public static function init() {
		try {
			load_plugin_textdomain( Plugin::SLUG, false, dirname( plugin_basename( static::$file ) ) . '/languages' );
			Requirements::check();
		}
		catch( Requirement_Not_Met_Exception $e ) {
			Requirements::report( $e );
			return;
		}

		// register the gateway into WooCommerce
		add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
			$gateways[] = static::get_gateway();
			return $gateways;
		} );

		add_filter( 'woocommerce_thankyou_order_received_text', function( $text, $order ) {
			if ( $order && $order->get_payment_method() === static::get_gateway()->id ) {
				$retry_payment_url = home_url() . '/wc-api/na-splatky-tb-retry?' . http_build_query([
					'order_id' => $order->get_id(),
					'nonce' => wp_create_nonce( static::SLUG . '-retry-nonce' ),
				], '', '&');
				$pick = esc_html__( 'Pick one of the options to complete the purchase.', 'na-splatky-tb' )
					. '</p><p>'
					. '<a href="' . esc_attr( $retry_payment_url ) . '">'
					/* translators: %s = formatted string "Na splatkyTB" */
					. sprintf( esc_html__( 'Retry payment with Tatra banka %s', 'na-splatky-tb' ), PRODUCT_NAME )
					. '</a>'
					. '</p><p>'
					. '<a href="' . esc_attr( $order->get_checkout_payment_url() ) . '">'
					. esc_html__( 'Select another payment method', 'na-splatky-tb' )
					. '</a>';
				$status = $order->get_meta( 'na-splatky-tb-application-status' );
				switch ( $status ) {
					case '': // backwards compatibility while developing
					case 'NEW':
						/* translators: %s = formatted string "Na splatkyTB" */
						$text = '<p>' . sprintf( esc_html__( 'It seems, that you did not finish the whole Tatra banka %s process.', 'na-splatky-tb' ), PRODUCT_NAME ) . ' ' . $pick . '</p>';
						break;
					case 'CUSTOMER_CREATION_IN_PROGRESS':
					case 'LOAN_APPLICATION_IN_PROGRESS':
						$text = '<p>' . esc_html__( 'We are now waiting for updates for your loan application status. You should receive any updates via email within 24 hours.', 'na-splatky-tb' ) . '</p>'
							. '<p>' . esc_html__( 'If you want, you may refresh this page to see any updates now.', 'na-splatky-tb' ) . '</p>'
							. '<p><a href="javascript:window.location.reload()">' . esc_html__( 'Refresh the page', 'na-splatky-tb' ) . '</a></p>';
						break;
					case 'LOAN_APPLICATION_FINISHED':
					case 'LOAN_DISBURSED':
						$text = '<p>' . esc_html__( 'Congratulations, your loan application was approved and order is now paid.', 'na-splatky-tb' ) . '</p>';
						break;
					case 'EXPIRED':
					case 'CANCELED': // single L is intentional
						$text = '<p>' . esc_html__( 'We are sorry, but the payment method chosen for your order could not be processed.', 'na-splatky-tb' ) . ' ' . $pick . '</p>';
						break;
					default:
						static::get_gateway()->webikon_logger()->debug('Unknown status for order #' . $order->get_id(), $status );
						break;
				}
			}
			return $text;
		}, 10, 2 );

		// register retry handler
		add_action( 'woocommerce_api_' . static::SLUG . '-retry', function() {
			$nonce = isset($_GET['nonce']) && !empty($_GET['nonce']) ? sanitize_key($_GET['nonce']) : '';

			if ( ! wp_verify_nonce( $nonce, static::SLUG . '-retry-nonce' ) ) {
				wp_die( esc_html__( 'The retry payment link has expired. Please go back and refresh the page or try ordering again.', 'na-splatky-tb' ) );
			}
			$order_id = isset($_GET['order_id']) ? intval( $_GET['order_id'] ) : 0;
			static::get_gateway()->webikon_logger()->debug('Manually processing payment for order', $order_id );
			$process = static::get_gateway()->process_payment( $order_id );
			if ( isset( $process['result'], $process['redirect'] ) && 'success' === $process['result'] ) {
				// reinstate order ID awaiting payment for the second/third/... return
				WC()->session->set( 'order_awaiting_payment', $order_id );
				wp_redirect( $process['redirect'] );
				exit;
			}
			wp_die( sprintf(
				esc_html__( 'The retry payment process has failed for order %s. Please try ordering again.', 'na-splatky-tb' ),
				"#$order_id"
			) );
		} );

		// register redirect handler
		add_action( 'woocommerce_api_' . static::SLUG . '-redirect', function() {
			static::get_gateway()->webikon_logger()->debug('Received hit to redirect endpoint', [
				'method' => isset($_SERVER['REQUEST_METHOD']) && !empty($_SERVER['REQUEST_METHOD']) ? sanitize_text_field( $_SERVER['REQUEST_METHOD'] ) : '',
				'data' => array_sanitize_text_fields((array) $_GET) + array_sanitize_text_fields((array) $_POST),
				'body' => file_get_contents( 'php://input' ),
			] );
			// find original order ID in woocommerce session
			$order_id = WC()->session->order_awaiting_payment;
			static::get_gateway()->webikon_logger()->debug('Found order awaiting payment', $order_id );
			$order = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order ) {
				static::get_gateway()->webikon_logger()->debug('Redirecting to order', $order->get_id() );
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
			wp_die( sprintf(
				esc_html__( 'We cannot find any order you purchased via Tatra banka %s, if this is a mistake, please contact us to resolve this issue.', 'na-splatky-tb' ),
				PRODUCT_NAME
			) . '<br><br><a href="' . esc_attr( home_url() ) . '">' . esc_html__( 'Return to homepage', 'na-splatky-tb' ) . '</a>' );
		} );

		// register webhook handler
		add_action( 'woocommerce_api_' . static::SLUG . '-webhook', function() {
			$body = file_get_contents( 'php://input' );
			static::get_gateway()->webikon_logger()->debug('Received hit to webhook endpoint', [
				'method' => isset($_SERVER['REQUEST_METHOD']) && !empty($_SERVER['REQUEST_METHOD']) ? sanitize_text_field( $_SERVER['REQUEST_METHOD'] ) : '',
				'data' => array_sanitize_text_fields((array) $_GET) + array_sanitize_text_fields((array) $_POST),
				'body' => $body,
			] );
			$json = json_decode( $body );
			if ( ! isset( $json->events, $json->events->payLaterStatusEvents, $json->events->payLaterStatusEvents->financeApplicationId ) ) {
				static::get_gateway()->webikon_logger()->debug('Webhook data are in unknown format', $json );
				exit;
			}
			$application_id = strval( $json->events->payLaterStatusEvents->financeApplicationId );
			$found = wc_get_orders( [
				'limit' => 1,
				'meta_key' => 'na-splatky-tb-application-id',
				'meta_value' => $application_id,
			] );
			if ( ! isset( $found, $found[0] ) || ! ( $found[0] instanceof \WC_Order ) ) {
				static::get_gateway()->webikon_logger()->info('Order referenced by webhook was not found', $found );
				exit;
			}
			$order = $found[0];
			static::get_gateway()->webikon_logger()->debug('Application id belongs to order #' . $order->get_id(), $order );
			$client = new Client(
				static::get_gateway()->get_option( 'client_id' ),
				static::get_gateway()->get_option( 'client_secret' ),
				static::get_gateway()->webikon_logger()
			);
			$status = $client->get_application_status( $application_id );
			if ( $status ) {
				static::get_gateway()->webikon_logger()->debug('Got application status for order #'. $order->get_id(), $status );
				$previous_status = $order->get_meta( 'na-splatky-tb-application-status' );
				if ( $status === $previous_status ) {
					static::get_gateway()->webikon_logger()->debug('Ignoring same status as application already has; order #' . $order->get_id(), $status );
				}
				else {
					$order->update_meta_data( 'na-splatky-tb-application-status', $status );
					$order->save();
					// filter order note author => instead of default WooCommerce
					// (which is not displayed by default) we set it to string
					// displaying plugin slug and version
					$filter = function( $note_data ) {
						return [
							'comment_author' => static::SLUG . '@' . static::VERSION,
							'comment_email' => '',
						] + $note_data;
					};

					add_filter('woocommerce_new_order_note_data', $filter, 1000);

					$order->add_order_note('Na splátkyTB: ' . $status);

					switch ($status && !in_array($order->get_status(), ['completed', 'refunded', 'cancelled'])) {
						case 'NEW':
							// do nothing
							break;
						case 'CUSTOMER_CREATION_IN_PROGRESS':
						case 'LOAN_APPLICATION_IN_PROGRESS':
							$order->update_status('on-hold');
							break;
						case 'LOAN_APPLICATION_FINISHED':
						case 'LOAN_DISBURSED':
							$order->payment_complete();
							break;
						case 'CANCELED': // single L is intentional
						case 'EXPIRED':
							$order->update_status('failed');
							break;
						default:
							static::get_gateway()->webikon_logger()->debug('Unknown status for order #' . $order->get_id(), $status);
							break;
					}

					remove_filter('woocommerce_new_order_note_data', $filter, 1000);
				}
			}
			exit;
		}, 10, 0 );

		add_action( 'wp_ajax_' . static::PRECALCULATION, [ __CLASS__, 'handle_precalculation' ] );
		add_action( 'wp_ajax_nopriv_' . static::PRECALCULATION, [ __CLASS__, 'handle_precalculation' ] );

		// add link to settings page
		add_action( 'admin_init', function() {
			add_filter( 'plugin_action_links_' . plugin_basename( static::$file ), function( $links ) {
				$admin_url = admin_url( add_query_arg( [
					'page' => 'wc-settings',
					'tab' => 'checkout',
					'section' => static::get_gateway()->id,
				], 'admin.php' ) );
				return array_merge(
					[ 'settings' => '<a href="'. esc_attr( $admin_url ) . '">' . esc_html__( 'Settings', 'na-splatky-tb' ) . '</a>', ],
					$links
				);
			} );
		}, 10, 0 );

		Frontend::init( static::$file );
	}

	public static function handle_precalculation() {
		if ( ! check_ajax_referer( static::PRECALCULATION, static::PRECALCULATION_NONCE, false ) ) {
			wp_send_json_error(	'Invalid nonce' );
		}
		try {
			$client = new Client(
				static::get_gateway()->get_option( 'client_id' ),
				static::get_gateway()->get_option( 'client_secret' ),
				static::get_gateway()->webikon_logger()
			);

			$amount = !empty($_POST['amount']) ? (float) $_POST['amount'] : 0;
			$json = $client->get_precalculation( $amount );
			wp_send_json_success( $json );
		}
		catch ( \Throwable $e ) {
			static::get_gateway()->webikon_logger()->debug( 'Precalculation failed', $e->getMessage() );
			wp_send_json_error(	'There was an error getting precalculation data' );
		}
	}

	public static function format_price( $amount, array $options = [] ) {
		$options += [ 'currency' => 'EUR', 'decimals' => 2 ];
		$value = number_format( floatval( $amount ), $options['decimals'], ',', ' ' );
		$currency = $options['currency'] ? ' ' . $options['currency'] : '';
		return $value . $currency;
	}
}

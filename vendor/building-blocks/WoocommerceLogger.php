<?php

namespace MatejKravjar\BuildingBlocks\WoocommerceLogger_1a;

use MatejKravjar\BuildingBlocks\SimpleLogger_1a\SimpleLogger;

/**
 * Simple logging class for WooCommerce.
 * Implements log() with 2 possible levels: 'info' and 'debug'
 * and respective methods for these levels: info() and debug()
 */
class WoocommerceLogger extends SimpleLogger {
	/**
	 * Constructor.
	 * @param string log handle (file name prefix)
	 */
	public function __construct( $handle ) {
		parent::__construct( wc_get_log_file_path( $handle ) );
	}

	/**
	 * Get current date+time in timezone from WordPress settings.
	 * @internal
	 */
	public function get_current_datetime() {
		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), 'Y-m-d H:i:s O' );
	}
}

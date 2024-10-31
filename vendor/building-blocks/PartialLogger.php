<?php

namespace MatejKravjar\BuildingBlocks\PartialLogger_1a;

/**
 * Send to parent logger only specific levels of events.
 */
class PartialLogger {
	/**
	 * @var object
	 */
	protected $logger;

	/**
	 * @var array
	 */
	protected $levels;

	/**
	 * Constructor.
	 * @param string parent logger
	 */
	public function __construct( $logger, $levels = [] ) {
		$this->logger = $logger;
		$this->levels = $levels;
	}

	/**
	 * Send event to parent logger depending on set levels
	 */
	public function log( $level, $message, $variable = null ) {
		if ( ! empty( $this->levels[ $level ] ) ) {
			$this->logger->log( $level, $message, $variable );
		}
	}

	/**
	 * Handle the shorthand methods for concrete log levels
	 * @param string method name
	 * @param array call arguments
	 * @return void
	 */
	public function __call( $name, $args ) {
		switch ( $name ) {
			case 'info':
			case 'debug':
				array_unshift( $args, $name );
				call_user_func_array( [ $this, 'log' ], $args );
				return;
			default:
				throw new \RuntimeException(
					'Call to undefined method ' . get_class( $this ) . '::' . $name . '()'
				);
		}
	}
}

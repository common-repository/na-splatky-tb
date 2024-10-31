<?php

namespace MatejKravjar\BuildingBlocks\SimpleLogger_1a;

/**
 * Simple logging class implementing subset of \Psr3\LoggerInterface.
 * Implements log() with 2 possible levels: 'info' and 'debug'
 * and respective methods for these levels: info() and debug()
 */
class SimpleLogger {
	const LEVEL_INFO = 'info';
	const LEVEL_DEBUG = 'debug';

	/** @var string */
	protected $file;

	/**
	 * Constructor.
	 * @param string full path to log file
	 */
	public function __construct( $file ) {
		$this->file = $file;
	}

	/**
	 * Writes log message to file (debug level prefixes message with 'DEBUG ').
	 * @param string level---either 'info' or 'debug'
	 * @param string message
	 * @param mixed variable to dump (optional)
	 * @return void
	 * @throws SimpleLoggerException
	 */
	public function log( $level, $message, $variable = null ) {
		$message = trim( $message );
		if ( static::LEVEL_DEBUG === $level ) {
			$message = "DEBUG $message";
		}
		elseif ( static::LEVEL_INFO !== $level ) {
			throw new SimpleLoggerException( "Unrecognized log level '$level'; supported are 'info' and 'debug'" );
		}
		$date = $this->get_current_datetime();
		if ( null !== $variable ) {
			$message .= ': ' . static::dump( $variable );
		}
		$bytes = file_put_contents( $this->file, "[$date] $message\n", LOCK_EX | FILE_APPEND );
		if ( false === $bytes ) {
			$message = 'Writing log message into file resulted in failure';
			$error = error_get_last();
			if ( $error && isset( $error['message'] ) ) {
				$message .= ': ' . $error['message'];
			}
			throw new SimpleLoggerException( $message );
		}
	}

	/**
	 * Shorthand for log('info', $message, $variable).
	 * @param string message
	 * @param mixed variable to dump (optional)
	 * @return void
	 * @throws SimpleLoggerException
	 */
	public function info( $message, $variable = null ) {
		$this->log( static::LEVEL_INFO, $message, $variable );
	}

	/**
	 * Shorthand for log('debug', $message, $variable).
	 * @param string message
	 * @param mixed variable to dump (optional)
	 * @return void
	 * @throws SimpleLoggerException
	 */
	public function debug( $message, $variable = null ) {
		$this->log( static::LEVEL_DEBUG, $message, $variable );
	}

	/**
	 * Get current date+time.
	 * Logic can be modified by child classes
	 * @internal
	 */
	public function get_current_datetime() {
		return date( 'Y-m-d H:i:s O' );
	}

	/**
	 * Dump variable contents for use in logs.
	 * @internal
	 */
	public function dump( $variable ) {
		$type = gettype( $variable );
		switch ( $type ) {
		case 'boolean':
			return $variable ? 'true' : 'false';
		case 'integer':
			return strval( $variable );
		case 'double':
			return strval( $variable );
		case 'string':
			$variable = str_replace( [ '\\', "'" ], [ '\\\\', "\\'" ], $variable );
			return "'" . preg_replace_callback( '/[\x00-\x1f\x7f-\xff]/', function( $m ) {
				return "\\x" . bin2hex( $m[0] );
			}, $variable ) . "'";
		case 'array':
			$children = static::dump_children( $variable );
			return $children ? '[ ' . $children . ' ]' : '[]';
		case 'object':
			$children = static::dump_children( $variable );
			return get_class( $variable ) . ' ' . (
				$children ? '{ ' . $children . ' }' : '{}'
			);
		case 'resource':
		case 'resource (closed)':
			return $type;
		case 'NULL':
			return 'null';
		}
		return "??? $type ???";
	}

	/**
	 * Dump array/object children for use in logs.
	 * @internal
	 */
	public function dump_children( $children ) {
		$items = [];
		foreach ( $children as $key => $value ) {
			$items[] = static::dump( $key ) . ': ' . static::dump( $value );
		}
		return implode( ', ', $items );
	}
}

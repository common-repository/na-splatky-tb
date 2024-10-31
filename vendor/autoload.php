<?php

namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

spl_autoload_register( function( $classname ) {
	if ( 0 === strpos( $classname, 'MatejKravjar\BuildingBlocks\\' ) ) {
		$basename = substr( $classname, strrpos( $classname, '\\' ) + 1 );
		require __DIR__ . "/building-blocks/$basename.php";
	}
} );
	
<?php

/*
Plugin Name: Na splátkyTB
Description: Na splátkyTB payment gateway for WooCommerce e-shop
Author: Webikon (Matej Kravjar)
Version: 1.0.7
Author URI: https://webikon.sk
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: na-splatky-tb
Requires at least: 5.0
Tested up to: 5.9
Requires PHP: 7.0
WC requires at least: 5.0
WC tested up to: 5.4.1
*/

namespace Webikon\Woocommerce_Payment_Gateway\Tatrabanka\Nasplatky;

const MIN_PHP = '7';
const PRODUCT_NAME = 'Na splátky<sup>TB</sup>';

add_action('plugins_loaded', function () {
    // Require helpers
    require __DIR__ . "/includes/helpers.php";

    spl_autoload_register(function ($classname) {
        if (0 === strpos($classname, __NAMESPACE__ . '\\')) {
            $name = substr($classname, strrpos($classname, '\\') + 1);
            $name = str_replace('_', '-', strtolower($name));
            require __DIR__ . "/includes/class-$name.php";
        }
    });

    if (version_compare(\PHP_VERSION, MIN_PHP, '<')) {
        Requirements::report(new Requirement_Not_Met_Exception('PHP >= ' . MIN_PHP));
        return;
    }
    require __DIR__ . '/vendor/autoload.php';
    Plugin::run(__FILE__);
}, 10, 0);

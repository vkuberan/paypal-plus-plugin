<?php # -*- coding: utf-8 -*-
// phpcs:disable

/**
 * Plugin Name: PayPal PLUS for WooCommerce
 * Description: PayPal Plus - the official WordPress Plugin for WooCommerce
 * Author: Inpsyde GmbH
 * Author URI: https://inpsyde.com/
 * Version: 2.0.4
 * WC requires at least: 3.2.0
 * WC tested up to: 3.6.4
 * License: GPLv2+
 * Text Domain: woo-paypalplus
 * Domain Path: /languages/
 */

namespace WCPayPalPlus;

use Closure;

$bootstrap = Closure::bind(
    function () {

    /**
     * @return bool
     */
    function autoload()
    {
        $autoloader = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            /** @noinspection PhpIncludeInspection */
            require $autoloader;

            require_once __DIR__ . '/src/inc/functions.php';
        }

        return class_exists(PayPalPlus::class);
    }

    if (!autoload()) {
        return;
    }

    $bootstrapper = new Bootstrapper(resolve());

    add_action('plugins_loaded', [$bootstrapper, 'bootstrap'], 0);
    add_action('init', function () {
        load_plugin_textdomain('woo-paypalplus');
    });
    },
    null
);

$bootstrap();

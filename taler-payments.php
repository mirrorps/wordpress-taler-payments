<?php
/**
 * Plugin Name: Taler Payments
 * Plugin URI: https://github.com/mirrorps/wordpress-taler-payments
 * Description: The Taler Payments plugin integrates the GNU Taler payment system, enabling seamless payments and donations on any WordPress site.
 * Version: 1.2.0
 * License: GPLv2 or later
 * Author: mirrorps
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

define('TALER_PAYMENTS_VERSION', '1.2.0');

add_action('plugins_loaded', static function (): void {
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;

    $adminBootstrap = new \TalerPayments\Bootstrap\AdminBootstrap();
    $adminBootstrap->boot();

    $publicBootstrap = new \TalerPayments\Bootstrap\PublicBootstrap(
        plugin_dir_url(__FILE__),
        __DIR__
    );
    $publicBootstrap->boot();
});

/**
 * Allow the "taler" protocol in the content.
 */
add_filter('kses_allowed_protocols', function ($protocols) {
    if (!is_array($protocols)) {
        return $protocols;
    }

    if (is_admin() && !wp_doing_ajax()) {
        return $protocols;
    }

    if (!in_array('taler', $protocols, true)) {
        $protocols[] = 'taler';
    }
    return $protocols;
});
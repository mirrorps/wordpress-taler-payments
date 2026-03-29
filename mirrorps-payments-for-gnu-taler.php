<?php
/**
 * Plugin Name: mirrorps Payments for GNU Taler
 * Plugin URI: https://github.com/mirrorps/wordpress-taler-payments
 * Description: Integrates the GNU Taler payment system for payments and donations on your WordPress site. Not affiliated with the GNU Taler project.
 * Version: 1.2.1
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: mirrorps
 * Text Domain: mirrorps-payments-for-gnu-taler
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

define('TALER_PAYMENTS_VERSION', '1.2.1');

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

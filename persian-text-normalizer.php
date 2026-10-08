<?php
/**
 * Plugin Name:       Persian Text Normalizer
 * Plugin URI:        https://github.com/kamranhajhossein/persian-text-normalizer
 * Description:       Fixes Arabic «ي/ك», mixed digits and broken ZWNJs across posts, products, search, slugs and checkout forms — better search results, working phone validation and no duplicate URLs on Persian WordPress & WooCommerce sites.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kamran Hajhossein
 * Author URI:        https://kamranh.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       persian-text-normalizer
 * Domain Path:       /languages
 * WC tested up to:   10.2
 *
 * @package PersianTextNormalizer
 */

defined( 'ABSPATH' ) || exit;

define( 'PTN_VERSION', '1.0.0' );
define( 'PTN_FILE', __FILE__ );
define( 'PTN_DIR', plugin_dir_path( __FILE__ ) );

require_once PTN_DIR . 'includes/class-ptn-normalizer.php';
require_once PTN_DIR . 'includes/class-ptn-plugin.php';
require_once PTN_DIR . 'includes/class-ptn-bulk.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'persian-text-normalizer', false, dirname( plugin_basename( PTN_FILE ) ) . '/languages' );
		PTN_Plugin::instance();

		if ( is_admin() ) {
			require_once PTN_DIR . 'includes/class-ptn-admin.php';
			PTN_Admin::init();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once PTN_DIR . 'includes/class-ptn-cli.php';
			WP_CLI::add_command( 'ptn', 'PTN_CLI' );
		}
	},
	1
);

// Declare compatibility with WooCommerce High-Performance Order Storage and the block checkout.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PTN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PTN_FILE, true );
		}
	}
);

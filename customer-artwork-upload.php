<?php
/**
 * Plugin Name:       Customer Artwork Upload
 * Plugin URI:        https://github.com/dakakiki/customer-artwork-upload
 * Description:       Secure customer artwork uploads for WooCommerce products and orders.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Davor
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       customer-artwork-upload
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CAU_VERSION', '0.1.0' );
define( 'CAU_PLUGIN_FILE', __FILE__ );
define( 'CAU_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$autoload_file = CAU_PLUGIN_PATH . 'vendor/autoload.php';

if ( ! file_exists( $autoload_file ) ) {
    add_action(
        'admin_notices',
        static function (): void {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }

            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Customer Artwork Upload could not start because its Composer autoloader is missing.',
                'customer-artwork-upload'
            );
            echo '</p></div>';
        }
    );

    return;
}

require_once $autoload_file;

add_action(
    'plugins_loaded',
    static function (): void {
        \DakaKiki\CustomerArtworkUpload\Plugin::instance()->boot();
    }
);
<?php

namespace DakaKiki\CustomerArtworkUpload\Admin;

defined( 'ABSPATH' ) || exit;

final class DependencyNotice {

    public static function register(): void {
        add_action( 'admin_notices', array( __CLASS__, 'render' ) );
    }

    public static function render(): void {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Customer Artwork Upload requires WooCommerce to be installed and active.',
                'customer-artwork-upload'
            )
        );
    }
}

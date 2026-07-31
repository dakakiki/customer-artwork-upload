<?php

namespace DakaKiki\CustomerArtworkUpload\WooCommerce;

defined( 'ABSPATH' ) || exit;

final class Compatibility {

    public static function register(): void {
        add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
    }

    public static function declare_hpos_compatibility(): void {
        if ( ! class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            CAU_PLUGIN_FILE,
            true
        );
    }
}

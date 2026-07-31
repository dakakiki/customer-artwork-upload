<?php

namespace DakaKiki\CustomerArtworkUpload;

use DakaKiki\CustomerArtworkUpload\Admin\DependencyNotice;
use DakaKiki\CustomerArtworkUpload\Infrastructure\Requirements;
use DakaKiki\CustomerArtworkUpload\Admin\ProductSettings;

defined( 'ABSPATH' ) || exit;

final class Plugin {

    private static $instance = null;

    private function __construct() {
    }

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        add_action( 'init', array( $this, 'load_textdomain' ) );

        if ( ! Requirements::has_woocommerce() ) {
            DependencyNotice::register();

            return;
        }

        ProductSettings::register();

        /**
         * Fires after Customer Artwork Upload has passed its requirements check.
         *
         * @since 0.1.0
         */
        do_action( 'cau_loaded' );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'customer-artwork-upload',
            false,
            dirname( plugin_basename( CAU_PLUGIN_FILE ) ) . '/languages'
        );
    }
}

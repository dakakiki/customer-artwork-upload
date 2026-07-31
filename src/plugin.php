<?php

namespace DakaKiki\CustomerArtworkUpload;

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
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'customer-artwork-upload',
            false,
            dirname( plugin_basename( CAU_PLUGIN_FILE ) ) . '/languages'
        );
    }
}
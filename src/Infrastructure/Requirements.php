<?php

namespace DakaKiki\CustomerArtworkUpload\Infrastructure;

defined( 'ABSPATH' ) || exit;

final class Requirements {

    public static function has_woocommerce(): bool {
        return class_exists( 'WooCommerce' );
    }
}

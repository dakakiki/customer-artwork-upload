<?php

namespace DakaKiki\CustomerArtworkUpload\WooCommerce;

use DakaKiki\CustomerArtworkUpload\Frontend\ProductUploadField;
use DakaKiki\CustomerArtworkUpload\Storage\StorageInterface;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

final class CartArtwork {

    public const CART_KEY = 'cau_artwork';

    /** @var StorageInterface */
    private $storage;

    /** @var array<string, mixed>|null */
    private $pending_artwork;

    public function __construct( StorageInterface $storage ) {
        $this->storage = $storage;
    }

    public function register(): void {
        add_filter(
            'woocommerce_add_to_cart_validation',
            array( $this, 'store_validated_upload' ),
            999,
            3
        );

        add_filter(
            'woocommerce_add_cart_item_data',
            array( $this, 'add_cart_item_data' ),
            10,
            3
        );

        add_filter(
            'woocommerce_get_item_data',
            array( $this, 'display_cart_item_data' ),
            10,
            2
        );

        add_action(
            'woocommerce_checkout_create_order_line_item',
            array( $this, 'add_order_item_data' ),
            10,
            4
        );
    }

    /**
     * Store the upload only after the product field's validation has passed.
     */
    public function store_validated_upload(
        bool $passed,
        int $product_id,
        int $quantity
    ): bool {
        unset( $product_id, $quantity );

        if ( ! $passed || null !== $this->pending_artwork ) {
            return $passed;
        }

        // ProductUploadField has already validated this upload at priority 10.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = isset( $_FILES[ ProductUploadField::FIELD_NAME ] )
            ? $_FILES[ ProductUploadField::FIELD_NAME ]
            : null;

        if (
            ! is_array( $file ) ||
            ! isset( $file['error'] ) ||
            UPLOAD_ERR_NO_FILE === absint( $file['error'] )
        ) {
            return $passed;
        }

        $stored = $this->storage->store( $file );

        if ( is_wp_error( $stored ) ) {
            wc_add_notice( $stored->get_error_message(), 'error' );

            return false;
        }

        $this->pending_artwork = $stored;

        return true;
    }

    /**
     * @param array<string, mixed> $cart_item_data Existing cart data.
     * @return array<string, mixed>
     */
    public function add_cart_item_data(
        array $cart_item_data,
        int $product_id,
        int $variation_id
    ): array {
        unset( $product_id, $variation_id );

        if ( null === $this->pending_artwork ) {
            return $cart_item_data;
        }

        $cart_item_data[ self::CART_KEY ] = $this->pending_artwork;

        // Make separately uploaded artwork produce a separate cart line.
        $cart_item_data['cau_unique_key'] = wp_generate_uuid4();
        $this->pending_artwork             = null;

        return $cart_item_data;
    }

    /**
     * @param array<int, array<string, mixed>> $item_data Existing display data.
     * @param array<string, mixed>             $cart_item Cart item.
     * @return array<int, array<string, mixed>>
     */
    public function display_cart_item_data( array $item_data, array $cart_item ): array {
        $artwork = isset( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] )
            ? $cart_item[ self::CART_KEY ]
            : array();

        if ( ! empty( $artwork['original_name'] ) ) {
            $item_data[] = array(
                'key'   => __( 'Artwork', 'customer-artwork-upload' ),
                'value' => esc_html( (string) $artwork['original_name'] ),
            );
        }

        return $item_data;
    }

    /**
     * @param array<string, mixed> $values Cart item values.
     */
    public function add_order_item_data(
        WC_Order_Item_Product $item,
        string $cart_item_key,
        array $values,
        $order
    ): void {
        unset( $cart_item_key, $order );

        $artwork = isset( $values[ self::CART_KEY ] ) && is_array( $values[ self::CART_KEY ] )
            ? $values[ self::CART_KEY ]
            : array();

        if ( empty( $artwork['storage_key'] ) ) {
            return;
        }

        $item->add_meta_data( '_cau_artwork_storage_key', sanitize_file_name( (string) $artwork['storage_key'] ), true );
        $item->add_meta_data( '_cau_artwork_original_name', sanitize_file_name( (string) $artwork['original_name'] ), true );
        $item->add_meta_data( '_cau_artwork_mime_type', sanitize_mime_type( (string) $artwork['mime_type'] ), true );
        $item->add_meta_data( '_cau_artwork_size', absint( $artwork['size'] ), true );
    }
}

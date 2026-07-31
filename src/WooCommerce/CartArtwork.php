<?php

namespace DakaKiki\CustomerArtworkUpload\WooCommerce;

use DakaKiki\CustomerArtworkUpload\Frontend\ProductUploadField;
use DakaKiki\CustomerArtworkUpload\Storage\StorageInterface;
use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

final class CartArtwork {
    public const CART_KEY = 'cau_artwork';
    public const ORDER_STORAGE_KEYS_META = '_cau_artwork_storage_keys';

    /** @var StorageInterface */ private $storage;
    /** @var array<string, mixed>|null */ private $pending_artwork;

    public function __construct( StorageInterface $storage ) { $this->storage = $storage; }

    public function register(): void {
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'store_validated_upload' ), 999, 3 );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_data' ), 10, 4 );
        add_action( 'woocommerce_new_order_item', array( $this, 'mark_order_item_artwork_permanent' ), 10, 3 );
        add_action( 'woocommerce_checkout_order_created', array( $this, 'mark_order_artwork_permanent' ) );
    }

    public function store_validated_upload( bool $passed, int $product_id, int $quantity ): bool {
        unset( $product_id, $quantity );
        if ( ! $passed || null !== $this->pending_artwork ) { return $passed; }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = isset( $_FILES[ ProductUploadField::FIELD_NAME ] ) ? $_FILES[ ProductUploadField::FIELD_NAME ] : null;
        if ( ! is_array( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === absint( $file['error'] ) ) { return $passed; }
        $stored = $this->storage->store( $file );
        if ( is_wp_error( $stored ) ) { wc_add_notice( $stored->get_error_message(), 'error' ); return false; }
        $this->pending_artwork = $stored;
        return true;
    }

    public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
        unset( $product_id, $variation_id );
        if ( null === $this->pending_artwork ) { return $cart_item_data; }
        $cart_item_data[ self::CART_KEY ] = $this->pending_artwork;
        $cart_item_data['cau_unique_key'] = wp_generate_uuid4();
        $this->pending_artwork = null;
        return $cart_item_data;
    }

    public function display_cart_item_data( array $item_data, array $cart_item ): array {
        $artwork = isset( $cart_item[ self::CART_KEY ] ) && is_array( $cart_item[ self::CART_KEY ] ) ? $cart_item[ self::CART_KEY ] : array();
        if ( ! empty( $artwork['original_name'] ) ) { $item_data[] = array( 'key' => __( 'Artwork', 'customer-artwork-upload' ), 'value' => esc_html( (string) $artwork['original_name'] ) ); }
        return $item_data;
    }

    public function add_order_item_data( WC_Order_Item_Product $item, string $cart_item_key, array $values, $order ): void {
        unset( $cart_item_key );
        $artwork = isset( $values[ self::CART_KEY ] ) && is_array( $values[ self::CART_KEY ] ) ? $values[ self::CART_KEY ] : array();
        if ( empty( $artwork['storage_key'] ) ) { return; }
        $storage_key = sanitize_file_name( (string) $artwork['storage_key'] );
        if ( '' === $storage_key ) { return; }

        $item->add_meta_data( '_cau_artwork_storage_key', $storage_key, true );
        $item->add_meta_data( '_cau_artwork_original_name', sanitize_file_name( (string) $artwork['original_name'] ), true );
        $item->add_meta_data( '_cau_artwork_mime_type', sanitize_mime_type( (string) $artwork['mime_type'] ), true );
        $item->add_meta_data( '_cau_artwork_size', absint( $artwork['size'] ), true );

        if ( $order instanceof WC_Order ) {
            $storage_keys = $order->get_meta( self::ORDER_STORAGE_KEYS_META, true );
            $storage_keys = is_array( $storage_keys ) ? $storage_keys : array();
            $storage_keys[] = $storage_key;
            $order->update_meta_data( self::ORDER_STORAGE_KEYS_META, array_values( array_unique( array_filter( array_map( 'sanitize_file_name', $storage_keys ) ) ) ) );
        }

    }

    /**
     * Finalize an upload as soon as its saved order item owns the storage key.
     *
     * @param int   $item_id  Order item ID.
     * @param mixed $item     Saved order item object.
     * @param int   $order_id Order ID.
     */
    public function mark_order_item_artwork_permanent( int $item_id, $item, int $order_id ): void {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            return;
        }

        $storage_key = $item->get_meta( '_cau_artwork_storage_key', true );
        $storage_key = is_string( $storage_key ) ? sanitize_file_name( $storage_key ) : '';

        if ( '' === $storage_key ) {
            return;
        }

        if ( ! $this->storage->mark_permanent( $storage_key ) && function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->error(
                'An artwork upload could not be marked as attached to an order item.',
                array(
                    'source'        => 'customer-artwork-upload',
                    'order_id'      => $order_id,
                    'order_item_id' => $item_id,
                    'storage_key'   => $storage_key,
                )
            );
        }
    }

    public function mark_order_artwork_permanent( WC_Order $order ): void {
        $storage_keys = $order->get_meta( self::ORDER_STORAGE_KEYS_META, true );
        $storage_keys = is_array( $storage_keys ) ? $storage_keys : array();

        // Some WooCommerce checkout paths do not expose the newly written
        // order-level index here. The line-item metadata is already saved, so
        // use it as a second source before finalizing the upload.
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $item_storage_key = $item->get_meta( '_cau_artwork_storage_key', true );

            if ( is_string( $item_storage_key ) && '' !== $item_storage_key ) {
                $storage_keys[] = $item_storage_key;
            }
        }

        foreach ( array_unique( array_filter( array_map( 'sanitize_file_name', $storage_keys ) ) ) as $storage_key ) {
            if ( ! $this->storage->mark_permanent( $storage_key ) && function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->error(
                    'An artwork upload could not be marked as attached to an order.',
                    array(
                        'source'      => 'customer-artwork-upload',
                        'order_id'    => $order->get_id(),
                        'storage_key' => $storage_key,
                    )
                );
            }
        }
    }
}

<?php

namespace DakaKiki\CustomerArtworkUpload\WooCommerce;

use DakaKiki\CustomerArtworkUpload\Storage\StorageInterface;
use WC_Order;
use WC_Order_Factory;
use WC_Order_Item;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ArtworkCleanup {

    /** @var StorageInterface */
    private $storage;

    /** @var array<int, bool> */
    private $processed_orders = array();

    /** @var array<string, bool> */
    private $processed_keys = array();

    public function __construct( StorageInterface $storage ) {
        $this->storage = $storage;
    }

    public function register(): void {
        add_action( 'woocommerce_before_delete_order_item', array( $this, 'delete_order_item_artwork' ), 5, 1 );
        add_action( 'woocommerce_before_delete_order', array( $this, 'delete_order_artwork' ), 10, 2 );
        add_action( 'before_delete_post', array( $this, 'delete_legacy_order_artwork' ), 5, 2 );
    }

    public function delete_order_item_artwork( int $item_id ): void {
        $storage_key = sanitize_file_name(
            (string) wc_get_order_item_meta( $item_id, '_cau_artwork_storage_key', true )
        );

        if ( '' === $storage_key ) {
            $item = WC_Order_Factory::get_order_item( $item_id );

            if ( $item instanceof WC_Order_Item ) {
                $storage_key = sanitize_file_name(
                    (string) $item->get_meta( '_cau_artwork_storage_key', true )
                );
            }
        }

        if ( '' !== $storage_key ) {
            $this->delete_storage_key( $storage_key, $item_id );
        }
    }

    /**
     * @param int      $order_id Order ID.
     * @param WC_Order $order    Order being permanently deleted.
     */
    public function delete_order_artwork( int $order_id, $order = null ): void {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        $this->delete_artwork_for_order( $order_id, $order );
    }

    /**
     * @param int     $post_id Order post ID.
     * @param WP_Post $post    Post being deleted.
     */
    public function delete_legacy_order_artwork( int $post_id, $post ): void {
        if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, wc_get_order_types(), true ) ) {
            return;
        }

        $this->delete_artwork_for_order( $post_id, wc_get_order( $post_id ) );
    }

    /**
     * @param int            $order_id Order ID.
     * @param WC_Order|false $order    Loaded order, or false when unavailable.
     */
    private function delete_artwork_for_order( int $order_id, $order ): void {
        if ( isset( $this->processed_orders[ $order_id ] ) || ! $order instanceof WC_Order ) {
            return;
        }

        $storage_keys = $this->get_order_storage_keys( $order );

        // Retain item metadata as a fallback for orders created before the
        // order-level index was introduced.
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $storage_key = sanitize_file_name(
                (string) $item->get_meta( '_cau_artwork_storage_key', true )
            );

            if ( '' !== $storage_key ) {
                $storage_keys[] = $storage_key;
            }
        }

        $storage_keys = array_values( array_unique( $storage_keys ) );
        $all_deleted  = true;

        foreach ( $storage_keys as $storage_key ) {
            if ( ! $this->delete_storage_key( $storage_key, 0 ) ) {
                $all_deleted = false;
            }
        }

        if ( $all_deleted ) {
            $this->processed_orders[ $order_id ] = true;
        }
    }

    /** @return array<int, string> */
    private function get_order_storage_keys( WC_Order $order ): array {
        $storage_keys = $order->get_meta( CartArtwork::ORDER_STORAGE_KEYS_META, true );

        if ( ! is_array( $storage_keys ) ) {
            return array();
        }

        return array_values(
            array_unique(
                array_filter( array_map( 'sanitize_file_name', $storage_keys ) )
            )
        );
    }

    private function delete_storage_key( string $storage_key, int $item_id ): bool {
        if ( isset( $this->processed_keys[ $storage_key ] ) ) {
            return true;
        }

        $path = $this->storage->get_path( $storage_key );

        if ( '' === $path || ! file_exists( $path ) ) {
            $this->processed_keys[ $storage_key ] = true;
            return true;
        }

        if ( $this->storage->delete( $storage_key ) ) {
            $this->processed_keys[ $storage_key ] = true;
            return true;
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->error(
                sprintf(
                    'Could not delete private artwork file "%1$s"%2$s.',
                    $storage_key,
                    $item_id > 0 ? sprintf( ' for order item %d', $item_id ) : ''
                ),
                array( 'source' => 'customer-artwork-upload' )
            );
        }

        return false;
    }
}

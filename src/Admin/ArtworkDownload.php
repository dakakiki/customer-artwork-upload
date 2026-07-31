<?php

namespace DakaKiki\CustomerArtworkUpload\Admin;

use DakaKiki\CustomerArtworkUpload\Storage\StorageInterface;
use WC_Order_Factory;
use WC_Order_Item;

defined( 'ABSPATH' ) || exit;

final class ArtworkDownload {

    private const ACTION = 'cau_download_artwork';

    /** @var StorageInterface */
    private $storage;

    public function __construct( StorageInterface $storage ) {
        $this->storage = $storage;
    }

    public function register(): void {
        add_action(
            'woocommerce_after_order_itemmeta',
            array( $this, 'render_download_link' ),
            10,
            3
        );

        add_action(
            'admin_post_' . self::ACTION,
            array( $this, 'download' )
        );
    }

    /**
     * Display an authorized download link below artwork order-item metadata.
     *
     * @param int           $item_id Order item ID.
     * @param WC_Order_Item $item    Order item object.
     * @param mixed         $product Product object, when applicable.
     */
    public function render_download_link(
        int $item_id,
        WC_Order_Item $item,
        $product
    ): void {
        unset( $product );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $storage_key  = (string) $item->get_meta( '_cau_artwork_storage_key', true );
        $original_name = (string) $item->get_meta( '_cau_artwork_original_name', true );

        if ( '' === $storage_key || '' === $original_name ) {
            return;
        }

        $url = wp_nonce_url(
            add_query_arg(
                array(
                    'action'  => self::ACTION,
                    'item_id' => $item_id,
                ),
                admin_url( 'admin-post.php' )
            ),
            self::nonce_action( $item_id )
        );

        printf(
            '<p class="cau-artwork-download"><a class="button" href="%1$s">%2$s</a></p>',
            esc_url( $url ),
            esc_html__( 'Download artwork', 'customer-artwork-upload' )
        );
    }

    /**
     * Stream a protected artwork file to an authorized administrator.
     */
    public function download(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die(
                esc_html__( 'You are not allowed to download this artwork.', 'customer-artwork-upload' ),
                esc_html__( 'Forbidden', 'customer-artwork-upload' ),
                array( 'response' => 403 )
            );
        }

        $item_id = isset( $_GET['item_id'] )
            ? absint( wp_unslash( $_GET['item_id'] ) )
            : 0;

        if ( 0 === $item_id ) {
            $this->not_found();
        }

        check_admin_referer( self::nonce_action( $item_id ) );

        $item = WC_Order_Factory::get_order_item( $item_id );

        if ( ! $item instanceof WC_Order_Item || ! $item->get_order() ) {
            $this->not_found();
        }

        $storage_key   = sanitize_file_name(
            (string) $item->get_meta( '_cau_artwork_storage_key', true )
        );
        $original_name = sanitize_file_name(
            (string) $item->get_meta( '_cau_artwork_original_name', true )
        );
        $mime_type     = sanitize_mime_type(
            (string) $item->get_meta( '_cau_artwork_mime_type', true )
        );
        $path          = $this->storage->get_path( $storage_key );

        if (
            '' === $storage_key ||
            '' === $original_name ||
            '' === $path ||
            ! is_file( $path ) ||
            ! is_readable( $path )
        ) {
            $this->not_found();
        }

        $file_size = filesize( $path );

        nocache_headers();
        header( 'Content-Type: ' . ( $mime_type ?: 'application/octet-stream' ) );
        header(
            'Content-Disposition: attachment; filename="' .
            str_replace( '"', '', $original_name ) . '"'
        );
        header( 'X-Content-Type-Options: nosniff' );

        if ( false !== $file_size ) {
            header( 'Content-Length: ' . (string) $file_size );
        }

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }

    private static function nonce_action( int $item_id ): string {
        return self::ACTION . '_' . $item_id;
    }

    private function not_found(): void {
        wp_die(
            esc_html__( 'The requested artwork file could not be found.', 'customer-artwork-upload' ),
            esc_html__( 'Artwork not found', 'customer-artwork-upload' ),
            array( 'response' => 404 )
        );
    }
}

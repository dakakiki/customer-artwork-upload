<?php

namespace DakaKiki\CustomerArtworkUpload\Admin;

use DakaKiki\CustomerArtworkUpload\Storage\StorageInterface;
use WC_Order;
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
            array( $this, 'render_admin_download_link' ),
            10,
            3
        );

        add_action(
            'woocommerce_order_item_meta_end',
            array( $this, 'render_customer_download_link' ),
            10,
            4
        );

        add_action(
            'admin_post_' . self::ACTION,
            array( $this, 'download' )
        );

        add_action(
            'admin_post_nopriv_' . self::ACTION,
            array( $this, 'download' )
        );
    }

    /**
     * Display a download link in the administrator order view.
     *
     * @param int           $item_id Order item ID.
     * @param WC_Order_Item $item    Order item object.
     * @param mixed         $product Product object, when applicable.
     */
    public function render_admin_download_link(
        int $item_id,
        WC_Order_Item $item,
        $product
    ): void {
        unset( $product );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $this->render_link( $item_id, $item );
    }

    /**
     * Display a download link to the logged-in owner on View order.
     *
     * @param int           $item_id   Order item ID.
     * @param WC_Order_Item $item      Order item object.
     * @param WC_Order      $order     Order object.
     * @param bool          $plain_text Whether plain-text output is requested.
     */
    public function render_customer_download_link(
        int $item_id,
        WC_Order_Item $item,
        WC_Order $order,
        bool $plain_text
    ): void {
        if (
            $plain_text ||
            is_admin() ||
            ! is_user_logged_in() ||
            ! $this->user_can_access_order( $order )
        ) {
            return;
        }

        $this->render_link( $item_id, $item );
    }

    /**
     * Stream a protected artwork file to an authorized user.
     */
    public function download(): void {
        if ( ! is_user_logged_in() ) {
            auth_redirect();
        }

        $item_id = isset( $_GET['item_id'] )
            ? absint( wp_unslash( $_GET['item_id'] ) )
            : 0;

        if ( 0 === $item_id ) {
            $this->not_found();
        }

        check_admin_referer( self::nonce_action( $item_id ) );

        $item = WC_Order_Factory::get_order_item( $item_id );

        if ( ! $item instanceof WC_Order_Item ) {
            $this->not_found();
        }

        $order = $item->get_order();

        if (
            ! $order instanceof WC_Order ||
            ! $this->user_can_access_order( $order )
        ) {
            $this->forbidden();
        }

        $storage_key = sanitize_file_name(
            (string) $item->get_meta( '_cau_artwork_storage_key', true )
        );
        $original_name = sanitize_file_name(
            (string) $item->get_meta( '_cau_artwork_original_name', true )
        );
        $mime_type = sanitize_mime_type(
            (string) $item->get_meta( '_cau_artwork_mime_type', true )
        );
        $path = $this->storage->get_path( $storage_key );

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

    private function render_link( int $item_id, WC_Order_Item $item ): void {
        $storage_key = (string) $item->get_meta(
            '_cau_artwork_storage_key',
            true
        );
        $original_name = (string) $item->get_meta(
            '_cau_artwork_original_name',
            true
        );

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

    private function user_can_access_order( WC_Order $order ): bool {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }

        $customer_id = (int) $order->get_customer_id();

        return $customer_id > 0 && get_current_user_id() === $customer_id;
    }

    private static function nonce_action( int $item_id ): string {
        return self::ACTION . '_' . $item_id;
    }

    private function forbidden(): void {
        wp_die(
            esc_html__( 'You are not allowed to download this artwork.', 'customer-artwork-upload' ),
            esc_html__( 'Forbidden', 'customer-artwork-upload' ),
            array( 'response' => 403 )
        );
    }

    private function not_found(): void {
        wp_die(
            esc_html__( 'The requested artwork file could not be found.', 'customer-artwork-upload' ),
            esc_html__( 'Artwork not found', 'customer-artwork-upload' ),
            array( 'response' => 404 )
        );
    }
}

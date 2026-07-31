<?php

namespace DakaKiki\CustomerArtworkUpload\Frontend;

use DakaKiki\CustomerArtworkUpload\Admin\ProductSettings;
use WC_Product;

defined( 'ABSPATH' ) || exit;

final class ProductUploadField {

    public const FIELD_NAME = 'cau_artwork';

    /**
     * Register frontend hooks.
     */
    public static function register(): void {
        add_action(
            'woocommerce_before_add_to_cart_button',
            array( self::class, 'render' )
        );

        add_filter(
            'woocommerce_add_to_cart_validation',
            array( self::class, 'validate' ),
            10,
            3
        );
    }

    /**
     * Display the artwork field on enabled simple products.
     */
    public static function render(): void {
        global $product;

        if (
            ! $product instanceof WC_Product ||
            ! $product->is_type( 'simple' ) ||
            'yes' !== $product->get_meta( ProductSettings::META_ENABLED )
        ) {
            return;
        }

        $required      = 'yes' === $product->get_meta(
            ProductSettings::META_REQUIRED
        );
        $allowed_types = self::get_allowed_types( $product );
        $max_size      = self::get_max_size( $product );
        $accept         = self::get_accept_attribute( $allowed_types );

        echo '<div class="cau-artwork-upload">';

        printf(
            '<label for="%1$s">%2$s%3$s</label>',
            esc_attr( self::FIELD_NAME ),
            esc_html__( 'Upload your artwork', 'customer-artwork-upload' ),
            $required
                ? ' <span class="required" aria-hidden="true">*</span>'
                : ''
        );

        printf(
            '<input type="file"
                    id="%1$s"
                    name="%1$s"
                    accept="%2$s"
                    %3$s>',
            esc_attr( self::FIELD_NAME ),
            esc_attr( $accept ),
            $required ? 'required' : ''
        );

        printf(
            '<small class="cau-artwork-help">%1$s</small>',
            esc_html(
                sprintf(
                    /* translators: 1: file extensions, 2: maximum size */
                    __(
                        'Allowed formats: %1$s. Maximum size: %2$d MB.',
                        'customer-artwork-upload'
                    ),
                    strtoupper( implode( ', ', $allowed_types ) ),
                    $max_size
                )
            )
        );

        echo '</div>';
    }

    /**
     * Validate the submitted upload before adding the product to the cart.
     *
     * @param bool $passed       Existing validation result.
     * @param int  $product_id   Product ID.
     * @param int  $quantity     Requested quantity.
     */
    public static function validate(
        bool $passed,
        int $product_id,
        int $quantity
    ): bool {
        unset( $quantity );

        $product = wc_get_product( $product_id );

        if (
            ! $product instanceof WC_Product ||
            'yes' !== $product->get_meta( ProductSettings::META_ENABLED )
        ) {
            return $passed;
        }

        $required = 'yes' === $product->get_meta(
            ProductSettings::META_REQUIRED
        );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = isset( $_FILES[ self::FIELD_NAME ] )
            ? $_FILES[ self::FIELD_NAME ]
            : null;

        $upload_error = is_array( $file ) && isset( $file['error'] )
            ? absint( $file['error'] )
            : UPLOAD_ERR_NO_FILE;

        if ( UPLOAD_ERR_NO_FILE === $upload_error ) {
            if ( $required ) {
                wc_add_notice(
                    __(
                        'Please upload your artwork before adding this product to the cart.',
                        'customer-artwork-upload'
                    ),
                    'error'
                );

                return false;
            }

            return $passed;
        }

        if ( UPLOAD_ERR_OK !== $upload_error ) {
            wc_add_notice(
                __(
                    'The artwork could not be uploaded. Please try again.',
                    'customer-artwork-upload'
                ),
                'error'
            );

            return false;
        }

        $file_name = isset( $file['name'] )
            ? sanitize_file_name( wp_unslash( $file['name'] ) )
            : '';

        $temporary_name = isset( $file['tmp_name'] )
            ? $file['tmp_name']
            : '';

        $file_size = isset( $file['size'] )
            ? absint( $file['size'] )
            : 0;

        if (
            '' === $file_name ||
            '' === $temporary_name ||
            ! is_uploaded_file( $temporary_name )
        ) {
            wc_add_notice(
                __(
                    'The submitted artwork upload is invalid.',
                    'customer-artwork-upload'
                ),
                'error'
            );

            return false;
        }

        $maximum_bytes = self::get_max_size( $product ) * MB_IN_BYTES;

        if ( 0 === $file_size || $file_size > $maximum_bytes ) {
            wc_add_notice(
                sprintf(
                    /* translators: %d: maximum file size in megabytes */
                    __(
                        'Artwork must be no larger than %d MB.',
                        'customer-artwork-upload'
                    ),
                    self::get_max_size( $product )
                ),
                'error'
            );

            return false;
        }

        $allowed_mimes = self::get_allowed_mimes(
            self::get_allowed_types( $product )
        );

        $checked_file = wp_check_filetype_and_ext(
            $temporary_name,
            $file_name,
            $allowed_mimes
        );

        if (
            empty( $checked_file['ext'] ) ||
            empty( $checked_file['type'] ) ||
            ! in_array( $checked_file['type'], $allowed_mimes, true )
        ) {
            wc_add_notice(
                __(
                    'This artwork file type is not allowed.',
                    'customer-artwork-upload'
                ),
                'error'
            );

            return false;
        }

        return $passed;
    }

    /**
     * Get normalized product file types.
     *
     * @return array<string>
     */
    private static function get_allowed_types( WC_Product $product ): array {
        $types = $product->get_meta(
            ProductSettings::META_ALLOWED_TYPES
        );

        if ( ! is_array( $types ) || empty( $types ) ) {
            return array( 'jpg', 'png', 'pdf' );
        }

        return array_values(
            array_intersect(
                array( 'jpg', 'png', 'pdf' ),
                array_map( 'sanitize_key', $types )
            )
        );
    }

    /**
     * Get the configured maximum size in MB.
     */
    private static function get_max_size( WC_Product $product ): int {
        $size = absint(
            $product->get_meta( ProductSettings::META_MAX_FILE_SIZE )
        );

        return max( 1, min( 100, $size ?: 10 ) );
    }

    /**
     * Build the browser accept attribute.
     *
     * @param array<string> $types Allowed extensions.
     */
    private static function get_accept_attribute( array $types ): string {
        $accept_values = array(
            'jpg' => '.jpg,.jpeg,image/jpeg',
            'png' => '.png,image/png',
            'pdf' => '.pdf,application/pdf',
        );

        $values = array();

        foreach ( $types as $type ) {
            if ( isset( $accept_values[ $type ] ) ) {
                $values[] = $accept_values[ $type ];
            }
        }

        return implode( ',', $values );
    }

    /**
     * Map extensions to allowed MIME types.
     *
     * @param array<string> $types Allowed extensions.
     * @return array<string, string>
     */
    private static function get_allowed_mimes( array $types ): array {
        $mime_map = array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
        );

        return array_intersect_key(
            $mime_map,
            array_flip( $types )
        );
    }
}
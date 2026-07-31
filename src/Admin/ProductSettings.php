<?php

namespace DakaKiki\CustomerArtworkUpload\Admin;

use WC_Product;

defined( 'ABSPATH' ) || exit;

final class ProductSettings {

    public const META_ENABLED       = '_cau_upload_enabled';
    public const META_REQUIRED      = '_cau_upload_required';
    public const META_ALLOWED_TYPES = '_cau_allowed_file_types';
    public const META_MAX_FILE_SIZE = '_cau_max_file_size';

    /**
     * Register WooCommerce hooks.
     */
    public static function register(): void {
        add_action(
            'woocommerce_product_options_general_product_data',
            array( self::class, 'render_fields' )
        );

        add_action(
            'woocommerce_admin_process_product_object',
            array( self::class, 'save_fields' )
        );
    }

    /**
     * Render artwork-upload settings in the product editor.
     */
    public static function render_fields(): void {
        global $post;

        if ( ! $post ) {
            return;
        }

        $product = wc_get_product( $post->ID );

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        echo '<div class="options_group show_if_simple">';

        woocommerce_wp_checkbox(
            array(
                'id'          => self::META_ENABLED,
                'label'       => __(
                    'Enable artwork upload',
                    'customer-artwork-upload'
                ),
                'description' => __(
                    'Allow customers to attach artwork to this product.',
                    'customer-artwork-upload'
                ),
                'desc_tip'    => true,
                'value'       => $product->get_meta( self::META_ENABLED ),
            )
        );

        woocommerce_wp_checkbox(
            array(
                'id'          => self::META_REQUIRED,
                'label'       => __(
                    'Artwork is required',
                    'customer-artwork-upload'
                ),
                'description' => __(
                    'Prevent the product from being added to the cart without artwork.',
                    'customer-artwork-upload'
                ),
                'desc_tip'    => true,
                'value'       => $product->get_meta( self::META_REQUIRED ),
            )
        );

        $allowed_types = $product->get_meta( self::META_ALLOWED_TYPES );

        if ( ! is_array( $allowed_types ) ) {
            $allowed_types = array( 'jpg', 'jpeg', 'png', 'pdf' );
        }

        echo '<p class="form-field">';
        echo '<label>' .
            esc_html__(
                'Allowed file types',
                'customer-artwork-upload'
            ) .
            '</label>';

        foreach (
            array(
                'jpg'  => __( 'JPEG', 'customer-artwork-upload' ),
                'png'  => __( 'PNG', 'customer-artwork-upload' ),
                'pdf'  => __( 'PDF', 'customer-artwork-upload' ),
            ) as $type => $label
        ) {
        printf(
            '<label style="float:none; width:auto; margin:0 15px 0 0; display:inline-block;">
                <input type="checkbox"
                    name="%1$s[]"
                    value="%2$s" %3$s>
                %4$s
            </label>',
            esc_attr( self::META_ALLOWED_TYPES ),
            esc_attr( $type ),
            checked(
                in_array(
                    $type,
                    self::normalize_types( $allowed_types ),
                    true
                ),
                true,
                false
            ),
            esc_html( $label )
        );
        }

        echo wc_help_tip(
            __(
                'Select the artwork formats customers may upload.',
                'customer-artwork-upload'
            )
        );
        echo '</p>';

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_MAX_FILE_SIZE,
                'label'             => __(
                    'Maximum file size',
                    'customer-artwork-upload'
                ),
                'description'       => __(
                    'Maximum size for one uploaded file.',
                    'customer-artwork-upload'
                ),
                'desc_tip'          => true,
                'type'              => 'number',
                'value'             => $product->get_meta(
                    self::META_MAX_FILE_SIZE
                ) ?: '10',
                'custom_attributes' => array(
                    'min'  => '1',
                    'max'  => '100',
                    'step' => '1',
                ),
            )
        );

        echo '<p class="form-field">';
        echo '<span class="description">' .
            esc_html__( 'Size is measured in MB.', 'customer-artwork-upload' ) .
            '</span>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Save product settings through the WooCommerce product object.
     */
    public static function save_fields( WC_Product $product ): void {
        $enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no';

        $required = (
            'yes' === $enabled &&
            isset( $_POST[ self::META_REQUIRED ] )
        ) ? 'yes' : 'no';

        $submitted_types = isset(
            $_POST[ self::META_ALLOWED_TYPES ]
        )
            ? (array) wp_unslash(
                $_POST[ self::META_ALLOWED_TYPES ]
            )
            : array();

        $allowed_types = self::normalize_types( $submitted_types );

        $max_file_size = isset(
            $_POST[ self::META_MAX_FILE_SIZE ]
        )
            ? absint(
                wp_unslash(
                    $_POST[ self::META_MAX_FILE_SIZE ]
                )
            )
            : 10;

        $max_file_size = max( 1, min( 100, $max_file_size ) );

        $product->update_meta_data( self::META_ENABLED, $enabled );
        $product->update_meta_data( self::META_REQUIRED, $required );
        $product->update_meta_data(
            self::META_ALLOWED_TYPES,
            $allowed_types
        );
        $product->update_meta_data(
            self::META_MAX_FILE_SIZE,
            $max_file_size
        );
    }

    /**
     * Restrict file types to formats supported by the Lite plugin.
     *
     * @param array<mixed> $types Submitted extensions.
     * @return array<string>
     */
    private static function normalize_types( array $types ): array {
        $types = array_map( 'sanitize_key', $types );

        // Treat jpeg and jpg as one JPEG selection.
        if ( in_array( 'jpeg', $types, true ) ) {
            $types[] = 'jpg';
        }

        return array_values(
            array_intersect(
                array( 'jpg', 'png', 'pdf' ),
                array_unique( $types )
            )
        );
    }
}
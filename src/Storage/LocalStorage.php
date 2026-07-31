<?php

namespace DakaKiki\CustomerArtworkUpload\Storage;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class LocalStorage implements StorageInterface {

    private const DIRECTORY_NAME = 'customer-artwork-upload-private';
    private const PENDING_SUFFIX = '.cau-pending';

    /**
     * @param array<string, mixed> $file Uploaded file data.
     * @return array<string, mixed>|WP_Error
     */
    public function store( array $file ) {
        $temporary_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        $original_name  = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';

        if ( '' === $temporary_name || '' === $original_name || ! is_uploaded_file( $temporary_name ) ) {
            return new WP_Error( 'cau_invalid_upload', __( 'The submitted artwork upload is invalid.', 'customer-artwork-upload' ) );
        }

        $checked_file = wp_check_filetype_and_ext(
            $temporary_name,
            $original_name,
            array(
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'pdf'  => 'application/pdf',
            )
        );

        if ( empty( $checked_file['ext'] ) || empty( $checked_file['type'] ) ) {
            return new WP_Error( 'cau_invalid_file_type', __( 'This artwork file type is not allowed.', 'customer-artwork-upload' ) );
        }

        $directory = $this->get_base_directory();

        if ( ! $this->prepare_directory( $directory ) ) {
            return new WP_Error( 'cau_storage_unavailable', __( 'Artwork storage is unavailable. Please contact the site administrator.', 'customer-artwork-upload' ) );
        }

        $extension   = strtolower( (string) $checked_file['ext'] );
        $storage_key = wp_generate_uuid4() . '.' . $extension;
        $destination = trailingslashit( $directory ) . $storage_key;

        if ( ! move_uploaded_file( $temporary_name, $destination ) ) {
            return new WP_Error( 'cau_move_failed', __( 'The artwork could not be stored. Please try again.', 'customer-artwork-upload' ) );
        }

        @chmod( $destination, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if ( false === file_put_contents( $this->get_pending_path( $storage_key ), (string) time(), LOCK_EX ) ) {
            wp_delete_file( $destination );
            return new WP_Error( 'cau_pending_marker_failed', __( 'The artwork could not be stored. Please try again.', 'customer-artwork-upload' ) );
        }

        return array(
            'storage_key'   => $storage_key,
            'original_name' => $original_name,
            'mime_type'     => (string) $checked_file['type'],
            'size'          => filesize( $destination ) ?: 0,
        );
    }

    public function get_path( string $storage_key ): string {
        $storage_key = $this->sanitize_storage_key( $storage_key );

        return '' === $storage_key ? '' : trailingslashit( $this->get_base_directory() ) . $storage_key;
    }

    public function mark_permanent( string $storage_key ): bool {
        $marker = $this->get_pending_path( $storage_key );

        if ( '' === $marker || ! is_file( $marker ) ) {
            return true;
        }

        wp_delete_file( $marker );
        return ! file_exists( $marker );
    }

    public function cleanup_pending( int $cutoff_timestamp ): array {
        $result  = array( 'deleted' => 0, 'failed' => 0 );
        $pattern = trailingslashit( $this->get_base_directory() ) . '*' . self::PENDING_SUFFIX;
        $markers = glob( $pattern );

        if ( false === $markers ) {
            return $result;
        }

        foreach ( $markers as $marker ) {
            $modified = filemtime( $marker );

            if ( false === $modified || $modified > $cutoff_timestamp ) {
                continue;
            }

            $storage_key = basename( substr( $marker, 0, -strlen( self::PENDING_SUFFIX ) ) );
            $file_path   = $this->get_path( $storage_key );
            $file_ok     = '' === $file_path || ! is_file( $file_path ) || $this->delete( $storage_key );

            if ( $file_ok ) {
                if ( is_file( $marker ) ) {
                    wp_delete_file( $marker );
                }
                ++$result['deleted'];
            } else {
                ++$result['failed'];
            }
        }

        return $result;
    }

    public function delete( string $storage_key ): bool {
        $path = $this->get_path( $storage_key );

        if ( '' === $path || ! is_file( $path ) ) {
            return false;
        }

        wp_delete_file( $path );

        if ( ! file_exists( $path ) ) {
            $this->mark_permanent( $storage_key );
            return true;
        }

        return false;
    }

    private function get_pending_path( string $storage_key ): string {
        $path = $this->get_path( $storage_key );
        return '' === $path ? '' : $path . self::PENDING_SUFFIX;
    }

    private function sanitize_storage_key( string $storage_key ): string {
        return sanitize_file_name( basename( $storage_key ) );
    }

    private function get_base_directory(): string {
        $uploads = wp_upload_dir( null, false );
        return trailingslashit( $uploads['basedir'] ) . self::DIRECTORY_NAME;
    }

    private function prepare_directory( string $directory ): bool {
        if ( ! wp_mkdir_p( $directory ) || ! is_writable( $directory ) ) {
            return false;
        }

        $protection_files = array(
            'index.php'  => "<?php\n// Silence is golden.\n",
            '.htaccess'  => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
            'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></system.webServer></configuration>',
        );

        foreach ( $protection_files as $name => $contents ) {
            $path = trailingslashit( $directory ) . $name;
            if ( ! file_exists( $path ) && false === file_put_contents( $path, $contents, LOCK_EX ) ) {
                return false;
            }
        }

        return true;
    }
}

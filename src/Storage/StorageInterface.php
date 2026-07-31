<?php

namespace DakaKiki\CustomerArtworkUpload\Storage;

use WP_Error;

defined( 'ABSPATH' ) || exit;

interface StorageInterface {

    /**
     * Store a validated PHP upload.
     *
     * @param array<string, mixed> $file Uploaded file data.
     * @return array<string, mixed>|WP_Error
     */
    public function store( array $file );

    /**
     * Resolve a storage key to a local path.
     */
    public function get_path( string $storage_key ): string;

    /**
     * Delete a stored file.
     */
    public function delete( string $storage_key ): bool;
}

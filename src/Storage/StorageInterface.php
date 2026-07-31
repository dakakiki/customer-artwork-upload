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
     * Mark a pending upload as attached to an order.
     */
    public function mark_permanent( string $storage_key ): bool;

    /**
     * Delete pending uploads whose marker is older than the cutoff timestamp.
     *
     * @return array{deleted:int,failed:int}
     */
    public function cleanup_pending( int $cutoff_timestamp ): array;

    /**
     * Delete a stored file.
     */
    public function delete( string $storage_key ): bool;
}

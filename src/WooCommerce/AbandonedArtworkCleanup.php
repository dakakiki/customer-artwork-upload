<?php

namespace DakaKiki\CustomerArtworkUpload\WooCommerce;

use DakaKiki\CustomerArtworkUpload\Storage\StorageInterface;

defined( 'ABSPATH' ) || exit;

final class AbandonedArtworkCleanup {

    public const CRON_HOOK = 'cau_cleanup_abandoned_artwork';
    private const DEFAULT_GRACE_PERIOD = 2 * DAY_IN_SECONDS;

    /** @var StorageInterface */
    private $storage;

    public function __construct( StorageInterface $storage ) {
        $this->storage = $storage;
    }

    public function register(): void {
        add_action( self::CRON_HOOK, array( $this, 'run' ) );

        if ( did_action( 'init' ) ) {
            $this->ensure_scheduled();
        } else {
            add_action( 'init', array( $this, 'ensure_scheduled' ) );
        }

        if ( defined( 'CAU_PLUGIN_FILE' ) ) {
            register_deactivation_hook( CAU_PLUGIN_FILE, array( __CLASS__, 'unschedule' ) );
        }
    }

    public function ensure_scheduled(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
        }
    }

    public function run(): void {
        $grace_period = (int) apply_filters( 'cau_abandoned_upload_grace_period', self::DEFAULT_GRACE_PERIOD );
        $grace_period = max( HOUR_IN_SECONDS, $grace_period );
        $result       = $this->storage->cleanup_pending( time() - $grace_period );

        if ( $result['failed'] > 0 && function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->error(
                'One or more abandoned artwork files could not be deleted.',
                array( 'source' => 'customer-artwork-upload', 'failed_count' => $result['failed'] )
            );
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }
}

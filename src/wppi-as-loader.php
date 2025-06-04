<?php
/**
 * Loads the action scheduler library.
 *
 * @package Plugin Installer
 */

if ( ! function_exists( 'wppi_load_as' ) && function_exists( 'add_action' ) ) :

    /**
     * Load the Action Scheduler library.
     */
    function wppi_load_as(): void {
        if ( function_exists( 'WC' ) || function_exists( 'action_scheduler_register_3_dot_9_dot_2' ) ) {
            return;
        }

        $base = str_starts_with( __DIR__, ABSPATH )
            ? realpath( __DIR__ . '/../../..' )
            : realpath( __DIR__ . '/../vendor' );

        $path = $base . '/woocommerce/action-scheduler/action-scheduler.php';

        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }

        // Try one more time.
        $base = realpath( __DIR__ . '/../../../../vendor' );
        $path = $base . '/woocommerce/action-scheduler/action-scheduler.php';

        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
    add_action( 'plugins_loaded', 'wppi_load_as', -10, 0 );

endif;

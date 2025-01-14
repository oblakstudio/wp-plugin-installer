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
    function wppi_load_as() {
        if ( function_exists( 'WC' ) || function_exists( 'action_scheduler_register_3_dot_7_dot_1' ) ) {
            return;
        }

        $base = str_starts_with( __DIR__, ABSPATH )
            ? realpath( __DIR__ . '/../../..' )
            : realpath( __DIR__ . '/../vendor' );

        require_once $base . '/woocommerce/action-scheduler/action-scheduler.php';
    }

    add_action( 'plugins_loaded', 'wppi_load_as', -10, 0 );

endif;

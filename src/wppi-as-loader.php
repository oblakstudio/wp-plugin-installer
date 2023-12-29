<?php
/**
 * Loads the action scheduler library.
 *
 * @package Plugin Installer
 */

if ( defined( 'ABSPATH' ) ) {
	add_action(
        'plugins_loaded',
        function () {
            require_once trailingslashit( realpath( __DIR__ . '/../../..' ) ) . '/woocommerce/action-scheduler/action-scheduler.php';
        },
        -10
	);
}

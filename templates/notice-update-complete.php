<?php
/**
 * Admin View: Notice - Updated.
 *
 * @package Plugin Installer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<p>
    <?php
    printf(
        /* translators: %s: plugin name */
        esc_html__( '%s database update complete. Thank you for updating to the latest version!', 'oblak-plugin-installer' ),
        esc_html( $plugin_name )
    );
    ?>
</p>

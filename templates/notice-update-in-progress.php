<?php
/**
 * Admin View: Notice - Updating
 *
 * @package Plugin Installer
 */

use Automattic\Jetpack\Constants;

defined( 'ABSPATH' ) || exit;

$cron_disabled = Constants::is_true( 'DISABLE_WP_CRON' );
$cron_cta      = $cron_disabled ? __( 'You can manually run queued updates here.', 'oblak-plugin-installer' ) : __( 'View progress &rarr;', 'oblak-plugin-installer' );
?>
<p>
    <strong>
        <?php
        printf(
            /* translators: %s: plugin name */
            esc_html__( '%s database update', 'oblak-plugin-installer' ),
            esc_html( $plugin_name )
        );
        ?>
    </strong>
</p>
<p>
    <?php
    printf(
        /* translators: %s: plugin name */
        esc_html__( '%s is updating the database in the background. The database update process may take a little while, so please be patient.', 'oblak-plugin-installer' ),
        esc_html( $plugin_name )
    );
    if ( $cron_disabled ) {
        echo '<br>' . esc_html__( 'Note: WP CRON has been disabled on your install which may prevent this update from completing.', 'oblak-plugin-installer' );
    }
    ?>
    &nbsp;<a href="<?php echo esc_url( $scheduler_url ); ?>"><?php echo esc_html( $cron_cta ); ?></a>
</p>

<?php
/**
 * Admin View: Notice - Update
 *
 * @package Plugin Installer
 */

defined( 'ABSPATH' ) || exit;

if ( '' === $update_url ) {
    $update_url = wp_nonce_url(
        add_query_arg( "do_update_{$plugin_slug}", 'true', admin_url( 'index.php' ) ),
        "{$plugin_slug}_db_update",
        "{$plugin_slug}_db_update_nonce"
    );
}
?>
<p>
    <strong>
        <?php
        printf(
            /* translators: %s: plugin name */
            esc_html__( '%s database update required', 'oblak-plugin-installer' ),
            esc_html( $plugin_name )
        );
        ?>
    </strong>
</p>
<p>
    <?php
        printf(
            /* translators: %s: plugin name */
            esc_html__( '%s has been updated! To keep things running smoothly, we have to update your database to the newest version.', 'oblak-plugin-installer' ),
            esc_html( $plugin_name )
        );

        printf(
            /* translators: 1: Link to docs 2: Close link. */
            ' ' . esc_html__( 'The database update process runs in the background and may take a little while, so please be patient. Advanced users can alternatively update via %1$sWP CLI%2$s.', 'oblak-plugin-installer' ),
            "<a href='{$cli_update_faq}'>", //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            '</a>'
        );
        ?>
</p>
<p class="submit">
    <a href="<?php echo esc_url( $update_url ); ?>" class="wc-update-now button-primary">
        <?php
        printf(
            /* translators: %s: plugin name */
            esc_html__( 'Update %s Database', 'oblak-plugin-installer' ),
            esc_html( $plugin_name )
        );
        ?>
    </a>
    <a href="<?php echo esc_url( $how_to_update ); ?>" class="button-secondary">
        <?php esc_html_e( 'Learn more about updates', 'oblak-plugin-installer' ); ?>
    </a>
</p>

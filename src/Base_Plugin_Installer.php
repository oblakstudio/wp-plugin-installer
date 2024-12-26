<?php //phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Base_Installer class file.
 *
 * @package Plugin Installer
 * @link https://plugin-installer.wp.rs
 */

namespace Oblak\WP;

use Automattic\Jetpack\Constants;
use Closure;
use Oblak\WP\Admin_Notice_Manager;
use WP_CLI;

use function WP_CLI\Utils\make_progress_bar;

/**
 * Base Installer class from which all installers derive.
 */
abstract class Base_Plugin_Installer {
    /**
     * Class instance
     *
     * @var Base_Plugin_Installer[]
     */
    protected static $instances = array();

    /**
     * Full plugin name
     *
     * @var string
     */
    protected $name;

    /**
     * Translatable plugin name
     *
     * @var Closure(): string
     */
    protected Closure $tr_name;

    /**
     * Plugin slug for use in filters, actions and database keys
     *
     * @var string
     */
    protected $slug = '';

    /**
     * Plugin version
     *
     * @var string
     */
    protected $version = '';

    /**
     * Plugin DB version
     *
     * @var string
     */
    protected $db_version = null;

    /**
     * Wheter to show admin notices
     *
     * @var bool
     */
    protected $show_admin_notices = true;

    /**
     * Class constructor
     */
    protected function __construct() {
        $this->set_defaults();
        $this->verify_defaults();

        $this->db_version ??= $this->version;
    }

    /**
     * Returns the singleton instance
     *
     * @return static
     */
    final public static function instance() {
        return static::$instances[ static::class_basename( static::class ) ] ??= new static();
    }

    /**
     * Get the plugin name
     *
     * @return string
     */
    protected function get_name(): string {
        if ( ! isset( $this->name ) ) {
            $this->name = isset( $this->tr_name ) ? ( $this->tr_name )() : '';
        }

        return $this->name;
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object $classname Class name or object.
     * @return string
     */
    private static function class_basename( $classname ) {
        $classname = \is_object( $classname ) ? $classname::class : $classname;

        return \str_replace( '\\', '/', $classname );
    }

    /**
     * Sets the class defaults.
     *
     * Sets the plugin slug, plugin version, and db schema version
     */
    abstract protected function set_defaults();

    /**
     * Verifies the class defaults.
     *
     * @throws \Exception If the plugin slug is not set, or if the plugin version is not set.
     */
    protected function verify_defaults() {
        if ( '' === $this->version ) {
            throw new \Exception( \esc_html__( 'Plugin version not set', 'oblak-plugin-installer' ) );
        }

        if ( '' === $this->slug ) {
            throw new \Exception( \esc_html__( 'Plugin slug not set', 'oblak-plugin-installer' ) );
        }

        if ( '' === $this->db_version && '' !== $this->get_schema() ) {
            throw new \Exception( \esc_html__( 'Plugin schema version not set', 'oblak-plugin-installer' ) );
        }
    }

    /**
     * Initialize and hook me baby one more time
     */
    public function init() {
        \add_action( 'init', array( $this, 'load_textdomain' ) );
        \add_action( 'init', array( $this, 'check_version' ) );
        \add_action( 'admin_init', array( $this, 'install_actions' ) );
        \add_action( 'cli_init', array( $this, 'register_commands' ) );
        \add_filter(
            'woocommerce_debug_tools',
            array( $this, 'add_debug_tools' ),
            99 + \count( static::$instances ),
            1,
        );

        \add_action( "{$this->slug}_run_update_callback", array( $this, 'run_update_callback' ), 10, 2 );
    }

    /**
     * Loads our textdomain file for translations
     */
    public function load_textdomain() {
        $locale = \get_locale();

        $mofile_path = \dirname( __DIR__ ) . "/languages/oblak-plugin-installer-{$locale}.mo";

        if ( ! \file_exists( $mofile_path ) ) {
            return;
        }

        \load_textdomain( 'oblak-plugin-installer', $mofile_path );
    }

    /**
     * Checks if this is a new install or an update
     *
     * @return bool
     */
    public function is_new_install() {
        return \is_null( \get_option( "{$this->slug}_version", null ) );
    }

    /**
     * Checks the plugin version and runs the updater if required
     */
    public function check_version() {
        $plugin_version = \get_option( "{$this->slug}_version" );
        $code_version   = $this->version;
        $needs_update   = \version_compare( $plugin_version, $code_version, '<' );

        if ( Constants::is_defined( 'IFRAME_REQUEST' ) || ! $needs_update ) {
            return;
        }

        $this->install();
        /**
         * Action fired after plugin is updated
         *
         * @since 5.4.0
         */
        \do_action( "{$this->slug}_updated" );
    }

    /**
     * Runs the plugin installation
     */
    public function install() {
        if ( ! \is_blog_installed() || 'yes' === \get_transient( "{$this->slug}_installing" ) ) {
            return;
        }

        \set_transient( "{$this->slug}_installing", 'yes', MINUTE_IN_SECONDS * 5 );
        Constants::set_constant( \str_replace( '-', '_', \strtoupper( $this->slug ) . '_INSTALLING' ), true );

        if ( $this->get_schema() ) {
            $this->create_tables();
            $this->verify_base_tables();
        }

        $this->create_options();
        $this->create_roles();
        $this->setup_environment();
        $this->create_terms();
        $this->update_plugin_version();
        $this->maybe_update_db_version();

        if ( $this->is_new_install() ) {
            /**
             * Fires after the base installation steps are completed
             *
             * @since 1.0.0
             */
            \do_action( "{$this->slug}_install" );
        }

        \delete_transient( "{$this->slug}_installing" );
    }

    /**
     * Sets up the database tables which the plugin needs to function.
     * WARNING: If you're fucking around with this method, make sure that it's safe to call regardless of the state of the database.
     *
     * This is called from install method above and runs only when installing or updating the plugin.
     *
     * @since 2.0.0
     */
    public function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        \dbDelta( $this->get_schema() );
    }

    /**
     * Get table schema
     *
     * @return string Table schema
     */
    protected function get_schema(): ?string {
        return null;
    }

    /**
     * Get the database table names which are not in sync
     *
     * @return array<int, string>
     */
    protected function get_unsynced_tables(): array {
        global $wpdb;

        $queries = \dbDelta( $this->get_schema(), false );
        $tables  = array();

        foreach ( $queries as $table_name => $result ) {
            if ( \is_numeric( $table_name ) || ! \str_contains( $table_name, $wpdb->prefix ) ) {
                continue;
            }

            $tables[] = \strtok( $table_name, '.' );
        }

        return $tables;
    }

    /**
     * Display a notice if the database tables are missing or out of sync
     *
     * @param  bool               $modify_notice  Can we modify the notice.
     * @param  array<int, string> $missing_tables List of missing tables.
     */
    protected function display_missing_tables_notice( bool $modify_notice, array $missing_tables = array() ) {
        if ( ! $modify_notice || ! $this->show_admin_notices ) {
            return;
        }

        \xwp_get_notice( "{$this->slug}_missing_tables" )
            ->set_defaults()
            ->set_props(
                array(
                    'caps'        => 'manage_woocommerce',
                    'classes'     => 'alt',
                    'dismissible' => false,
                    'message'     => \sprintf(
                        '<p><strong>%s</strong> - %s: %s</p>',
                        \esc_html( $this->slug ),
                        \esc_html__( 'The following tables are missing: ', 'oblak-plugin-installer' ),
                        \implode( ', ', $missing_tables ),
                    ),
                    'persistent'  => true,
                    'type'        => 'error',
                ),
            )
            ->save( true );
    }

    /**
     * Verifies if the database tables have been created.
     *
     * @param  bool $modify_notice Can we modify the notice.
     * @param  bool $execute       Are we executing table creation.
     * @return string[]            List of missing tables.
     */
    public function verify_base_tables( $modify_notice = true, $execute = false ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if ( $execute ) {
            $this->create_tables();
        }

        $missing_tables = $this->get_unsynced_tables();

        if ( 0 < \count( $missing_tables ) ) {
            $this->display_missing_tables_notice( $modify_notice );
        } elseif ( $modify_notice && $this->show_admin_notices ) {
                \xwp_delete_notice( "{$this->slug}_missing_tables", true );
		} else {
            \update_option( "{$this->slug}_schema_version", $this->db_version );
        }

        return $missing_tables;
    }

    /**
     * Creates the default plugin options, if needed
     */
    public function create_options() {
        // Does nothing.
    }

    /**
     * Creates the default roles for the plugin.
     */
    public function create_roles() {
        // Does nothing.
    }

    /**
     * Sets up the plugin environment
     *
     * CPT registration, taxonomies, etc.
     */
    public function setup_environment() {
        // Does nothing.
    }

    /**
     * Creates terms for the plugin.
     */
    public function create_terms() {
        // Does nothing.
    }

    /**
     * Get the admin update notice args.
     *
     * Enables users to change the plugin name, slug, update URL, etc...
     *
     * @param string $file_name Template name to get the args for.
     * @return array{cli_update_faq: string, how_to_update: string, plugin_name: string, plugin_slug: string, scheduler_url: string, update_url: string}
     */
    protected function get_notice_args( string $file_name ): array {
        $file_args = array(
            'cli_update_faq' => '#',
            'how_to_update'  => '#',
            'plugin_name'    => $this->get_name(),
            'plugin_slug'    => $this->slug,
            'scheduler_url'  => "tools.php?page=action-scheduler&s={$this->slug}_run_update&status=pending",
            'update_url'     => '',
        );

        /**
         * Filters the template variables for the admin update notice.
         *
         * @param  array  $file_args   Template variables.
         * @param  string $file_name   Template name.
         * @return array
         *
         * @since 1.0.0
         */
        return \apply_filters( "{$this->slug}_update_notice_args", $file_args, $file_name );
    }

    /**
     * Get the admin update notice template.
     *
     * @return string
     */
    protected function get_update_notice_template(): string {
        if ( $this->get_update_handler()?->needs_update() ) {
            $next_scheduled_date = \as_next_scheduled_action(
                "{$this->slug}_run_update_callback",
                null,
                "{$this->slug}-db-updates",
            );

            //phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $doing_updates = \sanitize_text_field( \wp_unslash( $_GET[ "do-update_{$this->slug}" ] ?? '' ) );

            return $next_scheduled_date || '' !== $doing_updates ? 'update-in-progress' : 'update-needed';
        }

        return 'update-complete';
    }

    /**
     * Adds the admin update notice.
     *
     * @return void
     */
    protected function add_admin_update_notice() {
        if ( ! $this->show_admin_notices ) {
            return;
        }

        $name = $this->get_update_notice_template();
        $file = $this->get_template_file( $name );
        $args = $this->get_notice_args( $name );

        \xwp_get_notice( "{$this->slug}_update_notice" )
            ->set_defaults()
            ->set_props(
                array(
                    'caps'        => 'manage_options',
                    'dismissible' => 'update-complete' === $file,
                    'params'      => $args,
                    'persistent'  => false,
                    'template'    => $file,
                    'type'        => 'info',
                ),
            )
            ->save( true );
    }

    /**
     * Get the template file for the admin update notice.
     *
     * @param  string $template_name Template name.
     * @return string                Template file path.
     */
    protected function get_template_file( $template_name ) {
        $default_path = \dirname( __DIR__ ) . "/templates/notice-{$template_name}.php";

        /**
         * Filters the template path for the plugin.
         *
         * @param  string $default_path  Default template path.
         * @param  string $template_name Template name.
         * @return string
         *
         * @since 1.0.0
         */
        return \apply_filters( "{$this->slug}_get_update_notice_template_file", $default_path, $template_name );
    }

    /**
     * See if we need to show or run database updates during install.
     */
    protected function maybe_update_db_version() {
        if ( $this->get_update_handler()?->needs_update() ) {
            //phpcs:ignore
            if ( apply_filters( "{$this->slug}_enable_auto_update_db", !$this->show_admin_notices ) ) {
                $this->update();
            } else {
                $this->add_admin_update_notice();
            }
        } else {
            $this->update_db_version();
        }
    }

    /**
     * Push all needed updates to the queue for processing.
     */
    public function update() {
        $current_version = \get_option( "{$this->slug}_db_version", null );

        if ( ! $current_version ) {
            return;
        }

        $this->get_update_handler()?->update( $current_version );
    }

    /**
     * Get the update callback handler.
     *
     * @return Update_Callback_Handler
     */
    protected function get_update_handler(): ?Update_Callback_Handler {
        return null;
    }

    /**
     * Run an update callback when triggered by ActionScheduler.
     *
     * @param string $update_callback Callback name.
     */
    public function run_update_callback( $update_callback ) {
        $this->get_update_handler()->run_update_callback( $update_callback );
    }

    /**
     * Install actions when a update button is clicked within the admin area.
     *
     * This function is hooked into admin_init to affect admin only.
     */
    public function install_actions() {
        if ( '' === \sanitize_text_field( \wp_unslash( $_GET[ "do_update_{$this->slug}" ] ?? '' ) ) ) {
            return;
        }

        \check_admin_referer( "{$this->slug}_db_update", "{$this->slug}_db_update_nonce" );
        $this->update();
        $this->add_admin_update_notice();
    }

    /**
     * Update plugin version to current.
     */
    public function update_plugin_version() {
        \update_option( "{$this->slug}_version", $this->version );
    }

    /**
     * Update DB version to current.
     *
     * @param string|null $version New plugin DB version or null.
     */
    public function update_db_version( $version = null ) {
        $version ??= $this->db_version;
        if ( '0.0.0' === $version ) {
            return;
        }

        \update_option( "{$this->slug}_db_version", \is_null( $version ) ? $this->version : $version );
    }

    /**
     * Adds a plugin update command to WP-CLI
     */
    public function register_commands() {
        WP_CLI::add_command( "{$this->slug} update", array( $this, 'cli_update' ) );

        if ( ! $this->get_schema() ) {
            return;
        }

        WP_CLI::add_command( "{$this->slug} verify_tables", array( $this, 'cli_verify_tables' ) );
    }

    // phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

    /**
     * Updates the plugin database to the latest version
     *
     * ## OPTIONS
     *
     * [--from=<version>]
     * : Update the database from a specific version.
     * ---
     *
     * [--force]
     * : Force the update even if the database is already up to date.
     */
    public function cli_update( $args = array(), $assoc_args = array() ) {
        //phpcs:ignore SlevomatCodingStandard.Functions.RequireSingleLineCall.RequiredSingleLineCall
        $assoc_args = \wp_parse_args(
            $assoc_args,
            array(
				'force' => false,
                'from'  => null,
            ),
        );

        global $wpdb;

        $wpdb->hide_errors();

        $handler = $this->get_update_handler();

        $current_db_version = $assoc_args['from'] ??
            ( $assoc_args['force'] ? '0.0.0' : \get_option( "{$this->slug}_db_version", '0.0.1' ) );

        $update_count     = 0;
        $callbacks_to_run = $handler?->get_needed_update_callbacks(
            $current_db_version,
            $assoc_args['force'],
        ) ?? array();

        if ( 0 === \count( $callbacks_to_run ) ) {
            // Ensure DB version is set to the current plugin version to match WP-Admin update routine.
            $this->update_db_version();
            WP_CLI::success(
                \sprintf(
                    // translators: %s Database version number.
                    \__( 'No updates required. Database version is %s', 'oblak-plugin-installer' ),
                    $current_db_version,
                ),
            );
            return;
        }

        WP_CLI::log(
            \sprintf(
                // Translators: 1: Number of database updates 2: List of update callbacks.
                \__( 'Found %1$d updates (%2$s)', 'oblak-plugin-installer' ),
                \count( $callbacks_to_run ),
                \implode( ', ', \wp_list_pluck( $callbacks_to_run, 'details' ) ),
            ),
        );

        $progress = make_progress_bar(
            \__( 'Updating database', 'oblak-plugin-installer' ),
            \count( $callbacks_to_run ),
        );

        foreach ( $callbacks_to_run as $index => $callback_data ) {

            $progress->tick(
                0,
                \sprintf(
                    // Translators: 1: update callback details, 2: update callback version, 3: update callback index, 4: total number of update callbacks.
                    \esc_html__( 'Updating to %2$s: %1$s (%3$d/%4$d)', 'oblak-plugin-installer' ),
                    $callback_data['details'],
                    WP_CLi::colorize( "%C{$callback_data['version']}%n" ),
                    $index + 1,
                    \count( $callbacks_to_run ),
                ),
            );

            $status = $handler->run_update_callback( $callback_data['method'] );
            \sleep( 3 );

            $progress->tick( 1 );

            $status && $update_count++;
        }

        $progress->finish();

        \xwp_delete_notice( "{$this->slug}_update_notice", true );

        WP_CLI::success(
            \sprintf(
                /* translators: 1: Number of database updates performed 2: Database version number */
                \__( '%1$d update functions completed. Database version is %2$s', 'oblak-plugin-installer' ),
                \absint( $update_count ),
                \get_option( "{$this->slug}_db_version" ),
            ),
        );
    }

    /**
     * Runs the DB verification routine and outputs the results to the CLI.
     *
     * ## OPTIONS
     *
     * [--create]
     * : Create the missing tables.
     */
    public function cli_verify_tables( $args = array(), $assoc_args = array() ) {
        $results    = $this->verify_base_tables();
        $assoc_args = \wp_parse_args( $assoc_args, array( 'create' => false ) );

        if ( 0 === \count( $results ) ) {
            WP_CLI::success( \__( 'All database tables are up to date.', 'oblak-plugin-installer' ) );
            return;
        }

        WP_CLI::warning(
            \__( 'The following database tables are out of sync with the schema:', 'oblak-plugin-installer' ),
        );

        foreach ( $results as $table ) {

            WP_CLI::log(
                \sprintf(
                    // Translators: 1: table name, 2: result.
                    \__( 'Table %1$s: %2$s', 'oblak-plugin-installer' ),
                    $table,
                    \__( 'Missing', 'oblak-plugin-installer' ),
                ),
            );
        }

        if ( ! $assoc_args['create'] ) {
            WP_CLI::line(
                \__(
                    'Run the command again with --create to create the missing tables.',
                    'oblak-plugin-installer',
                ),
            );
            return;
        }

        WP_CLI::line( \__( 'Creating missing tables...', 'oblak-plugin-installer' ) );
        $this->verify_base_tables( false, true );

        $results = $this->verify_base_tables();

        if ( 0 === \count( $results ) ) {
            WP_CLI::success( \__( 'All database tables are up to date.', 'oblak-plugin-installer' ) );
            return;
        }

        WP_CLI::error( \__( 'There was an error creating the missing tables.', 'oblak-plugin-installer' ) );
    }

    /**
     * Add database verification to WooCommerce debug tools.
     *
     * @param  array<string, array> $tools Debug tools.
     * @return array
     */
    public function add_debug_tools( array $tools ): array {
        if ( ! $this->get_schema() ) {
            return $tools;
        }

        return \array_merge(
            $tools,
            array(
				"{$this->slug}_verify_db_tables" => array(
                    'button'   => \__( 'Verify database', 'woocommerce' ),
                    'callback' => array( $this, 'debug_verify_db_tables' ),
                    'desc'     => \__( 'Verify if all base database tables are present.', 'woocommerce' ),
                    'name'     => \sprintf(
                        '%s: %s',
                        $this->get_name(),
                        \__( 'Verify base database tables', 'woocommerce' ),
                    ),

                ),
            ),
        );
    }

    /**
     * Verify if all base database tables are present - from WooCommerce debug tools.
     *
     * @return string
     */
    public function debug_verify_db_tables() {
        \xwp_delete_notice( "{$this->slug}_missing_tables", true );

        $missing_tables = $this->verify_base_tables( false, true );

        return 0 === \count( $missing_tables )
            ? \__( 'Database verified successfully.', 'woocommerce' )
            : \__( 'Verifying database... One or more tables are still missing: ', 'woocommerce' ) . \implode(
                ', ',
                $missing_tables,
            );
    }
}

<?php
/**
 * Base_Installer class file.
 *
 * @package Plugin Installer
 * @link https://plugin-installer.wp.rs
 */

namespace Oblak\WP;

use Automattic\Jetpack\Constants;
use Oblak\WP\Admin_Notice_Manager;

use Exception;
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
     * Array of DB Update callbacks
     *
     * @var array
     */
    protected $db_updates = array();

    /**
     * Full plugin name
     *
     * @var string
     */
    protected $name = '';

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
    protected $db_version = '';

    /**
     * Flag to indicate if the plugin has DB tables
     *
     * @var bool
     */
    protected $has_db_tables = false;

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

        $this->db_version = $this->db_version ?? $this->version;
    }

    /**
     * Class instance getter
     *
     * @return Base_Plugin_Installer
     */
    public static function get_instance() {
        $called_class = static::class_basename( static::class );

        return static::$instances[$called_class] ?? static::$instances[$called_class] = new static(); // phpcs:ignore
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object $classname Class name or object.
     * @return string
     */
    private static function class_basename( $classname ) {
        $classname = is_object( $classname ) ? get_class( $classname ) : $classname;

        return basename( str_replace( '\\', '/', $classname ) );
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
     * @throws Exception If the plugin slug is not set, or if the plugin version is not set.
     */
    final protected function verify_defaults() {
        if ( '' === $this->version ) {
            throw new Exception( esc_html__( 'Plugin version not set', 'oblak-plugin-installer' ) );
        }

        if ( '' === $this->slug ) {
            throw new Exception( esc_html__( 'Plugin slug not set', 'oblak-plugin-installer' ) );
        }

        if ( '' === $this->db_version && $this->has_db_tables ) {
            throw new Exception( esc_html__( 'Plugin schema version not set', 'oblak-plugin-installer' ) );
        }
    }

    /**
     * Initialize and hook me baby one more time
     */
    final public function init() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'check_version' ) );
        add_action( 'admin_init', array( $this, 'install_actions' ) );
        add_action( 'cli_init', array( $this, 'register_commands' ) );

        add_action( "{$this->slug}_run_update_callback", array( $this, 'run_update_callback' ), 10, 2 );
    }

    /**
     * Loads our textdomain file for translations
     */
    final public function load_textdomain() {
        $locale = get_locale();

        $mofile_path = dirname( __DIR__ ) . "/languages/oblak-plugin-installer-{$locale}.mo";

        if ( ! file_exists( $mofile_path ) ) {
            return;
        }

        load_textdomain( 'oblak-plugin-installer', $mofile_path );
    }

    /**
     * Checks if this is a new install or an update
     *
     * @return bool
     */
    public function is_new_install() {
        return is_null( get_option( "{$this->slug}_version", null ) );
    }

    /**
     * Checks the plugin version and runs the updater if required
     */
    final public function check_version() {
        if ( ! Constants::is_defined( 'IFRAME_REQUEST' ) && version_compare( get_option( "{$this->slug}_version", '0.0.1' ), $this->version, '<' ) ) {
            $this->install();
            /**
             * Action fired after plugin is updated
             *
             * @since 5.4.0
             */
            do_action( "{$this->slug}_updated" );
        }
    }

    /**
     * Runs the plugin installation
     */
    final public function install() {
        if ( ! is_blog_installed() ) {
            return;
        }

        if ( get_transient( "{$this->slug}_installing" ) === 'yes' ) {
            return;
        }

        set_transient( "{$this->slug}_installing", 'yes', MINUTE_IN_SECONDS * 5 );
        maybe_define_constant( strtoupper( $this->slug ) . '_INSTALLING', true );

        if ( $this->has_db_tables ) {
            $this->create_tables();
            $this->verify_base_tables();
        }

        $this->create_options();
        $this->create_roles();
        $this->setup_environment();
        $this->create_terms();
        $this->maybe_update_db_version();
        $this->update_plugin_version();

        if ( $this->is_new_install() ) {
            /**
             * Fires after the base installation steps are completed
             *
             * @since 1.0.0
             */
            do_action( "{$this->slug}_install" );
        }

        delete_transient( "{$this->slug}_installing" );
    }

    /**
     * Sets up the database tables which the plugin needs to function.
     * WARNING: If you're fucking around with this method, make sure that it's safe to call regardless of the state of the database.
     *
     * This is called from install method above and runs only when installing or updating the plugin.
     *
     * @since 2.0.0
     */
    final public function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $this->get_schema() );
    }

    /**
     * Get table schema
     *
     * @return string Table schema
     */
    protected function get_schema() {
        return '';
    }

    /**
     * Verifies if the database tables have been created.
     *
     * @param  bool $modify_notice Can we modify the notice.
     * @param  bool $execute       Are we executing table creation.
     * @return string[]            List of missing tables.
     */
    final public function verify_base_tables( $modify_notice = true, $execute = false ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if ( $execute ) {
            $this->create_tables();
        }

        $queries        = dbDelta( $this->get_schema(), false );
        $missing_tables = array();

        foreach ( $queries as $table_name => $result ) {
            if ( "Created table {$table_name}" === $result ) {
                $missing_tables[] = $table_name;
            }
        }

        if ( count( $missing_tables ) > 0 ) {
            if ( $modify_notice && $this->show_admin_notices ) {
                Admin_Notice_Manager::get_instance()->add_notice(
                    "{$this->slug}_missing_tables",
                    array(
                        'type'        => 'error',
                        'caps'        => 'manage_woocommerce',
                        'message'     => sprintf(
                            '<p><strong>%s</strong> - %s: %s</p>',
                            esc_html( $this->slug ),
                            esc_html__( 'The following tables are missing: ', 'oblak-plugin-installer' ),
                            implode( ', ', $missing_tables ),
                        ),
                        'dismissible' => false,
                        'persistent'  => true,
                    ),
                    true
                );
            }
        } else {
            if ( $modify_notice && $this->show_admin_notices ) {
                Admin_Notice_Manager::get_instance()->remove_notice( "{$this->slug}_missing_tables", true );
            }
            update_option( "{$this->slug}_db_version", $this->db_version );
        }

        return $missing_tables;
    }

    /**
     * Creates the default plugin options, if needed
     */
    public function create_options() {
    }

    /**
     * Creates the default roles for the plugin.
     */
    public function create_roles() {
    }

    /**
     * Sets up the plugin environment
     *
     * CPT registration, taxonomies, etc.
     */
    public function setup_environment() {
    }

    /**
     * Creates terms for the plugin.
     */
    public function create_terms() {
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

        $file_name = '';
        if ( $this->needs_db_update() ) {
            $next_scheduled_date = as_next_scheduled_action( "{$this->slug}_run_update_callback", null, "{$this->slug}-db-updates" );

            //phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $next_scheduled_date || ! empty( $_GET[ "do-update_{$this->slug}" ] ) ) {
                $file_name = 'update-in-progress';
            } else {
                $file_name = 'update-needed';
            }
        } else {
            $file_name = 'update-complete';
        }

        $file = $this->get_template_file( $file_name );

        $file_args = array(
            'plugin_name'    => $this->name,
            'plugin_slug'    => $this->slug,
            'scheduler_url'  => "tools.php?page=action-scheduler&s={$this->slug}_run_update&status=pending",
            'update_url'     => '',
            'how_to_update'  => '#',
            'cli_update_faq' => '#',
        );

        /**
         * Filters the template variables for the admin update notice.
         *
         * @param array  $file_args   Template variables.
         * @param string $plugin_slug Plugin slug.
         * @param string $file_name   Template name.
         *
         * @since 1.0.0
         */
        $file_args = apply_filters( "{$this->slug}_update_notice_args", $file_args, $file_name );

        Admin_Notice_Manager::get_instance()->add_notice(
            "{$this->slug}_update_notice",
            array(
                'type'        => 'info',
                'caps'        => 'manage_options',
                'message'     => $file,
                'file_args'   => $file_args,
                'dismissible' => 'update-complete' === $file ? true : false,
                'persistent'  => false,
            ),
            true
        );
    }

    /**
     * Get the template file for the admin update notice.
     *
     * @param  string $template_name Template name.
     * @return string                Template file path.
     */
    final protected function get_template_file( $template_name ) {
        $default_path = dirname( __DIR__ ) . "/templates/notice-{$template_name}.php";

        /**
         * Filters the template path for the plugin.
         *
         * @param string $default_path  Default template path.
         * @param string $template_name  Template name.
         *
         * @since 1.0.0
         */
        return apply_filters( "{$this->slug}_get_update_notice_template_file", $default_path, $template_name );
    }

    /**
     * Update plugin version to current.
     */
    final public function update_plugin_version() {
        update_option( "{$this->slug}_version", $this->version );
    }

    /**
     * Update DB version to current.
     *
     * @param string|null $version New plugin DB version or null.
     */
    final public function update_db_version( $version = null ) {
        $version = $version ?? $this->db_version;
        if ( '0.0.0' !== $version ) {
			update_option( "{$this->slug}_db_version", is_null( $version ) ? $this->version : $version );
        }
    }

    /**
     * See if we need to show or run database updates during install.
     */
    final protected function maybe_update_db_version() {
        if ( $this->needs_db_update() ) {
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
     * Is a DB update needed?
     *
     * @return boolean
     */
    final public function needs_db_update() {
        $current_db_version = get_option( "{$this->slug}_db_version", null );
        $updates            = $this->get_db_update_callbacks();
        $update_versions    = array_keys( $updates );
        usort( $update_versions, 'version_compare' );

        return ! is_null( $current_db_version ) && version_compare( $current_db_version, end( $update_versions ), '<' );
    }

    /**
     * Push all needed DB updates to the queue for processing.
     */
    final public function update() {
        $current_db_version = get_option( "{$this->slug}_db_version" );
        $loop               = 0;

        foreach ( $this->get_db_update_callbacks() as $version => $update_callbacks ) {
            if ( version_compare( $current_db_version, $version, '<' ) ) {
                foreach ( $update_callbacks as $update_callback ) {
                    as_schedule_single_action(
                        time() + $loop,
                        "{$this->slug}_run_update_callback",
                        array(
                            'update_callback' => $update_callback,
                        ),
                        "{$this->slug}-db-updates"
                    );
                    ++$loop;
                }
            }
        }
    }

    /**
     * Get file path for the file that contains the update callbacks.
     *
     * @return string
     */
    protected function get_update_functions_file() {
        return '';
    }

    /**
     * Run an update callback when triggered by ActionScheduler.
     *
     * @param string $update_callback Callback name.
     */
    final public function run_update_callback( $update_callback ) {
        if ( '' !== $this->get_update_functions_file() ) {
            include_once $this->get_update_functions_file();
        }

        if ( is_callable( $update_callback ) ) {
            $this->update_callback_start( $update_callback );
            $result = (bool) call_user_func( $update_callback );
            $this->update_callback_end( $update_callback, $result );
        }
    }

    /**
     * Triggered when a callback will run.
     *
     * @param string $callback Callback name.
     */
    final protected function update_callback_start( $callback ) { // phpcs:ignore
        maybe_define_constant( strtoupper( $this->slug ) . '_UPDATING', true );
    }

    /**
     * Triggered when a callback has ran.
     *
     * @param string $callback Callback name.
     * @param bool   $result Return value from callback. Non-false need to run again.
     */
    final protected function update_callback_end( $callback, $result ) {
        if ( $result ) {
            as_schedule_single_action(
                "{$this->slug}_run_update_callback",
                array(
                    'update_callback' => $callback,
                ),
                "{$this->slug}-db-updates"
            );
        }
    }

    /**
     * Install actions when a update button is clicked within the admin area.
     *
     * This function is hooked into admin_init to affect admin only.
     */
    final public function install_actions() {
        if ( ! empty( $_GET[ "do_update_{$this->slug}" ] ) ) { // WPCS: input var ok.
            check_admin_referer( "{$this->slug}_db_update", "{$this->slug}_db_update_nonce" );
            $this->update();
            $this->add_admin_update_notice();
        }
    }

    /**
     * Get list of DB update callbacks.
     *
     * @since  3.0.0
     * @return array
     */
    final public function get_db_update_callbacks() {
        return $this->db_updates;
    }

    /**
     * Adds a plugin update command to WP-CLI
     */
    public function register_commands() {
        WP_CLI::add_command( "{$this->slug} update", array( $this, 'cli_update' ) );

        if ( $this->has_db_tables ) {
            WP_CLI::add_command( "{$this->slug} verify_tables", array( $this, 'cli_verify_tables' ) );
        }
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
    final public function cli_update( $args = array(), $assoc_args = array() ) {
        $assoc_args = wp_parse_args(
            $assoc_args,
            array(
				'force' => false,
				'from'  => null,
            )
        );

        global $wpdb;

        $wpdb->hide_errors();

        if ( '' !== $this->get_update_functions_file() ) {
            include_once $this->get_update_functions_file();
        }

        $current_db_version = $assoc_args['from'] ??
            ( $assoc_args['force'] ? '0.0.0' : get_option( "{$this->slug}_db_version", '0.0.1' ) );

        $update_count     = 0;
        $callbacks        = $this->get_db_update_callbacks();
        $callbacks_to_run = array();

        foreach ( $callbacks as $version => $update_callbacks ) {
            if ( version_compare( $current_db_version, $version, '<' ) ) {
                foreach ( $update_callbacks as $update_callback ) {
                    $callbacks_to_run[] = $update_callback;
                }
            }
        }

        if ( empty( $callbacks_to_run ) ) {
            // Ensure DB version is set to the current plugin version to match WP-Admin update routine.
            $this->update_db_version();
            /* translators: %s Database version number */
            WP_CLI::success( sprintf( __( 'No updates required. Database version is %s', 'oblak-plugin-installer' ), $current_db_version ) );
            return;
        }

        /* translators: 1: Number of database updates 2: List of update callbacks */
        WP_CLI::log( sprintf( __( 'Found %1$d updates (%2$s)', 'oblak-plugin-installer' ), count( $callbacks_to_run ), implode( ', ', $callbacks_to_run ) ) );

        $progress = make_progress_bar( __( 'Updating database', 'oblak-plugin-installer' ), count( $callbacks_to_run ) );

        foreach ( $callbacks_to_run as $update_callback ) {
            call_user_func( $update_callback );
            $result = false;
            while ( $result ) {
                $result = (bool) call_user_func( $update_callback );
            }
            ++$update_count;
            $progress->tick();
        }

        $progress->finish();

        Admin_Notice_Manager::get_instance()->remove_notice( "{$this->slug}_update_notice", true );

        /* translators: 1: Number of database updates performed 2: Database version number */
        WP_CLI::success( sprintf( __( '%1$d update functions completed. Database version is %2$s', 'oblak-plugin-installer' ), absint( $update_count ), get_option( "{$this->slug}_db_version" ) ) );
    }

    /**
     * Runs the DB verification routine and outputs the results to the CLI.
     *
     * ## OPTIONS
     *
     * [--create]
     * : Create the missing tables.
     */
    final public function cli_verify_tables( $args = array(), $assoc_args = array() ) {
        $results = $this->verify_base_tables();

        if ( empty( $results ) ) {
            WP_CLI::success( __( 'All database tables are up to date.', 'oblak-plugin-installer' ) );
            return;
        }

        WP_CLI::warning( __( 'The following database tables are out of sync with the schema:', 'oblak-plugin-installer' ) );

        foreach ( $results as $table ) {
            /* Translators: 1: table name, 2: result */
            WP_CLI::log( sprintf( __( 'Table %1$s: %2$s', 'oblak-plugin-installer' ), $table, __( 'Missing', 'oblak-plugin-installer' ) ) );
        }

        if ( ! $assoc_args['create'] ) {
            WP_CLI::line( __( 'Run the command again with --create to create the missing tables.', 'oblak-plugin-installer' ) );
            return;
        }

        WP_CLI::line( __( 'Creating missing tables...', 'oblak-plugin-installer' ) );
        $this->verify_base_tables( false, true );

        $results = $this->verify_base_tables();

        if ( empty( $results ) ) {
            WP_CLI::success( __( 'All database tables are up to date.', 'oblak-plugin-installer' ) );
            return;
        }

        WP_CLI::error( __( 'There was an error creating the missing tables.', 'oblak-plugin-installer' ) );
    }
}

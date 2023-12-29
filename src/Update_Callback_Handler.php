<?php
/**
 * Update_Callback_Handler class file.
 *
 * @package Plugin Installer
 */

namespace Oblak\WP;

use Automattic\Jetpack\Constants;
use ReflectionClass;
use ReflectionMethod;

/**
 * Handles the update callbacks for a plugin.
 *
 * @since 2.0.0
 */
abstract class Update_Callback_Handler {
    /**
     * The slug for the plugin.
     *
     * @var string
     */
    protected string $slug = '';

    /**
     * Array of plugin update callback functions.
     *
     * @var array<string, array>
     */
    protected array $callbacks = array();

    /**
     * Array of completed plugin update callback functions.
     *
     * @var array<string, array>
     */
    protected array $completed = array();

    /**
     * Initializes the update callback stack.
     *
     * @param string $slug The namespace for the plugin.
     */
    final public function init( string $slug ) {
        $this->slug      = $slug;
        $this->callbacks = $this->get_callbacks();
        $this->completed = get_option( "completed_updates_{$this->slug}", array() );
    }

    /**
     * Get the plugin update callback functions.
     *
     * @return array
     */
    final protected function get_callbacks(): array {
        $callbacks = array();
        $methods   = ( new ReflectionClass( $this ) )->getMethods( ReflectionMethod::IS_PUBLIC );

        foreach ( $methods as $method ) {
            $meta = $this->get_method_metadata( $method );

            if ( empty( $meta['version'] ) ) {
                continue;
            }

            $callbacks[ $meta['version'] ] ??= array();
            $callbacks[ $meta['version'] ][] = wp_parse_args(
                $meta,
                array(
					'priority' => 10,
					'redoable' => false,
					'method'   => $method->getName(),
                ),
            );
        }

        uksort( $callbacks, 'version_compare' );
        array_walk( $callbacks, fn( &$v ) => usort( $v, fn( $a, $b ) => $a['priority'] <=> $b['priority'] ) );

        return $callbacks;
    }

    /**
     * Get the metadata for a method.
     *
     * @param  ReflectionMethod $method The method to get the metadata for.
     * @return array<string, mixed>     The metadata for the method.
     */
    final protected function get_method_metadata( ReflectionMethod &$method ): ?array {
        $doc = $method->getDocComment();
        if ( ! $doc ) {
            return null;
        }

        preg_match_all( '/@([a-z]+?)\s+(.*?)\n/i', $doc, $metadata );

        if ( ! isset( $metadata[1] ) || 0 === count( $metadata[1] ) ) {
            return array();
        }

        preg_match_all( '/\s\*\s([A-Z].+)/', $doc, $details );

        return array_filter(
            array_merge(
                array( 'details' => $details[1][0] ?? '' ),
                array_combine(  // Combine the annotations with their values.
                    array_map( 'trim', $metadata[1] ), // Trim the keys.
                    array_map( 'trim', $metadata[2] ) // Trim the values.
                ),
            ),
            fn( $v, $k ) => in_array( $k, array( 'version', 'details', 'priority', 'redoable' ), true ) && '' !== $v,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Checks if the plugin needs to be updated.
     *
     * @param  string|null $current_ver The current version of the plugin.
     * @return bool                     Whether the plugin needs to be updated.
     */
    final public function needs_update( ?string $current_ver = null ): bool {
        $current_ver ??= get_option( "{$this->slug}_db_version", '0.0.0' );
        return ! empty( $this->get_needed_update_callbacks( $current_ver ) );
    }

    /**
     * Returns the needed update callbacks for the given version.
     *
     * @param  string $version Minimum update version.
     * @param  bool   $force   Whether to force the update.
     * @return array
     */
    final public function get_needed_update_callbacks( string $version, bool $force = false ): array {
        $needed = array_filter(
            $this->callbacks,
            fn( $v ) => version_compare( $v, $version, '>' ),
            ARRAY_FILTER_USE_KEY
        );

        return array_filter(
            array_merge( ...array_values( $needed ) ),
            fn( $c ) => $force || ! in_array( $c['method'], $this->completed, true ) || $c['redoable'],
        );
    }

    /**
     * Updates the plugin.
     *
     * @param string $version The version to update from.
     */
    final public function update( string $version ) {
        $callbacks = $this->get_needed_update_callbacks( $version );

        foreach ( $callbacks as $loop => $callback ) {
            $this->schedule_update( $callback['method'], $loop );
        }
    }

    /**
     * Schedules an update for the given callback method.
     *
     * @param  string $callback_method The callback method to run.
     * @param  int    $loop            Delay in seconds.
     */
    final protected function schedule_update( string $callback_method, int $loop = 0 ) {
        as_schedule_single_action(
            time() + $loop,
            "{$this->slug}_run_update_callback",
            array(
                'update_callback' => $callback_method,
            ),
            "{$this->slug}-db-updates"
        );
    }

    /**
     * Run the callback method
     *
     * @param  string $callback_method The callback method to run.
     */
    final public function run_update_callback( string $callback_method ): bool {
        if ( ! method_exists( $this, $callback_method ) ) {
            return false;
        }

        $this->before_update_callback( $callback_method );

        try {
            $result = $this->{"$callback_method"}();

            $this->after_update_callback( $callback_method, ! is_wp_error( $result ) );

            return true;
        } catch ( \Throwable $th ) {
            if ( Constants::is_true( 'WP_CLI' ) ) {
                \WP_CLI::error( $th->getMessage() );
            }
            $this->after_update_callback( $callback_method, false );

            return false;
        }
    }

    /**
     * Runs before the update callback method is run.
     *
     * @param  string $callback_method The callback method to run.
     */
    final protected function before_update_callback( string $callback_method ) {
        Constants::set_constant( str_replace( '-', '_', strtoupper( $this->slug ) . '_UPDATING' ), true );

        /**
         * Fires before the update callback method is run.
         *
         * @param string $callback_method The callback method to run.
         * @since 2.0.0
         */
        do_action( "{$this->slug}_before_update_callback", $callback_method );
    }

    /**
     * Runs after the update callback method is run.
     *
     * @param  string $callback_method The callback method to run.
     * @param  bool   $result          Whether the callback method ran successfully.
     */
    final protected function after_update_callback( string $callback_method, bool $result ) {
        if ( ! $result ) {
            $this->schedule_update( $callback_method, 1 );
        }

        $this->maybe_update_db_version( $callback_method );
        $this->mark_as_completed( $callback_method );
    }

    /**
     * Maybe update the database version.
     *
     * Checks if the callback is the last callback for a version and updates the database version if it is.
     *
     * @param  string $callback_method The callback method to run.
     */
    final public function maybe_update_db_version( string $callback_method ) {
        $version = $this->get_callback_version( $callback_method );

        if ( $version ) {
            $this->update_db_version( $version );
        }
    }

    /**
     * Get the version for a callback method.
     *
     * @param  string $callback_method The callback method to run.
     * @return string|null             The version for the callback method.
     */
    final protected function get_callback_version( $callback_method ): ?string {
        foreach ( $this->callbacks as $version => $callbacks ) {
            $last_callback = end( $callbacks );

            if ( ( $last_callback['method'] ?? '' ) === $callback_method ) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Update the database version.
     *
     * @param  string $version The version to update to.
     */
    final public function update_db_version( string $version ) {
        update_option( "{$this->slug}_db_version", $version );
    }

    /**
     * Mark a callback method as completed.
     *
     * @param  string $callback_method The callback method to run.
     */
    final protected function mark_as_completed( string $callback_method ) {
        $this->completed[] = $callback_method;

        update_option( "completed_updates_{$this->slug}", array_values( array_unique( $this->completed ) ) );
    }
}

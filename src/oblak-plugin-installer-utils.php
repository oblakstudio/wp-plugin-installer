<?php
/**
 * Utility functions
 *
 * @package Plugin Installer
 * @subpackage Utils
 */

namespace Oblak\WP;

/**
 * Define a constant if it is not already defined.
 *
 * @since 3.0.0
 *
 * @param string $name  Constant name.
 * @param mixed  $value Value.
 */
function maybe_define_constant( $name, $value ) {
    if ( ! defined( $name ) ) {
        define( $name, $value );
    }
}

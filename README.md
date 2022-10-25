<div align="center">

# 📦 WordPress plugin installer / activator
## Simplifies the installation and activation of WordPress plugins.

![Packagist Version](https://img.shields.io/packagist/v/oblak/wp-plugin-installer)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/oblak/wp-plugin-installer/php)
[![semantic-release: angular](https://img.shields.io/badge/semantic--release-angular-e10079?logo=semantic-release)](https://github.com/semantic-release/semantic-release)

![Code Climate maintainability](https://img.shields.io/codeclimate/maintainability/oblakstudio/wp-plugin-installer)
[![Release](https://github.com/oblakstudio/wp-plugin-installer/actions/workflows/release.yml/badge.svg)](https://github.com/oblakstudio/wp-plugin-installer/actions/workflows/release.yml)

![GitHub](https://img.shields.io/github/license/oblakstudio/wp-plugin-installer)
![Packagist Downloads](https://img.shields.io/packagist/dm/oblak/wp-plugin-installer)

</div>

## Highlights
 * Based on [WooCommerce](https://woocommerce.com) installation and activation process.
 * Automatically updates plugin and database schema versions.
 * Handles database table creation and updates (Schema enforcement).
 * Provides [WP-CLI](https://wp-cli.org) commands for manual updates, and database table creation / verification.
 * Easily extendable

## Installation

We officially support installing via composer only

### Via composer
```bash
composer require oblak/wp-plugin-installer
```

## Basic Usage

``Base_Plugin_Installer`` is an **abstract** singleton class which can be extended to create a plugin installer class. The class is responsible for installing and activating the plugin, and updating the plugin and database schema versions.
You need to extend it and implement the ``set_defaults()`` method, which is responsible for setting the default values for the class.

If your plugin needs non-wp database tables, you need to implement the ``get_schema()`` method, and set the ``has_tables`` property to ``true``, so that the installer can create and update the tables.

Class depends on [Action Scheduler](https://actionscheduler.org) for running update callbacks in the background.
If your plugin uses Action Scheduler, or depends on an another plugin, which has Action Scheduler, you can skip the activation step.

### 1. Define your installer class

```php
<?php
namespace Vendor\My_Plugin;

use Oblak\WP\Base_Plugin_Installer;

class My_Plugin_Installer extends Base_Plugin_Installer {

  /**
   * Singleton instance
   *
   * Since we're inheriting from a singleton class, we need to define this property.
   *
   * @var My_Plugin_Installer
   */
  protected static $instance;

  /**
   * Set the installer defaults.
   */
  protected function set_defaults() {
    $this->name       = 'My Plugin'; // Plugin name.
    $this->slug       = 'my-plugin'; // Plugin slug.
    $this->version    = '1.0.0';     // Plugin version (current).
    $this->db_version = '1.0.0';     // Database schema version (current).
    $this->has_tables = true;        // Does the plugin have database tables?
  }

  /**
  * Get the database schema.
  *
  * @return string The database schema.
  */
  protected function get_schema() {
    global $wpdb;

      $collate = '';

      if ( $wpdb->has_cap( 'collation' ) ) {
          $collate = $wpdb->get_charset_collate();
      }

    return
    "
    CREATE TABLE `{$wpdb->prefix}my_plugin_table` (
      ID bigint(20) NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      created_at datetime NOT NULL,
      PRIMARY KEY  (ID)
    ) {$collate}
    ";
  }

}
```

### 2. Action Scheduler activation
```php
require_once __DIR__ . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
```

### 3. Include the autoload file
```php
require_once __DIR__ . 'vendor/autoload.php';
```

### 4. Instantiate the installer class
```php
<?php

use Vendor\My_Plugin\My_Plugin_Installer;

My_Plugin_Installer::get_instance()->init();
```

## Advanced Usage

Covered in the [documentation](https://plugin-installer.wp.rs).

## Contributing

Contributions are welcome from everyone. We have [contributing guidelines](CONTRIBUTING.md) to help you get started.

## Credits and special thanks

This project is maintained by [Oblak Studio](https://oblak.studio).  
Special thanks goes to good people at [Automattic](https://automattic.com) for creating [WooCommerce](https://woocommerce.com) on whose installer this one is based upon, and [Action Scheduler](https://actionscheduler.org), which enables us to run update callbacks in the background.

## License

This project is licensed under the [GNU General Public License v2.0](LICENSE).



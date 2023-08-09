# Quick start

## Installation

We officially support installing via composer only

### Via composer
```bash
composer require oblak/wp-plugin-installer
```

## Usage

``Base_Plugin_Installer`` is an **abstract** singleton class which can be extended to create a plugin installer class. The class is responsible for installing and activating the plugin, and updating the plugin and database schema versions.
You need to extend it and implement the ``set_defaults()`` method, which is responsible for setting the default values for the class.

?> If your plugin needs non-wp database tables, you need to implement the ``get_schema()`` method, and set the ``has_db_tables`` property to ``true``, so that the installer can create and update the tables.


### Defining your installer class

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
    $this->has_db_tables = true;        // Does the plugin have database tables?
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

### Initializing action scheduler

Class depends on [Action Scheduler](https://actionscheduler.org) for running update callbacks in the background.
If your plugin uses Action Scheduler, or depends on an another plugin, which has Action Scheduler, you can skip this step.

```php
require_once __DIR__ . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
```

### Initializing your installer class

```php
<?php

require_once __DIR__ . 'vendor/autoload.php';

use Vendor\My_Plugin\My_Plugin_Installer;

$installer = My_Plugin_Installer::get_instance();
$installer->init();
```

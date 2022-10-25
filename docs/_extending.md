# Extending the installer

``Base_Plugin_Installer`` class does the bare minimum by itself. It's meant to be extended and customized to fit your needs.
At bare minimum you can extend the class and implement the ``set_defaults()`` method, which is responsible for setting the default values for the class.
This will only get you the plugin version tracking. If you want to track database schema version, you need to implement the ``get_schema()`` method, and set the ``has_tables`` property to ``true``.

## Database schema enforcement

The installer class provides a way to enforce the database schema. This means that the installer will automatically create the database tables, and update them when the plugin is updated.

In order to utilize this feature, you need to implement the ``get_schema()`` method, and set the ``has_tables`` property to ``true``.

?> Schema enforcement is based on the [WooCommerce](https://woocommerce.com) schema enforcement. It uses the ``dbDelta()`` function to create and update the database tables.

Function ``get_schema()`` should return the database schema as a string. The schema should be written in the same format as the ``dbDelta()`` function expects.

If you're not familiar with the ``dbDelta()`` function, you can read more about it [here](https://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table).

### Example:
```php
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
```

?> We're using the global ``$wpdb`` variable to get the database prefix, and the ``get_charset_collate()`` function to get the database collation.

## Admin notices

The installer class provides a way to display admin notices. By default Plugin Installer will display a notice only when the schema creation fails, or when there are pending updates.

?> The admin notices are displayed only on the admin pages, and only to users with ``manage_options`` capability.

Customizing the admin notices is covered in more details in the [Admin notices](admin_notices.md) section.

## Customizing the installation process

If you need additional installation steps, you need to override the class constructor, hook into the installer process and call the parent constructor.


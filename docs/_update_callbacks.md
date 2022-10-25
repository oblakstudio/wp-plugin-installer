# Update callbacks

Update callbacks are mostly used to update the database schema. But you can use them for any other purpose as well.

## Setting up update callbacks

Class has a defined property ``$db_updates``. This property is an array of update callbacks. Each callback is a method in the class. The key of the array is the version number, and the value is the name of the method.

```php
protected $db_updates = array(
  '1.0.1' => array (
    'run_my_update',
    'update_version_101'
  ),
  '2.0.0' => array (
    'run_my_other_update',
    'update_version_200'
  ),
);
```

## Defining update callbacks

Since class is namespaced, and update callbacks can be numerous, we assume they will be defined in a separate file.  
Since this file is only needed during the update process (and not during the plugin's normal operation), we will include it only when needed.

In order to do that, we will use the `get_update_callbacks_file()` method. This method will return the path to the file, and if the file doesn't exist, it will return `false`.

```php
protected function get_update_functions_file() {
  return '/some/path/to/my/update/functions.php';
}
```

By default, this method returns an empty string. This means that the plugin will not have any update callbacks - so you must override this method.

!> For every version number in the `$db_updates` array, you need to define a function which will update the database schema version to that version number.

```php
use Vendor\My_Plugin\My_Plugin_Installer;

function update_my_plugin_to_version_200() {
  My_Plugin_Installer::get_instance()->update_db_version( '2.0.0' );
}
```

## Running update callbacks

Upon plugin update, the plugin will check if the database schema version is lower than the plugin version. If it is, the plugin will add the update callbacks to the action scheduler queue.  
The action scheduler will run the callbacks in the background, and will update the database schema version after all callbacks have been run.

By default this will display an admin notice to the user. If you want to disable this, you can customize the notification behavior. [Show me how](_admin_notices.md)

Update callbacks can be run manually via WP-CLI as well, using the `wp $plugin_slug update` command.

!> If you're not sure if the user will have the Action Scheduler plugin installed, you must include the Action Scheduler library in your plugin. It is added as a composer dependency, so you can use it in your plugin.


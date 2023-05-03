# Configuration

Class configuration is done by overriding the `set_defaults()` method. This method is called by the constructor, so you can safely use `$this->` in it.

```php
protected function set_defaults() {
  $this->name          = 'My Plugin';
  $this->slug          = 'my-plugin';
  $this->version       = '1.0.0';    
  $this->db_version    = '1.0.0';    
  $this->has_db_tables = true;       
}
```
## name

The plugin name.  
This is used mainly for Admin notices

?> This can be safely changed at any time.

## slug

The plugin slug.  
This is used for the plugin's option names, and hooks in the installer class

!> Changing the slug after the plugin has been installed will cause the plugin to be reinstalled.

## version

The plugin version.  
This is used in order to determine if the plugin needs to be updated.

?> You can define this either by using an external constant, or by hardcoding it in the installer class.

## db_version

The database schema version.
Database schema version is used in order to determine if the database schema needs to be updated.
Incrementing this value will run the update callbacks

## has_db_tables

Whether the plugin has database tables.
If this is set to `true`, the installer will create and update the database tables.

?> If you set this to `true`, you need to implement the `get_schema()` method - since by default it returns an empty string.

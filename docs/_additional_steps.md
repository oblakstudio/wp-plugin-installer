# Adding dditional installation steps

We recognize the need to go beyond the creating / managing custom database tables. You may need to create users, define default options, create custom user roles, etc.  
That is why we have added a way to add additional installation steps.

In order to add additional installation steps, you need to override the class constructor, and hook into the installation process.

```php
<?php

namespace Vendor\My_Plugin;

use Oblak\WP\Base_Plugin_Installer;

class My_Plugin_Installer extends Base_Plugin_Installer {

  /**
   * Constructor.
   */
  public function __construct() {
    // Hook into the installation process.
    add_action( 'my_plugin_slug_install', array( $this, 'additional_installation_steps' ) );

    // Call the parent constructor.
    parent::__construct();
  }

  /**
   * Custom installation steps.
   */
  public function additional_installation_steps() {
    // Create users, pages, check for a license key...
  }
    
}
```

You can add as many additional installation steps as you need. But be aware that you are fully responsible for the installation process, and error handling.

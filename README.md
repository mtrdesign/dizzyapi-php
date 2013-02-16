dizzyapi-php
============
This library provides a PHP client for the [DizzyJam.com API](http://www.dizzyjam.com/apidoc/). The _catalogue/_ group of calls is public, and can be used without authentication; all other calls require valid _Auth ID_ and _API Key_. Information on obtaining these credentials can be found in the [API Docs](http://www.dizzyjam.com/apidoc/).

This library supports JSON an API output format.

## Installation
Dizzyapi-PHP requires the CURL PHP extension to be enabled. With this extension enabled you only need to include the _dizzyjam.php_ file in your code.

## Usage
```php

<?
require('./dizzyjam.php');

# Sample unauthenticated call
$api = new Dizzyjam();
$store_info = $api->catalogue->store_info('dizzyjam');

# Add authentication
$api->set_credentials($auth_id, $api_key);
$my_stores = $api->manage->my_stores();
?>

```
## More info
The full list of calls supported by the API, along with their arguments and 
sample output can be found at http://www.dizzyjam.com/apidoc/.




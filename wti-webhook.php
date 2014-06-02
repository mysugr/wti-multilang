<?php

/**
 * Plugin Name: Wti Multilang
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: Wti Multilang.
 * Version: 0.1
 * Author: Martin Wittmann
 * Author URI: http://martinwittmann.at
 * License: GPL2
 */

/**
* WebTranslateIt will make a post request to this file every time a translation has been updated
* We will then update the file storing this translation so the translators won't need to update 
* the wti data manually after making changes
*/

include_once('/home/witti/public/krumo/class.krumo.php');
include_once('wti-multilang.php');

//let's check whether this request is really from wti and actually has some useful data to update
if (!isset($_POST['payload'] || !is_array($_POST['payload'])) {
  exit;
}

$payload = $_POST['payload'];

preg_match("/https:\/\//webtranslateit\.com\/api\/projects\/[^\/]\/files\/", $payload['api_url'], $matches);
mail('martin.wittmann@tww.at', 'sdf', var_export($payload, true));


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

//add_action('wp_nav_menu_objects', 'email_obfuscate_redirect');

//function email_obfuscate_redirect($items, $args = -1) {
//}

include_once('/home/witti/public/krumo/class.krumo.php');

//add_action('init', 'wti_multilang_init');
add_action('rewrite_rules_array', 'wti_multilang_rewrite_rules');
add_filter('home_url', 'wti_multilang_link_url');
add_filter('query_vars', 'wti_multilang_query_vars');

function wti_multilang_query_vars($qv) {
  $qv[] = 'lang';
  return $qv;
}

function wti_multilang_init() {
  global $wp_rewrite;
  $wp_rewrite->flush_rules();
}

function wti_multilang_get_active_languages() {
  return array('en', 'de', 'fr', 'it');
}

function wti_multilang_rewrite_rules($rules) {
  $langs = wti_multilang_get_active_languages();
  $result['(' . implode('|', $langs) . ')/([^/]+)/?'] = 'index.php?lang=$matches[1]&name=$matches[2]';
  $result['(' . implode('|', $langs) . ')/?'] = 'index.php?lang=$matches[1]';
  return $result + $rules;
}

function wti_multilang_get_current_language() {
  static $lang;
  if (empty($lang)) {
    $langs = wti_multilang_get_active_languages();
    $server_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $home_without_scheme = preg_replace('/https?:\/\//', '', wti_multilang_get_home_url());
    $path = str_replace($home_without_scheme, '', $server_url);
    preg_match('/\/(' . implode('|', $langs) . ')/', $path, $matches);
    $lang = count($matches) > 1 ? $matches[1] : wti_multilang_get_default_language();
  }
  return $lang;
}

function wti_multilang_get_home_url() {
  static $home;
  if (empty($home)) {
    $home = get_option('home');
  }
  return $home;
}

function wti_multilang_get_default_language() {
  return 'en';
}

function wti_multilang_link_url($url) {
  $lang = wti_multilang_get_language_data();
  $home = wti_multilang_get_home_url();

  $path = str_replace($home, '', $url);
  $pattern = '/^\/(' . implode('|', $lang['languages']) . ')/';
  $path = preg_replace($pattern, '', $path);

  $result = $home . ($lang['current'] != $lang['default'] ? '/' . $lang['current'] : '') . $path;
  return $result;
}

function wti_multilang_get_language_data() {
  static $result;
  if (empty($result)) {
    $result =  array(
      'current' => wti_multilang_get_current_language(),
      'default' => wti_multilang_get_default_language(),
      'languages' => wti_multilang_get_active_languages(),
    );
  }
  return $result;
}
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

//
include_once('/home/witti/public/krumo/class.krumo.php');
include_once(dirname(__FILE__) . '/settings.php');
 
register_activation_hook(__FILE__, 'wti_multilang_install');

add_action('init', 'wti_multilang_init');
add_action('admin_init', 'wti_multilang_admin_init');
add_action('rewrite_rules_array', 'wti_multilang_rewrite_rules');
add_filter('home_url', 'wti_multilang_link_url');
add_filter('query_vars', 'wti_multilang_query_vars');
add_action('admin_menu', 'wti_multilang_admin_menu');

function wti_multilang_init() {
  //global $wp_rewrite;
  //$wp_rewrite->flush_rules();
}

function wti_multilang_install() {
  $translations = wti_multilang_translations();
  update_option('wtiml_schema_version', $translations->getSchemaVersion());
}

function wti_multilang_api() {
  static $api;
  if (empty($api)) {
    include_once(dirname(__FILE__) . '/api.php');
    $api = new WtiApi(get_option('wtiml_api_key'));
  }
  return $api;
}

function wti_multilang_translations() {
  static $translations;
  if (empty($translations)) {
    include_once(dirname(__FILE__) . '/translations.php');
    $translations = new WtiTranslations();
  }
  return $translations;
}

function wti_multilang_admin_init() {
  register_setting('wtiml', 'wtiml_api_key');
  //add_settings_section('setup', 'Setup', 'wti_multilang_section_setup', 'wti-multilang');
  add_settings_field('wtiml_api_key', 'Api public key', 'wti_multilang_api_key_field', 'wti-multilang', 'default', array('id' => 'wtiml_api_key'));
  wp_register_style('wtiml-admin-css', plugins_url('css/admin.css', __FILE__));
  wp_enqueue_style('wtiml-admin-css');
}

function wti_multilang_query_vars($qv) {
  $qv[] = 'lang';
  return $qv;
}

function wti_multilang_rewrite_rules($rules) {
  $langs = wti_multilang_get_active_languages();
  $result['(' . implode('|', $langs) . ')/([^/]+)/?'] = 'index.php?lang=$matches[1]&name=$matches[2]';
  $result['(' . implode('|', $langs) . ')/?'] = 'index.php?lang=$matches[1]';
  return $result + $rules;
}

function wti_multilang_get_active_languages() {
  return array('en', 'de', 'fr', 'it');
}

function wti_multilang_get_default_language() {
  return 'en';
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

function wti_multilang_link_url($url) {
  $api = wti_multilang_api();
  $languages = $api->getLanguages();
  $current_lang = wti_multilang_get_current_language();
  $home = wti_multilang_get_home_url();

  $path = str_replace($home, '', $url);
  $pattern = '/^\/(' . implode('|', array_keys($languages)) . ')/';
  $path = preg_replace($pattern, '', $path);

  $result = $home . ($current_lang != $languages['default'] ? '/' . $current_lang : '') . $path;
  return $result;
}

function wti_multilang_admin_menu() {
  add_menu_page('Setup', 'WTI Multilang', 'manage_options', 'wti-multilang', 'wti_multilang_page_admin'); 
}

function wti_multilang_page_admin() {
  //TODO add capability check - security

  $data = array(
    'project' => get_option('wtiml_project'),
  );
  $update_data = $data;

  if (isset($_POST['update-translations']) && $_POST['update-translations'] == '1') {
    $api = wti_multilang_api();
    $translations = wti_multilang_translations();
    $result = $translations->saveTranslationsLocally($api->prepareStrings($api->getStrings()));
    $update_data['message'] = array(
      'text' => $result === true ? 'The translations have been updated successfully' : $result,
      'type' => $result === true ? 'updated' : 'error',
    );
  }
  wti_multilang_theme('settings', $data);
  wti_multilang_theme('admin-languages', $data);
  wti_multilang_theme('update-translations', $update_data);
}

function wti_multilang_theme($template, $data = array()) {
  $dir = dirname(__FILE__);
  $filename = $dir . '/templates/' . $template . '.tpl.php';
  if (!file_exists($filename)) {
    return wti_multilang_message('Template not found: ' . $template, 'error');
  }
  include($filename);
}

function wti_multilang_message($message, $type = 'updated') {
  wti_multilang_theme('message', array('message' => $message, 'type' => $type));
}


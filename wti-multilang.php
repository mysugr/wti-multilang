<?php
/**
 * Plugin Name: Wti Multilang
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: Wti Multilang.
 * Version: 0.1
 * Author: Martin Wittmann
 * Author URI: https://github.com/mysugr/wti-multilang
 * License: GPL2
 */

if (!defined('ABSPATH')) {
  exit;
}

include_once(dirname(__FILE__) . '/settings.php');
 
register_activation_hook(__FILE__, 'wti_multilang_install');

add_action('init', 'wti_multilang_init', 1);
add_action('admin_init', 'wti_multilang_admin_init');
add_action('rewrite_rules_array', 'wti_multilang_rewrite_rules');
add_filter('home_url', 'wti_multilang_link_url');
add_filter('query_vars', 'wti_multilang_query_vars');
add_action('admin_menu', 'wti_multilang_admin_menu');
add_action('admin_notices', 'wti_multilang_admin_notices');
add_action('parse_request', 'wti_multilang_parse_request');
add_shortcode('wti', 'wti_multilang_shortcode');

function wti_multilang_init() {
}

function wti_multilang_install() {
  update_option('wtiml_languages', array(
    'default' => 'en',
    'all' => array(),
  ));
  update_option('wtiml_private_api_key', '');
  update_option('wtiml_public_api_key', '');
}

function wti_multilang_api() {
  static $api;
  if (empty($api)) {
    include_once(dirname(__FILE__) . '/api.php');
    $api = new WtiApi(wti_multilang_get_api_key('private'));
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
  register_setting('wtiml', 'wtiml_private_api_key');
  register_setting('wtiml', 'wtiml_public_api_key');

  add_settings_field('wtiml_private_api_key', 'Api private key', 'wti_multilang_api_key_field', 'wti-multilang', 'default', array('id' => 'wtiml_private_api_key'));

  add_settings_field('wtiml_public_api_key', 'Api public key', 'wti_multilang_api_key_field', 'wti-multilang', 'default', array('id' => 'wtiml_public_api_key'));

  wp_register_style('wtiml-admin-css', plugins_url('css/admin.css', __FILE__));
  wp_enqueue_style('wtiml-admin-css');
}

function wti_multilang_admin_notices() {
  if (!wti_multilang_setup_done()) {
    wti_multilang_message('Please add your WebTranslateIt Api key in the <a href="' . admin_url('admin.php?page=wti-multilang') . '">Wti Multilang Settings</a>', 'update-nag');
  }
}

function wti_multilang_get_api_key($type) {
  static $api_keys;
  if (empty($api_keys)) {
    $api_keys = array(
      'private' => get_option('wtiml_private_api_key'),
      'public' => get_option('wtiml_public_api_key'),
    );
  }

  switch ($type) {
    case 'private': return $api_keys['private'];
    case 'all': return array('private' => $api_keys['private'], 'public' => $api_keys['public']);
    default: return $api_keys['public'];
  }
}

function wti_multilang_query_vars($qv) {
  $qv[] = 'lang';
  $qv[] = 'webtranslateit-webhook';
  return $qv;
}

function wti_multilang_rewrite_rules($rules) {
  $result['webtranslateit-webhook'] = 'index.php?webtranslateit-webhook=1';
  return $result + $rules;
}

function wti_multilang_parse_request($wp) {
  if (isset($wp->query_vars['webtranslateit-webhook'])) {
    wti_multilang_webhook_handler();
    wp_die();
  }
}

function wti_multilang_get_current_language() {
  static $lang;
  if (empty($lang)) {
    $langs = get_option('wtiml_languages');
    $server_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $home_without_scheme = preg_replace('/https?:\/\//', '', wti_multilang_get_home_url());
    $path = str_replace($home_without_scheme, '', $server_url);
    preg_match('/\/(' . implode('|', array_keys($langs['all'])) . ')/', $path, $matches);
    $lang = count($matches) > 1 ? $matches[1] : $langs['default'];
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

function wti_multilang_link_url($url, $language = '') {
  $languages = get_option('wtiml_languages');
  if (count($languages) < 1) {
    //only interfere with urls if we actually have languages set up
    return $url;
  }

  if (empty($language)) {
    $language = wti_multilang_get_current_language();
  }
  $home = wti_multilang_get_home_url();

  $path = str_replace($home, '', $url);
  $pattern = '/^\/(' . implode('|', array_keys($languages)) . ')/';
  $path = preg_replace($pattern, '', $path);

  $result = $home . ($language != $languages['default'] ? '/' . $language : '') . $path;
  return $result;
}

function wti_multilang_admin_menu() {
  add_menu_page('Setup', 'WTI Multilang', 'manage_options', 'wti-multilang', 'wti_multilang_page_admin'); 
}

function wti_multilang_page_admin() {
  //TODO add capability check - security

  $data = array(
    'languages' => get_option('wtiml_languages'),
  );
  $update_data = $data;

  if (!wti_multilang_setup_done() && strlen(wti_multilang_get_api_key('private')) > 0) {
    //we seem to have an api key, but have not initialized our data yet.
    wti_multilang_update_wti_data('Wti Multilang is now configured and ready to be used.');
  }
  elseif (isset($_POST['update-translations']) && $_POST['update-translations'] == '1') {
    wti_multilang_update_wti_data();
  }

  wti_multilang_theme('settings', $data);
  wti_multilang_theme('admin-languages', $data);
  wti_multilang_theme('update-translations', $update_data);
}

function wti_multilang_update_wti_data($success_message = 'The translations have been updated successfully.') {
  global $wp_rewrite;
  $wp_rewrite->flush_rules();

  $errors = array();
  $api = wti_multilang_api();
  $lang_result = $api->getLanguages();
  if (!is_array($lang_result)) {
    $errors[] = $lang_result;
  }
  else {
    update_option('wtiml_languages', $lang_result);
  }

  $translations = $api->prepareTranslations($api->getStrings());
  $strings_result = wti_multilang_save_translations_locally($translations);
  if ($strings_result !== true) {
    $errors[] = $strings_result;
  }

  if (count($errors) < 1) {
    wti_multilang_message($success_message, 'updated');
  }
  else {
    wti_multilang_message(implode('<br>', $errors), 'error');
  }
  return count($errors) < 1;
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

function wti_multilang_save_translations_locally($data) {
  $result = true;
  foreach ($data AS $lang => $translations) {
    $filename = dirname(__FILE__) . '/translations/' . $lang . '.json';
    $result = $result && file_put_contents($filename, json_encode($translations)) !== FALSE;
  }
  return $result ? true : 'Wti Multilang: Error writing translations files.';
}

function wti_multilang_setup_done() {
  $api_key = wti_multilang_get_api_key('private');
  $languages = get_option('wtiml_languages');
  return $api_key !== FALSE && strlen($api_key) > 0 && $languages !== false && isset($languages['default']) && isset($languages['all']) && count($languages['all']) > 0;
}

function wti_multilang_shortcode($attrs = array(), $content = '') {
  //[wti]global.save[/wti]
  //[wti attribute]global.save[/wti]
  //[wti]Hello world![/wti]
  return wti_multilang_get_translation($content, isset($attrs['attribute']));
}

function wti_multilang_get_translation($key, $hide_status = true) {
  static $translations;
  if (empty($key)) {
    return '';
  }
  $current_lang = wti_multilang_get_current_language(); 
  if (!isset($translations[$current_lang][$key])) {
    if (empty($translations[$current_lang])) {
      $translations_filename = dirname(__FILE__) . '/translations/' . $current_lang . '.json';
      if (file_exists($translations_filename)) {
        $translations[$current_lang] = json_decode(file_get_contents($translations_filename), true);
      }
    }
  }

  if (isset($translations[$current_lang][$key])) {
    $translation = nl2br($translations[$current_lang][$key]['text']);
    $status = $translations[$current_lang][$key]['status'];
  }
  else {
    $translation = 'Error finding translation for key: ' . $key;
    $status = 'error';
  }

  if ('error' === $status) {
    $output = '<span class="wti-error" data-wtiml="' . $key . '">' . $translation . '</span>';
  }
  elseif ($hide_status || $status != 'proofread') {
    $output = $translation;
  }
  else {
    $output=  '<span class="wti-' . $status . '" data-wtiml="' . $key . '">' . $translation . '</span>';
  }
  return $output;
}

function wti_multilang_webhook_handler() {
  $payload = json_decode(@stripcslashes($_POST['payload']), true);

  //let's check whether this request is really from wti and actually has some useful data to update
  if (!is_array($payload)) {
    exit;
  }

  $api = wti_multilang_api();
  $languages = $api->getLanguages();

  preg_match("/https:\/\/webtranslateit\.com\/api\/projects\/([^\/]+)\/files\//", $payload['api_url'], $matches);
  if (count($matches) < 2 || $matches[1] != wti_multilang_get_api_key('public') || !in_array($payload['locale'], array_keys($languages['all']))) {
    exit;
  }

  $filename = dirname(__FILE__) . '/translations/' . $payload['locale'] . '.json';
  $data = json_decode(file_get_contents($filename), true);
  $key = @$payload['translation']['string']['key'];
  if (is_array($data) && strlen($key) > 0) {
    $data[$key]['text'] = $payload['translation']['text'];
    $data[$key]['status'] = $payload['translation']['status'];
    $data[$key]['version'] = $payload['translation']['version'];
    file_put_contents($filename, json_encode($data));
  }
}

function wti_multilang_get_all_translations($language = '') {
  static $translations;
  if (empty($translations)) {
    $translations =array();
  }

  if (empty($language)) {
    $language = wti_multilang_get_current_language(); 
  }
  if (!isset($translations[$language])) {
    $translations[$language] = array();
    $translations_filename = dirname(__FILE__) . '/translations/' . $language . '.json';
    if (file_exists($translations_filename)) {
      $translations[$language] = json_decode(file_get_contents($translations_filename), true);
    }
    else {
      return wti_multilang_message('Translations file not found: ' . $translations_filename, 'error');
    }
  }
  return $translations[$language];
}

function wti_multilang_get_languages() {
  $langs = get_option('wtiml_languages');
  $result = array(
    $langs['default'],
  );
  foreach ($langs['all'] AS $lang => $title) {
    if ($lang != $langs['default']) {
      $result[] = $lang;
    }
  }
  return $result;
}

function wti_multilang_get_language_urls() {
  $langs = get_option('wtiml_languages');
  $current_url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  $result = array();
  $langs_regex_part = implode('|', array_keys($langs['all']));
  foreach ($langs['all'] AS $lang => $title) {
    $result[$lang] = wti_multilang_link_url($current_url, $lang);
  }
  return $result;
}

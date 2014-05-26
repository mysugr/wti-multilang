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

add_action('init', 'wti_multilang_init');
add_action('admin_init', 'wti_multilang_admin_init');
add_action('rewrite_rules_array', 'wti_multilang_rewrite_rules');
add_filter('home_url', 'wti_multilang_link_url');
add_filter('query_vars', 'wti_multilang_query_vars');
add_action('admin_menu', 'wti_multilang_admin_menu');

function wti_multilang_init() {
  global $wp_rewrite;
  $wp_rewrite->flush_rules();
}

function wti_multilang_admin_init() {
  register_setting('wtiml', 'wtiml_api_key');
  //add_settings_section('setup', 'Setup', 'wti_multilang_section_setup', 'wti-multilang');
  add_settings_field('wtiml_api_key', 'Api public key', 'wti_multilang_api_key_field', 'wti-multilang', 'default', array('id' => 'wtiml_api_key'));
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

function wti_multilang_admin_menu() {
  add_menu_page('Setup', 'WTI Multilang', 'manage_options', 'wti-multilang', 'wti_multilang_page_admin'); 
}

function wti_multilang_page_admin() {
  //TODO add capability check
  $strings = wti_multilang_strings();
  krumo($strings);
  //wti_multilang_retrieve_lang_file();
  //wti_multilang_update_wti_data();
  $data = array(
    'project' => get_option('wtiml_project'),
  );
  //krumo($data);
  wti_multilang_theme('admin-languages', $data);
  wti_multilang_theme('settings', $data);
}

function wti_multilang_theme($template, $data = array()) {
  $dir = dirname(__FILE__);
  $filename = $dir . '/templates/' . $template . '.tpl.php';
  if (!file_exists($filename)) {
    return wti_multilang_error('Template not found: ' . $template);
  }
  include($filename);
}

function wti_multilang_error($message) {
  if (WP_DEBUG) {
    wti_multilang_theme('error', array('message' => $message));
  }
  elseif ('user_error' == $type) {
    wti_multilang_theme('error', array('message' => $message, 'type' => $type));
  }
  return false;
}

function wti_multilang_update_wti_data() {
  $api_key = get_option('wtiml_api_key');
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $api_key . ".json");
  $response = curl_exec($ch);
  $project = json_decode($response, true);
  update_option('wtiml_project', reset($project));
  curl_close($ch);
}

function wti_multilang_retrieve_lang_file() {
  $api_key = get_option('wtiml_api_key');
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $api_key . "/zip_file");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $zip_text = curl_exec($ch);
  curl_close($ch);

  $zip_filename = tempnam(sys_get_temp_dir(), '');
  file_put_contents($zip_filename, $zip_text);
  $zip = new ZipArchive();
  $zip->open($zip_filename);
  $files = array();
  $num_files = $zip->numFiles;
  for ($i=0;$i<$num_files;$i++) {
    $filename = $zip->getNameIndex($i);
    preg_match('/(\.([a-z]{2}))?.json$/i', $filename, $matches);
    $lang = isset($matches[2]) ? $matches[2] : wti_multilang_get_default_language();
    $files[$lang] = wti_multilang_prepare_translation_array(json_decode($zip->getFromIndex($i), true));
  }
  $zip->close();
  unlink($zip_filename);
  krumo($files);
}

function wti_multilang_prepare_translation_array($t, $prefix = '') {
  $result = array();
  if (strlen($prefix) > 0) {
    $prefix .= '.';
  }
  foreach ($t AS $key => $value) {
    if (is_string($value)) {
      $result[$prefix . $key] = $value;
    }
    else {
      $result += wti_multilang_prepare_translation_array($value, $prefix . $key);
    }
  }
  return $result;
}

function wti_multilang_strings($page_id = 1) {
  $api_key = get_option('wtiml_api_key');
  $ch = curl_init();
  //page index is 1 based, so this is the first page
  curl_setopt($ch, CURLOPT_URL, 'https://webtranslateit.com/api/projects/' . $api_key . '/strings.json?locale=de&page=' . $page_id);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);
  $header = substr($response, 0, $info['header_size']);
  $data = json_decode(mb_substr($response, $info['header_size']), true);
  krumo($header);

  preg_match('/\<https:\/\/webtranslateit.com\/api\/projects\/' . $api_key . '\/strings\.json\?page=([0-9]+)\>; rel="last"/', $header, $matches);
  if (count($matches) < 2) {
    return wti_multilang_error('Error parsing last page id from webtranslateit Strings Api!');
  }
  $last_page_id = $matches[1];
  if ($page_id < $last_page_id) {
    $data += wti_multilang_strings($page_id + 1);
  }
  return $data;
}
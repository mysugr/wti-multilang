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

add_filter('locale', 'wti_multilang_locale', 1);
add_action('admin_init', 'wti_multilang_admin_init');
add_action('rewrite_rules_array', 'wti_multilang_rewrite_rules');
add_filter('query_vars', 'wti_multilang_query_vars');
add_action('admin_menu', 'wti_multilang_admin_menu');
add_action('admin_notices', 'wti_multilang_admin_notices');
add_action('parse_request', 'wti_multilang_parse_request');
add_action('wp_before_admin_bar_render', 'wti_multilang_before_toolbar_render');
add_shortcode('t', 'wti_multilang_shortcode_translate');
add_shortcode('if', 'wti_multilang_shortcode_if_language');
// NOTE:
// we needed to remove the home url filter because it basically prevents
// us from using translated urls by removing the home url from every request.
// So /de/presse/downloads returns a http 404 because is sees this page as
// /presse/downloads.
// We worked around this problem by removing the home url filter which
// has the sideeffect though that home_url() does not return a localized url
//add_filter('home_url', 'wti_multilang_link_url');

add_filter('post_link', 'wti_multilang_post_link', 10, 2);
add_filter('page_link', 'wti_multilang_link_url');
add_action('wp_ajax_mysugr_wti_add_text_key', 'wti_multilang_ajax_wti_add_text_key');
add_filter('redirect_canonical', 'wti_multilang_filter_redirect_canonical', 10, 2);

function wti_multilang_filter_redirect_canonical($url, $request) {
  $languages = wti_multilang_get_languages();
  $home_url = home_url();

  $simple_url = preg_replace("/\?.*$/", '', $request);
  foreach ($languages AS $language) {
    if (trim($simple_url, '/') == $home_url . '/' . $language) {
      return false;
    }
  }
  return $url;
}

function wti_multilang_locale($locale) {
  $lang = wti_multilang_get_current_language();
  switch ($lang) {
    case 'de':
      $locale = 'de_DE';
      break;
    case 'en':
      $locale = 'en_US';
      break;
    case 'fr':
      $locale = 'fr_FR';
      break;
    case 'it':
      $locale = 'it_IT';
      break;
  }
  return $locale;
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

  wp_enqueue_script('wtiml-autocomplete', plugins_url('translations/autocomplete-data.js', __FILE__));
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
  $qv[] = 'mysugr_page';
  return $qv;
}

function wti_multilang_rewrite_rules($rules) {
  $new_rules = array(
    'webtranslateit-webhook' => 'index.php?webtranslateit-webhook=1',
  );

  $slug_regex_suffix = '/?(\?\=.*)?$';

  $languages = wti_multilang_get_languages();
  $default_language = wti_multilang_get_default_language();
  $front_page_id = get_option('page_on_front');
  $blog_overview_page_id = get_option('page_for_posts');

  // Add front page in alle language except the default one (there's no lang prefix in this case)
  foreach ($languages AS $language) {
    if ($default_language == $language) {
      continue;
    }
    $new_rules[$language . $slug_regex_suffix] = 'index.php?page_id=' . $front_page_id;
  }

  // Add the blog overview page separately because we need our own pagination (e.g. /diabetes-life/2)
  foreach ($languages AS $language) {
    $slug = mysugrv3_get_localized_url_for_post(get_post($blog_overview_page_id), $language);
    $new_rules[trim($slug, '/') . '/?([0-9]+)?' . $slug_regex_suffix] = 'index.php?page_id=' . $blog_overview_page_id . '&mysugr_page=$matches[1]';
  }

  $pages = get_posts(array(
    'post_type' => 'page',
    'posts_per_page' => -1,
    'post__not_in' => array($front_page_id, $blog_overview_page_id),
  ));
  foreach ($pages AS $page) {
    foreach ($languages AS $language) {
      $slug = mysugrv3_get_localized_url_for_post($page, $language);
      if (false !== $slug) {
        $new_rules[trim($slug, '/') . $slug_regex_suffix] = 'index.php?page_id=' . $page->ID;
      }
    }
  }

  $posts = get_posts(array(
    'post_type' => 'post',
    'posts_per_page' => -1,
    'post_status' => array(
      'publish',
      'private',
    ),
  ));

  foreach ($posts AS $post) {
    $post->language = get_field('language', $post->ID);
    switch ($post->language) {
      case 'en':
        foreach ($languages AS $lang) {
          if ('de' == $lang) {
            // We're showing english posts in the blog pages of all languages
            // except german.
            continue;
          }
          $slug = $lang . '/' . $post->post_name;
          $new_rules[$slug . $slug_regex_suffix] = 'index.php?p=' . $post->ID;
        }
        break;
      case 'de':
      case 'fr':
      case 'it':
        $slug = $post->language . '/' . $post->post_name;
        $new_rules[$slug . $slug_regex_suffix] = 'index.php?p=' . $post->ID;
        break;
      default:
        mysugrv3_log('error', 'mysugrv3_rewrite_rules: no language set for: ', $post);
    }
  }
  return $new_rules + $rules;
}

function wti_multilang_parse_request($wp) {
  /*
    Saving german blog posts as draft and the previewing it caused a 404 error
    because wp got both a pade id and a post id and then tried to query posts
    (meaning a post_type == 'post') with the given page id which results in no
    posts found and therefor a 404 error.

    We work around that problem by unsetting the page id if both a post id and
    pade id are set as query parameters.
    Right now we can't think of any other case where this would/should happen.
  */
  if (isset($wp->query_vars['p']) && isset($wp->query_vars['page_id']) && isset($wp->query_vars['preview'])) {
    unset($wp->query_vars['page_id']);
  }

  if (isset($wp->query_vars['webtranslateit-webhook'])) {
    wti_multilang_webhook_handler();
    wp_die();
  }
}

function wti_multilang_get_current_language() {
  static $lang;
  if (empty($lang)) {
    $langs = get_option('wtiml_languages');

    if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $langs['all'])) {
      // If the lang get parameter is set use it
      $lang = $_GET['lang'];
    }
    else {
      // Try to determine the language from the url path prefix and fallback
      // to the default language if we don't know the given language
      $server_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
      $home_without_scheme = preg_replace('/https?:\/\//', '', wti_multilang_get_home_url());
      $path = str_replace($home_without_scheme, '', $server_url);
      preg_match('/^\/(' . implode('|', array_keys($langs['all'])) . ')/', $path, $matches);
      $lang = count($matches) > 1 ? $matches[1] : $langs['default'];
    }
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

function wti_multilang_post_link($url, $post) {
  if ('post' == $post->post_type) {
    $post_language = get_field('language', $post->ID);
      return wti_multilang_link_url($url, $post_language);
  }
  return wti_multilang_link_url($url);
}

/*
  TODO:
    handle localized urls correctly
    right now only the language prefix is changed for url localization
    but in the case of a localied url slug (e.g. /de/tagebuch vs. /logbook) this would fail
*/
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
  $pattern = '/^\/(' . implode('|', array_keys($languages['all'])) . ')/';
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
  if (!is_array($lang_result) || empty($lang_result)) {
    wti_multilang_message($lang_result, 'error');
    return false;
  }
  else {
    update_option('wtiml_languages', $lang_result);
  }

  $translations = $api->prepareTranslations($api->getStrings());
  if (!is_array($translations) || empty($translations)) {
    wti_multilang_message($translations, 'error');
    return FALSE;
  }
  $strings_result = wti_multilang_save_translations_locally($translations);
  wti_multilang_create_autocomplete_data($translations);
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

function wti_multilang_shortcode_translate($attrs = array(), $content = '') {
  //[wti]global.save[/wti]
  //[wti attribute]global.save[/wti]
  //[wti]Hello world![/wti]
  return wti_multilang_get_translation($content, true, array(), isset($attrs['lang']) ? $attrs['lang'] : '');
}

function wti_multilang_shortcode_if_language($attrs = array(), $content = '') {
  //[if lang="de"]Cont that's only visible in german[/if]
  $current_lang = wti_multilang_get_current_language(); 
  $if_langs = isset($attrs['lang']) ? explode(' ', $attrs['lang']) : array();

  if (empty($if_langs) || in_array($current_lang, $if_langs)) {
    return do_shortcode($content);
  }
  return '';
}

function wti_multilang_get_translation_data($lang = '') {
  static $translations;

  if (empty($lang)) {
    $lang = wti_multilang_get_current_language(); 
  }

  if (!isset($translations[$lang])) {
    $translations_filename = dirname(__FILE__) . '/translations/' . $lang . '.json';
    if (file_exists($translations_filename)) {
      $translations[$lang] = json_decode(file_get_contents($translations_filename), true);
    }
  }
  return $translations[$lang];
}

function wti_multilang_get_translation($key, $hide_status = true, $replacements = array(), $lang = false) {
  static $logged_errors;
  if (empty($logged_errors)) {
    $logged_errors = array();
  }

  $hide_status = true;
  if (empty($key)) {
    return '';
  }

  if (empty($lang)) {
    $lang = wti_multilang_get_current_language();
  }

  $translations = wti_multilang_get_translation_data($lang);

  if (isset($translations[$key]['text']) && !empty($translations[$key]['text'])) {
    $translation = nl2br($translations[$key]['text']);
    $status = $translations[$key]['status'];
    if (!empty($replacements) && is_array($replacements)) {
      foreach ($replacements AS $search => $replace) {
        $translation = str_replace($search, $replace, $translation);
      }
    }
  }
  else {
    if (is_user_logged_in()) {
      $translation = 'Missing wti key: ' . $key;
      $status = 'error';
    }
    else {
      $en_texts = wti_multilang_get_translation_data('en');
      if (isset($en_texts[$key]['text']) && !empty($en_texts[$key]['text'])) {
        $translation = nl2br($en_texts[$key]['text']);
        $status = $en_texts[$key]['status'];
      }
      else {
        $translation = '';
        $status = '';
      }
    }

    if (!in_array($key, $logged_errors)) {
      // Only log one error for each key on a given page.
      $message = 'Url: ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n";
      $message .= 'Wti key: ' . $key;
      mysugrv3_log('error', 'Missing wti key: ' . $key, $message);
      $logged_errors[] = $key;
    }
  }

  $output = $translation;
  wti_multilang_register_translation_usage($key);
  return $output;
}

function wti_multilang_webhook_handler() {
  $payload = json_decode(@stripcslashes($_POST['payload']), true);

  if (!is_array($payload)) {
    exit;
  }

  $api = wti_multilang_api();
  $languages = $api->getLanguages();

  // Let's check whether this request is really from wti and actually has some useful data to update
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

function wti_multilang_before_toolbar_render() {
  global $wp_admin_bar;
  if (is_admin()) {
    return;
  }

  $used_translations = wti_multilang_register_translation_usage();

  if (!empty($used_translations)) {
    $current_language = wti_multilang_get_current_language();
    $first = reset($used_translations);
    $wti_base_url = 'https://webtranslateit.com/en/projects/' . $first['project'] . '/';
    $wp_admin_bar->add_node(array(
      'id' => 'wti-multilang-used-translations',
      'href' => $wti_base_url,
      'title' => __('Translations'),
    ));

    foreach ($used_translations AS $key => $translation) {
      if ('error' == $translation['status']) {
        continue;
      }
      $title = strlen($translation['text']) > 40 ? substr($translation['text'], 0, 40) . '&hellip;' : $translation['text'];
      $title = '"' . $title . '" - ' . $key . ' (' . $translation['status'] . ')';
      $wp_admin_bar->add_node(array(
        'id' => 'wti-multilang-used-translations-' . $translation['id'],
        'parent' => 'wti-multilang-used-translations',
        'href' => $wti_base_url . 'locales/en..' . $current_language . '/strings/' . $translation['id'],
        'title' => $title,
      ));
    }
  }
}

function wti_multilang_register_translation_usage($key = '') {
  static $used_translation_keys;
  if (empty($used_translation_keys)) {
    $used_translation_keys = array();
  }

  if (empty($key)) {
    $all_translations = wti_multilang_get_translation_data();
    $result = array();
    foreach ($used_translation_keys AS $key) {
      if (isset($all_translations[$key])) {
        $result[$key] = $all_translations[$key];
      }
      else {
        $result[$key] = array(
          'text' => '[Translation key not found]',
          'status' => 'error',
          'version' => -1,
        );
      }
    }
    return $result;
  }
  elseif (!in_array($key, $used_translation_keys)) {
    $used_translation_keys[] = $key;
  }
}

function wti_multilang_create_autocomplete_data($translations) {
  $langs = array_keys($translations);
  $json = array(
    'texts' => array(),
    'project' => 0,
    'languages' => $langs,
  );
  foreach ($translations['en'] AS $key => $translation) {
    $translation_data = array();
    foreach ($langs AS $lang) {
      $translation_data[$lang] = $translations[$lang][$key]['text'];
    }
    $translation_data['key'] = $key;
    $json['texts'][$translation['id']] = $translation_data;
    $json['project'] = $translations[$lang][$key]['project'];
  }
  $filename = dirname(__FILE__) . '/translations/autocomplete-data.js';
  file_put_contents($filename, 'var wtimlTranslations=' . json_encode($json)) . ';';
}

function wti_multilang_ajax_wti_autocomplete() {
  $query = strtolower($_GET['term']);
  $search_all = '[all]' == strtolower($_GET['term']);
  $translations = wti_multilang_get_all_translations();
  $results = array();
  foreach ($translations AS $key => $translation) {
    if (empty($translation['text']) || $translation['text'] == 'null') {
      $translation['text'] = '[' . $translation['status'] . ']';
    }
    if ($search_all || strpos(strtolower($translation['text']), $query) !== false || strpos(strtolower($key), $query) !== false) {
      $preview = strlen($translation['text']) > 70 ? substr($translation['text'], 0, 70) . '...' : $translation['text'];
      $results[] = array(
        'value' => $key,
        'label' => $key . ' - "' . $preview . '"',
      );
    }
  }
  //asort($results);
  print json_encode($results);
  die();
}

function wti_multilang_ajax_wti_add_text_key() {
  if (current_user_can('edit_posts')) {
    $api = wti_multilang_api();
    $result = $api->addTextSegment($_POST['key']);
  }
  if (is_array($result) && isset($result['id']) && !empty($result['id'])) {
    // Update our current translation files.
    $languages = wti_multilang_get_languages();
    $all_translations = array();
    foreach ($languages AS $language) {
      $filename = dirname(__FILE__) . '/translations/' . $language . '.json';
      $translations = json_decode(file_get_contents($filename), true);
      $translations[$result['key']] = array(
        'text' => '',
        'status' => $result['status'],
        'id' => $result['id'],
        'version' => 1,
        'project' => $result['project'],
      );
      file_put_contents($filename, json_encode($translations));
      $all_translations[$language] = $translations;
    }
    wti_multilang_create_autocomplete_data($all_translations);
  }
  print json_encode($result);
  die();
}

function wti_multilang_get_default_language() {
  $langs = get_option('wtiml_languages');
  return is_array($langs) ? $langs['default'] : false;
}

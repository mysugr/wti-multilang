<?php

class WtiApi {

  public function __construct($api_key) {
    $this->api_key = $api_key;
  }

  public function getProjectData() {
    static $project;
    if (empty($project)) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . ".json");
      $response = curl_exec($ch);
      $projects = json_decode($response, true);
      //update_option('wtiml_project', reset($project));
      curl_close($ch);
      $project = reset($projects);
    }
    return $project;
  }

  public function getLanguages() {
    static $languages;
    if (empty($languages)) {
      $project = $this->getProjectData();
      $languages = array(
        'all' => array(),
        'default' => $project['source_locale']['code'],
      );
      foreach ($project['target_locales'] AS $locale) {
        $languages['all'][$locale['code']] = $locale['name'];
      }
    }
    return $languages;
  }

  public function getStrings($page_id = 1, $locale = 'all') {
    //if no locale was given, we retrieve strings for all locales
    if ($locale == 'all') {
      $locales = wti_multilang_get_active_languages();
      $data = array();
      foreach ($locales AS $locale) {
        $data[$locale] = $this->getStrings(1, $locale);
      }
      return $data;
    }

    $ch = curl_init();
    //page index is 1 based, so this is the first page
    curl_setopt($ch, CURLOPT_URL, 'https://webtranslateit.com/api/projects/' . $this->api_key . '/strings.json?locale=' . $locale . '&page=' . $page_id);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $header = substr($response, 0, $info['header_size']);
    $data = json_decode(mb_substr($response, $info['header_size']), true);

    preg_match('/\<https:\/\/webtranslateit.com\/api\/projects\/' . $this->api_key . '\/strings\.json\?page=([0-9]+)\>; rel="last"/', $header, $matches);
    if (count($matches) < 2) {
      return wti_multilang_message('Error parsing last page id from webtranslateit Strings Api!', 'error');
    }
    $last_page_id = $matches[1];
    if ($page_id < $last_page_id) {
      $data += $this->getStrings($page_id + 1);
    }
    return $data;
  }

  public function prepareStrings($strings) {
    $result = array();
    foreach ($strings AS $lang => $str) {
      $result[$lang] = array();
      foreach ($str AS $segment) {
        $result[$lang][$segment['key']] = array(
          'text' => $segment['translations']['text'],
          'status' => str_replace('status_', '', $segment['translations']['status']),
          'version' => $segment['translations']['version'],
        );
      }
    }
    return $result;
  }

}

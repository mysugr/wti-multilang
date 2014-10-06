<?php

class WtiApi {

  public function __construct($api_key) {
    $this->api_key = $api_key;
  }

  public function getProjectData() {
    static $project;
    if (empty($project)) {
      $errors = array();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . ".json");
      $response = curl_exec($ch);

      if (false === $response) {
        $errors[] = 'Error when requesting project data from WebTranslateIt Api: ' . curl_error($ch);
      }
      curl_close($ch);
      $projects = json_decode($response, true);

      if (!is_array($projects) || count($projects) < 1) {
        $errors[] = 'Could not find project in WebTranslateIt response.';
      }
      $res = count($errors) > 0 ? implode('<br>', $errors) : reset($projects);
      return $res;
    }
    return $project;
  }

  public function getLanguages() {
    static $languages;
    if (empty($languages)) {
      $project = $this->getProjectData();
      if (!is_array($project)) {
        return 'Wti Multilang: getLanguages, ' . $project;
      }
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
      $languages = get_option('wtiml_languages');
      $data = array();
      foreach ($languages['all'] AS $lang_code => $lang_name) {
        $data[$lang_code] = $this->getStrings(1, $lang_code);
      }
      return $data;
    }

    $errors = array();
    $ch = curl_init();
    //page index is 1 based, so this is the first page
    curl_setopt($ch, CURLOPT_URL, 'https://webtranslateit.com/api/projects/' . $this->api_key . '/strings.json?locale=' . $locale . '&page=' . $page_id);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if (false === $response) {
      $errors[] = 'Wti Multilang getStrings: Error when requesting strings from WebTranslateIt Api: ' . curl_error($ch);
    }

    $header = substr($response, 0, $info['header_size']);
    if (strlen($header) < 20) {
      $errors[] = 'Wti Multilang getStrings: Error parsing pagination info from header.';
    }

    $data = json_decode(mb_substr($response, $info['header_size']), true);
    if (!is_array($data)) {
      $errors[] = 'Wti Multilang getStrings: Could not parse response json from WebTranslateIt.';
    }

    preg_match('/\<https:\/\/webtranslateit.com\/api\/projects\/' . $this->api_key . '\/strings\.json\?page=([0-9]+)\>; rel="last"/', $header, $matches);
    if (count($matches) < 2) {
      $errors[] = 'Wti Multilang getStrings: Error parsing last page id from webtranslateit Strings Api!';
    }

    if (count($errors) < 1) {
      $last_page_id = intval($matches[1]);
      if ($page_id < $last_page_id) {
        $data += array_merge($data, $this->getStrings($page_id + 1, $locale));
      }
      return $data;
    }
    else {
      return implode('<br>', $errors);
    }
  }

  public function prepareTranslations($strings) {
    $result = array();
    foreach ($strings AS $lang => $str) {
      $result[$lang] = array();
      foreach ($str AS $segment) {
        $result[$lang][$segment['key']] = array(
          'text' => $segment['translations']['text'],
          'status' => str_replace('status_', '', $segment['translations']['status']),
          'version' => $segment['translations']['version'],
          'id' => $segment['id'],
          'project' => $segment['project']['id'],
        );
      }
    }
    return $result;
  }

  function addTextSegment($key) {
    $errors = array();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . "/strings");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
      'key' => $key,
      'type' => 'String',
    )));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    return json_decode($response, true);
  }
}

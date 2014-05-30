<?php

class WtiApi {

  public function __construct($api_key) {
    $this->api_key = $api_key;
  }

  public function getJson() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . ".json");
    $response = curl_exec($ch);
    $project = json_decode($response, true);
    //update_option('wtiml_project', reset($project));
    curl_close($ch);
    return reset($project);
  }

/*
  public function getFiles() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . "/zip_file");
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
      $files[$lang] = $this->prepareTranslationsArray(json_decode($zip->getFromIndex($i), true));
    }
    $zip->close();
    unlink($zip_filename);
    krumo($files);
    return $files;
  }
  */

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
      return wti_multilang_error('Error parsing last page id from webtranslateit Strings Api!');
    }
    $last_page_id = $matches[1];
    if ($page_id < $last_page_id) {
      $data += $this->getStrings($page_id + 1);
    }
    return $data;
  }

  /*
  private function prepareTranslationsArray($t, $prefix = '') {
    $result = array();
    if (strlen($prefix) > 0) {
      $prefix .= '.';
    }
    foreach ($t AS $key => $value) {
      if (is_string($value)) {
        $result[$prefix . $key] = $value;
      }
      else {
        $result += $this->prepareTranslationsArray($value, $prefix . $key);
      }
    }
    return $result;
  }
  */
  private prepareStrings($strings) {
    $result = array();
    foreach ($strings AS $lang => $str) {
      $result[$lang] = array();
      foreach ($str AS $segment) {
        $result[$lang][$segment['key']] = array(
          'text' => $segment['translations']['text'],
          'status' => str_replace('status_', '', $segment['translations']['status_']),
          'version' => $segment['translations']['version'],
        );
      }
    }
  }

}

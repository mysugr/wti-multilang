<?php

class WtiTranslations {

  public function saveTranslationsLocally($data) {
    foreach ($data AS $lang => $translations) {
      $filename = dirname(__FILE__) . '/translations/' . $lang . '.json';
      return file_put_contents($filename, json_encode($translations)) !== FALSE && chmod($filename, 0755);
    }
  }
}
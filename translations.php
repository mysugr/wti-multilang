<?php

class WtiTranslations {

  public function updateTranslationData($data) {
    global $wpdb;

  }

  public function getTableName() {
    global $wpdb;
    return $wpdb->prefix . 'wti_translations';
  }

  public function getSchemaVersion() {
    return 1;
  }

  public function createTranslationsTable() {
    global $wpdb;
    $tableName = $this->getTableName();
    $query = "CREATE TABLE $tableName (
      lang VARCHAR(10) NOT NULL,
      key VARCHAR(400) KEY NOT NULL,
      status VARCHAR(50),
      text longtext,
      version mediumint(9)
    )";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($query);
  }
}
<?php

function wti_multilang_section_setup($arg) {
  return;
  print 'section:';
  var_export($arg);
}

function wti_multilang_api_key_field($args) {
  print '<input type="text" name="' . $args['id'] . '" id="' . $args['id'] . '" value="' . get_option($args['id']) . '" />';
}

function wti_multilang_langs_field($args) {
  print '<input type="text" name="wtiml_langs" id="wtiml_langs" value="' . get_option('wtiml_langs') . '" />';
}
<div class="wti-multilang">
  <div class="settings">
    <h2>WTI Multilang Settings</h2>
    <form id="wtiml-settings" action="options.php" method="POST">
<?php
      settings_fields('wtiml');
      do_settings_fields('wti-multilang', 'default');
      do_settings_sections('wti-multilang');
      print '<input type="hidden" name="wtiml_setup_done" value="' . intval(wti_multilang_setup_done()) . '">';
      submit_button(); ?>
    </form>
  </div>
</div>
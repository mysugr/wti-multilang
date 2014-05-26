<?php
  $defaults = array(
    //'post_target' => plugin_dir_url('wti-multilang') . 'form-submissions.php',
  );
  $d = $defaults + $data;
?><div class="wti-multilang">
  <div class="settings">
    <h2>WTI Multilang Settings</h2>
    <form id="wtiml-settings" action="options.php" method="POST">
<?php
      settings_fields('wtiml');
      do_settings_fields('wti-multilang', 'default');
      do_settings_sections('wti-multilang');
      submit_button(); ?>
    </form>
  </div>
</div>
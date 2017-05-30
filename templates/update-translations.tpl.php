<div class="wti-multilang">
  <div class="update-translations">

    <p>To update translations, follow these steps:</p>

    <ul>
      <li>log into the webserver</li>
      <li>traverse into the folder to the <pre>wti-multilang</pre> plugin folder</li>
      <li>cd into the lokalise folder</li>
      <li>execute <pre>./update_wordpress_translations.rb WITH-YOUR-LOKALISE-TOKEN</pre></li>
      <li>check if the generated files are correct (not empty, etc.)</li>
      <li>copy them over to the translations folder</li>
      <li>done</li>
    </ul>

    <form id="wtiml-settings" action="admin.php?page=wti-multilang" method="GET">
<?php
  if (isset($data['message'])) {
    wti_multilang_message($data['message']['text'], $data['message']['type']);
  }
?>
      <input class="button button-primary" disabled="true" type="submit" value="Update data from WTI" />
    </form>
  </div>
</div>

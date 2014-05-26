<div class="langauges">
  <h2>Available Languages</h2>
  <p>The following languages are available:</p>
  <ul>
<?php
    foreach ($data['project']['target_locales'] AS $lang) {
      print '<li>' . $lang['name'] . '</li>';
    }
?>
  </ul>
</div>
<div class="langauges">
  <h2>Available Languages</h2>
<?php
  if (count($data['languages']['all']) > 0) { ?>
    <p>The following languages are available:</p>
    <ul>
  <?php
      foreach ($data['languages']['all'] AS $code => $name) {
        print '<li>' . $name . ($code == $data['languages']['default'] ? ' (is default)' : '') . '</li>';
      }
  ?>
    </ul>
<?php
  }
  else {
    print '<p>There are no languages configured yet.</p>';
  }
?>
</div>
<?php
  $data_default = array(
    'type' => 'updated',
  );
  $d = $data + $data_default;
?><div class="wrap">
  <div class="<?php print $d['type']?>">
    <?php print $d['type'] == 'update-nag' ? $d['message'] : '<p>' . $d['message'] . '</p>'?>
  </div>
</div>
<?php
  $data_default = array(
    'type' => 'wti-error',
  );
  $d = $data_default + $data;

  print '<div class="' . $d['type'] . '">' . $d['message'] . '</div>';
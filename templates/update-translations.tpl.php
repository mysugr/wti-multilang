<div class="wti-multilang">
  <div class="update-translations">
    <form id="wtiml-settings" action="admin.php?page=wti-multilang" method="POST">
<?php
  if (isset($data['message'])) {
    wti_multilang_message($data['message']['text'], $data['message']['type']);
  }
?>    <input type="hidden" name="update-translations" value="1" />
      <input class="button button-primary" type="submit" value="Update data from WTI" />
    </form>
  </div>
</div>
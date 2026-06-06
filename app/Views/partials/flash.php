<?php
use App\Core\Session;
use App\Core\App;
$app = App::instance();
$flashes = Session::pullFlashes();
if (empty($flashes)) return;
?>
<script>
(function(){
  var fs = <?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  if (Array.isArray(fs)) {
    fs.forEach(function(f){
      try { window.EBM && window.EBM.flash(f.type, f.message); } catch(e){}
    });
  }
})();
</script>

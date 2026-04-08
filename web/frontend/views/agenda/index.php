<?php

use frontend\assets\AgendaLaboralAsset;
use yii\web\View;

/** @var array<int|string, string> $tiposDia */

$this->title = 'Agenda laboral';
$this->params['breadcrumbs'][] = $this->title;

AgendaLaboralAsset::register($this);

?>

<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <?= $this->render('_embed', ['tiposDia' => $tiposDia]) ?>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
(function () {
  var root = document.querySelector('[data-native-component="agenda_laboral"]');
  if (root && window.BioenlaceNativeComponents && window.BioenlaceNativeComponents.agenda_laboral) {
    window.BioenlaceNativeComponents.agenda_laboral.init(root);
  }
})();
JS, View::POS_END);
?>

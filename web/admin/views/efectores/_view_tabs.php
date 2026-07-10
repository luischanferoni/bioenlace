<?php

use yii\helpers\Html;

?>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <?= Html::a('Detalles', ['/efectores/view', 'id' => $model->id_efector], ['class' => 'nav-link '.($tab == 'view' ? 'active' : '')]);?>
  </li>
  <li class="nav-item">
    <?= Html::a('Profesionales (PES)', ['/efectores/profesionales', 'id' => $model->id_efector], ['class' => 'nav-link '.($tab == 'profesionales' ? 'active' : '')]);?>
  </li>
  <li class="nav-item">
    <?= Html::a('Licencia', ['/efectores/licencia', 'id' => $model->id_efector], ['class' => 'nav-link '.($tab == 'licencia' ? 'active' : '')]);?>
  </li>
</ul>
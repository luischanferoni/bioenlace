<?php

use yii\helpers\Html;

?>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <?= Html::a('Detalles', ['/efectores/view', 'id' => $model->id_efector], ['class' => 'nav-link '.($tab == 'view' ? 'active' : '')]);?>
  </li>
  <li class="nav-item">
    <?= Html::a('RRHH', ['/efectores/rrhh', 'id' => $model->id_efector], ['class' => 'nav-link '.($tab == 'rrhh' ? 'active' : '')]);?>
  </li>
</ul>
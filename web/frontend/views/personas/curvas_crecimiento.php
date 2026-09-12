<?php

use yii\helpers\Html;
use yii\helpers\Json;

/**
 * @var yii\web\View $this
 * @var array $page {@see \frontend\components\Person\CurvasCrecimientoPageBuilder}
 */

$this->title = 'Persona - Curvas Crecimiento';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-curvas" id="curvas-crecimiento-root" data-curvas-config="<?= Json::htmlEncode($page) ?>">
  <div class="card">
    <div class="card-header">
        Peso para la edad
    </div>
    <div class="card-body">
        <div id="peso_edad"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
        Talla para la edad
    </div>
    <div class="card-body">
        <div id="talla_edad"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
        Perímetro Cefálico para la edad
    </div>
    <div class="card-body">
        <div id="pcefalico_edad"></div>
    </div>
  </div>
  <?php if (!empty($page['showImc'])): ?>
  <div class="card">
    <div class="card-header">
        Indice de Masa Corporal para la edad
    </div>
    <div class="card-body">
        <div id="imc_edad"></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php

use yii\grid\GridView;
use yii\helpers\Html;

?>

<div class="persona-signos-vitales">
  <div class="row g-3 mb-3">
    <div class="col-md-3 col-sm-6">
      <div class="card h-100">
        <div class="card-body p-1">
          <h6 class="card-title mb-2 d-flex align-items-center">
            <?= file_get_contents(Yii::getAlias('@webroot/images/icons/svg_icon_scale.svg')); ?>
            <span class="ms-2">Peso</span>
          </h6>
          <p class="card-text fw-bold mb-1 fs-6">
            <?= $ultimos_sv['peso']['value'] ?> Kg
          </p>
          <small class="text-muted">
            <?= $ultimos_sv['peso']['fecha'] ?>
          </small>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6">
      <div class="card h-100">
        <div class="card-body p-1">
          <h6 class="card-title mb-2 d-flex align-items-center">
            <?= file_get_contents(Yii::getAlias('@webroot/images/icons/svg_icon_ruller.svg')); ?>
            <span class="ms-2">Altura</span>
          </h6>
          <p class="card-text fw-bold mb-1 fs-6">
            <?= $ultimos_sv['talla']['value'] ?> cm
          </p>
          <small class="text-muted">
            <?= $ultimos_sv['talla']['fecha'] ?>
          </small>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card h-100">
            <div class="card-body p-1">
            <h6 class="card-title mb-2 d-flex align-items-center">
                <?= file_get_contents(Yii::getAlias('@webroot/images/icons/svg_icon_wave.svg')); ?>
                <span class="ms-2">IMC</span>
            </h6>
            <p class="card-text fw-bold mb-1 fs-6">
                <?= $ultimos_sv['imc']['value'] ?>
            </p>
            <small class="text-muted">
                <?= $ultimos_sv['imc']['fecha'] ?>
            </small>
            </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6">
      <div class="card h-100">
        <div class="card-body p-1">
          <h6 class="card-title mb-2 d-flex align-items-center">
            <?= file_get_contents(Yii::getAlias('@webroot/images/icons/svg_icon_heartbeat.svg')); ?>
            <span class="ms-2">Tensión Arterial</span>
          </h6>
          <p class="card-text fw-bold mb-1 fs-6">
            <?= $ultimos_sv['ta']['sistolica'] ?> / <?= $ultimos_sv['ta']['diastolica'] ?> mmHg
          </p>
          <small class="text-muted">
            <?= $ultimos_sv['ta']['fecha'] ?>
          </small>
        </div>
      </div>
    </div>
  </div>
  
  <div class="card">
    <div class="card-header py-2">
      <h6 class="mb-0">Últimos datos registrados</h6>
    </div>
    <div class="card-body p-2">
      <?php
      $layout = '{items} {summary}' .
              '<small class="text-muted">Se muestran solo los últimos 10 registros.</small>';
      
      $show_ta = function ($row_data) {
        $tpl_ta = "<p>%s / %s</p>";
        $sistolica = $row_data['ta1_sistolica'];
        $diastolica = $row_data['ta1_diastolica'];
        $str = '';
        if($diastolica != '' && $sistolica != '') {
            $str = sprintf(
                    $tpl_ta, $sistolica, $diastolica,
                    );
            $s2 = $row_data['ta2_sistolica'];
            $d2 = $row_data['ta2_diastolica'];
            if($s2 != '' && $d2 != '') {
                $str .= sprintf(
                    $tpl_ta, $s2, $d2,
                    );
            }
        }
        return $str;
      };
      
      $hide_null = function ($value) {
          return $value == null? '': $value;
      };
      
      ?>
      <?= GridView::widget([
          'dataProvider' => $data_provider,
          'layout' => $layout,
          'tableOptions' => ['class' => 'table table-sm table-striped'],
          'options' => ['class' => 'mb-0'],
          'columns' => [
              [
                'attribute' => 'fecha_atencion',
                'label' => 'Fecha',
                'format' => 'date',
                'contentOptions' => ['class' => 'text-nowrap'],
              ],
              [
                'label' => 'Peso',
                'value' => function ($data) use ($hide_null) {
                    $peso = $hide_null($data['peso']);
                    return $peso ? $peso . ' kg' : '';
                },
                'contentOptions' => ['class' => 'text-center'],
              ],
              [
                'label' => 'Talla',
                'value' => function ($data) use ($hide_null) {
                    $talla = $hide_null($data['talla']);
                    return $talla ? $talla . ' cm' : '';
                },
                'contentOptions' => ['class' => 'text-center'],
              ],
              [
                'label' => 'IMC',
                'value' => function ($data) use ($hide_null) {
                    return $hide_null($data['imc']);
                },
                'contentOptions' => ['class' => 'text-center'],
              ],
              [
                'label' => 'Tensión Arterial',
                'format' => 'raw',
                'value' => function ($data) use ($show_ta) {
                    return $show_ta($data);
                },
                'contentOptions' => ['class' => 'text-center'],
              ],
          ],
      ]); ?>
    </div>
  </div>  
</div>
<?php

use yii\helpers\Html;
use common\models\Persona;
?>
<!-- #BEGIN card regimen -->
<div class="card w-100">
  <div class="card-header">
    <div class="d-flex bd-highlight align-items-center">
      <div class="bd-highlight">
        <h5>Regímenes</h5>
      </div>
    </div>
  </div>
  <div class="card-body">
    <?php if ($regimenes_list): ?>
      <table id="regimenes" class="table table-striped table-bordered detail-view">
        <tbody>
          <?php foreach ($regimenes_list as $regimen): ?>
            <tr>
              <td>
                <?= $regimen->getQueryExtraData('consulta_fecha') ?>
              </td>
              <td>
                <?= $regimen->getConceptTerm() ?>
              </td>
              <td>
                <?php
                $indicaciones = preg_split(
                    '/\R/', 
                    $regimen->indicaciones,
                    -1,
                    PREG_SPLIT_NO_EMPTY);
                $indicaciones = implode('<br>', $indicaciones);
                echo $indicaciones;
                ?>
              </td>
              <td>
                  <?= $regimen->consulta->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON)  ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<!-- #END card regimen -->
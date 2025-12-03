<?php

use yii\helpers\Html;
use common\models\Persona;
?>


<?php #BEGIN card balances
?>
<div class="card w-100">
  <div class="card-header">
    <div class="d-flex bd-highlight align-items-center">
      <div class="bd-highlight">
        <h5>Balance Hídrico</h5>
      </div>
    </div>
  </div>
  <div class="card-body">
    <?php if ($balances_list): ?>
      <table id="balances" class="table table-striped table-bordered detail-view">
        <tbody>
          <?php foreach ($balances_list as $balance): ?>
            <tr>
              <td>
                <?= $balance->fecha ?>
              </td>
              <td>
                <?= $balance->tipo_registro; ?>
              </td>
              <td>
                <?= $balance->getCodigoRegistroDescription(); ?>
              </td>
              <td>
                <?= $balance->hora_inicio ?> -
                <?= $balance->hora_fin ?>
              </td>
              <td>
                <?= $balance->cantidad ?>
              </td>  

            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php #END card evolucion

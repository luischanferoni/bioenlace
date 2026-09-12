<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var array $page {@see \frontend\components\Scheduling\TurnosEsperaPageBuilder}
 */

$this->title = 'Lista de Espera';
$this->params['breadcrumbs'][] = $this->title;
$tieneProfesional = !empty($page['tieneProfesional']);
?>

<?php if ($tieneProfesional): ?>
<div class="row">
  <div class="col-4 text-center">
    <img src="<?= Html::encode($page['logoMinisterioUrl']) ?>" style="height: 55px;" alt=""/>
  </div>
  <div class="col-4">
    <p class="text-center">
          MINISTERIO DE SALUD - PROVINCIA DE SANTIAGO DEL ESTERO<br/>
          BIOENLACE <?= Html::encode($page['nombreEfector']) ?>
      </p>
  </div>
  <div class="col-4 text-center">
    <img src="<?= Html::encode($page['logoSmallUrl']) ?>" style="height: 55px;" alt=""/>
  </div>
</div>
<?php endif; ?>

<div class="row d-flex align-items-center text-center mb-5">
  <div class="card">
    <div class="card-body">
       <div class="row">
          <div class="col-12">
              <h3 class="float-center mt-2 mb-2"><?= Html::encode($page['pageTitle']) ?></h3>
            </div>
       </div>

      <div class="row no-print">
        <div class="col-4">
          <?= Html::a(
              '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>',
              $page['urlFechaAnterior'],
              ['class' => 'btn btn-primary rounded-pill float-end', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'bottom', 'data-bs-original-title' => 'Dia Anterior']
          ) ?>
        </div>

        <div class="col-4 justify-content-center d-flex text-center align-items-center">
          <button id="cal-lista-espera" class="btn btn-sm bg-soft-primary w-25 float-start rounded"
            <?= $page['pesId'] !== null ? 'data-pes="' . (int) $page['pesId'] . '"' : '' ?>
            data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="Buscar por fecha">
            <i class="bi bi-calendar-date" style="font-size: 25px;"></i>
          </button>
        </div>

        <div class="col-4">
          <?php if (!empty($page['mostrarFechaSiguiente'])): ?>
            <?= Html::a(
                '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>',
                $page['urlFechaSiguiente'],
                ['class' => 'btn btn-primary rounded-pill float-start', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'bottom', 'data-bs-original-title' => 'Dia Siguiente']
            ) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($page['emptyMessage'] !== null): ?>
  <h3><?= Html::encode($page['emptyMessage']) ?></h3>
<?php endif; ?>

<?php foreach ($page['cards'] as $card): ?>
  <div class="card">
    <div class="card-body">
      <div class="row justify-content-center d-flex text-center align-items-center">
        <div class="<?= $tieneProfesional ? 'col-4' : 'col-xl-2 col-lg-3 border-end' ?>" style="font-size: <?= $tieneProfesional ? '18px' : '24px' ?>">
          <h3>TURNO</h3>
          <span><h4>#<?= (int) $card['orden'] ?></h4></span>
          <div style="font-size: 17px"><i class="bi bi-clock"></i> <?= Html::encode($card['hora']) ?></div>
        </div>

        <div class="<?= $tieneProfesional ? 'col-4' : 'col-7' ?>">
          <h4 class="mb-2"><?= Html::encode($card['nombrePaciente']) ?></h4>
          <?php if ($card['edad'] !== null): ?>
          <h4 class="mb-2">Edad: <?= (int) $card['edad'] ?> años</h4>
          <?php endif; ?>
          <?php if (!empty($card['esReferencia'])): ?>
            <h4 class="mb-2"><span class="badge bg-info">Referencia</span></h4>
          <?php endif; ?>
          <p>Confirmado: <?= $card['confirmado'] ? '<span class="badge bg-success">Si</span>' : '<span class="badge bg-warning">No</span>' ?>
            Programado: <?= $card['programado'] ? '<span class="badge bg-success">Si</span>' : '<span class="badge bg-warning">No</span>' ?>
          </p>
        </div>

        <div class="col-3">
        <?php if ($tieneProfesional): ?>
          <div class="col-xs-4">
            <h4><?php
              if ($card['edad'] !== null) {
                  echo 'Edad: ' . (int) $card['edad'] . ' años - ';
              }
            ?>HC: <?= Html::encode((string) $card['nHistoriaClinica']) ?></h4>
          </div>
        <?php else: ?>
            <?= Html::a(
                'No se presentó',
                (string) $card['idTurnos'],
                ['class' => 'btn btn-light', 'id' => 'no_se_presento']
            ) ?>
        <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

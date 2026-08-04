<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Json;

/**
 * Filtro piso/sala del mapa (selects nativos; sin Kartik Select2/DepDrop).
 *
 * @var iterable $pisos_efector
 * @var string $urlReset
 * @var string|null $formAction
 */

$formAction = $formAction ?? ($urlReset ?? '');
$pisoSeleccionado = (string) (Yii::$app->request->post('piso') ?? '');
$salaSeleccionada = (string) (Yii::$app->request->post('sala') ?? '');

$pisoOptions = ['' => 'Todos los pisos'];
$salasByPiso = [];
foreach ($pisos_efector as $piso) {
    $idPiso = (string) $piso->id;
    $labelPiso = trim((string) ($piso->descripcion ?? ''));
    if ($labelPiso === '') {
        $labelPiso = 'Piso ' . (string) ($piso->nro_piso ?? $idPiso);
    }
    $pisoOptions[$idPiso] = $labelPiso;
    $salasByPiso[$idPiso] = [];
    foreach ($piso->infraestructuraSalas as $sala) {
        $labelSala = trim((string) ($sala->descripcion ?? ''));
        if ($labelSala === '') {
            $labelSala = 'Sala ' . (string) ($sala->nro_sala ?? $sala->id);
        }
        $salasByPiso[$idPiso][] = [
            'id' => (string) $sala->id,
            'label' => $labelSala,
        ];
    }
}

$form = ActiveForm::begin([
    'action' => $formAction !== '' ? $formAction : null,
    'method' => 'post',
    'options' => ['id' => 'form-internados', 'class' => 'mb-0'],
]);
?>
<div class="d-flex align-items-end flex-wrap gap-2">
    <div style="min-width: 12rem; flex: 1 1 12rem;">
        <label class="form-label small mb-1" for="piso">Piso</label>
        <?= Html::dropDownList('piso', $pisoSeleccionado, $pisoOptions, [
            'id' => 'piso',
            'class' => 'form-select form-select-sm',
        ]) ?>
    </div>
    <div style="min-width: 12rem; flex: 1 1 12rem;">
        <label class="form-label small mb-1" for="descripcion">Sala</label>
        <?= Html::dropDownList('sala', $salaSeleccionada, ['' => 'Todas las salas'], [
            'id' => 'descripcion',
            'class' => 'form-select form-select-sm',
            'disabled' => $pisoSeleccionado === '',
        ]) ?>
    </div>
</div>
<script type="application/json" id="internacion-salas-by-piso"><?= Json::htmlEncode($salasByPiso) ?></script>
<?php
$this->registerJs(<<<'JS'
(function () {
  var form = document.getElementById('form-internados');
  var pisoEl = document.getElementById('piso');
  var salaEl = document.getElementById('descripcion');
  var dataEl = document.getElementById('internacion-salas-by-piso');
  if (!form || !pisoEl || !salaEl || !dataEl) return;

  var salasByPiso = {};
  try {
    salasByPiso = JSON.parse(dataEl.textContent || '{}') || {};
  } catch (e) {
    salasByPiso = {};
  }

  function fillSalas(preserveValue) {
    var idPiso = String(pisoEl.value || '');
    var keep = preserveValue ? String(salaEl.value || '') : '';
    var rows = idPiso && salasByPiso[idPiso] ? salasByPiso[idPiso] : [];
    salaEl.innerHTML = '';
    var optAll = document.createElement('option');
    optAll.value = '';
    optAll.textContent = idPiso ? 'Todas las salas' : 'Elegí un piso';
    salaEl.appendChild(optAll);
    rows.forEach(function (row) {
      var opt = document.createElement('option');
      opt.value = String(row.id);
      opt.textContent = String(row.label || row.id);
      if (keep && keep === opt.value) opt.selected = true;
      salaEl.appendChild(opt);
    });
    salaEl.disabled = !idPiso;
  }

  function submitFilter() {
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  }

  fillSalas(true);

  pisoEl.addEventListener('change', function () {
    fillSalas(false);
    // Cambiar piso aplica filtro; sala queda en "todas".
    submitFilter();
  });
  salaEl.addEventListener('change', submitFilter);
})();
JS
);
ActiveForm::end();

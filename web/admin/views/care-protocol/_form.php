<?php

use common\models\Clinical\CareProtocol;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $model */
/** @var array<int|string, string> $provincias */
/** @var string $submitLabel */
/** @var bool $lockKey */

$lockKey = $lockKey ?? false;
$sex = is_array($model['sex'] ?? null) ? $model['sex'] : [];
?>

<?php $form = ActiveForm::begin(['options' => ['class' => 'care-protocol-form']]); ?>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="protocol_key">Clave (protocol_key)</label>
        <input type="text" class="form-control" id="protocol_key" name="protocol_key" maxlength="64" required
               <?= $lockKey ? 'readonly' : '' ?>
               value="<?= Html::encode((string) ($model['protocol_key'] ?? '')) ?>">
        <div class="form-text">Identificador estable, p. ej. <code>vacunas_pediatricas</code>.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="orden">Orden</label>
        <input type="number" class="form-control" id="orden" name="orden"
               value="<?= (int) ($model['orden'] ?? 100) ?>">
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="title">Título</label>
    <input type="text" class="form-control" id="title" name="title" maxlength="255" required
           value="<?= Html::encode((string) ($model['title'] ?? '')) ?>">
</div>

<div class="mb-3">
    <label class="form-label" for="hub_label">Etiqueta en hub (opcional)</label>
    <input type="text" class="form-control" id="hub_label" name="hub_label" maxlength="255"
           value="<?= Html::encode((string) ($model['hub_label'] ?? '')) ?>">
    <div class="form-text">Texto “Control recomendado…” que ve el paciente. Si vacío, usa el título.</div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label" for="scope_type">Alcance</label>
        <?= Html::dropDownList(
            'scope_type',
            $model['scope_type'] ?? CareProtocol::SCOPE_NATION,
            [
                CareProtocol::SCOPE_NATION => 'Nación',
                CareProtocol::SCOPE_PROVINCE => 'Provincia',
            ],
            ['class' => 'form-select', 'id' => 'scope_type']
        ) ?>
    </div>
    <div class="col-md-8 mb-3" id="wrap-provincia">
        <label class="form-label" for="id_provincia">Provincia</label>
        <?= Html::dropDownList(
            'id_provincia',
            $model['id_provincia'] ?? '',
            $provincias,
            ['class' => 'form-select', 'id' => 'id_provincia']
        ) ?>
        <div class="form-text">Obligatoria si el alcance es Provincia.</div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label" for="age_min">Edad mín.</label>
        <input type="number" class="form-control" id="age_min" name="age_min" min="0"
               value="<?= Html::encode($model['age_min'] !== null && $model['age_min'] !== '' ? (string) $model['age_min'] : '') ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label" for="age_max">Edad máx.</label>
        <input type="number" class="form-control" id="age_max" name="age_max" min="0"
               value="<?= Html::encode($model['age_max'] !== null && $model['age_max'] !== '' ? (string) $model['age_max'] : '') ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label d-block">Sexo</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="sex[]" id="sex_f" value="F"
                <?= in_array('F', $sex, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="sex_f">F</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="sex[]" id="sex_m" value="M"
                <?= in_array('M', $sex, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="sex_m">M</label>
        </div>
        <div class="form-text">Vacío = sin filtro por sexo.</div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="condition_match">Match de diagnóstico</label>
        <?= Html::dropDownList(
            'condition_match',
            $model['condition_match'] ?? CareProtocol::MATCH_NONE,
            [
                CareProtocol::MATCH_NONE => 'none — solo perfil (edad/sexo)',
                CareProtocol::MATCH_ACTIVE => 'active — condición activa',
                CareProtocol::MATCH_CHRONIC => 'chronic — activa y crónica',
                CareProtocol::MATCH_ACTIVE_OR_CHRONIC => 'active_or_chronic',
            ],
            ['class' => 'form-select', 'id' => 'condition_match']
        ) ?>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="condition_codes_text">Códigos CIE</label>
        <input type="text" class="form-control" id="condition_codes_text" name="condition_codes_text"
               value="<?= Html::encode((string) ($model['condition_codes_text'] ?? '')) ?>"
               placeholder="I10, I11, E11">
        <div class="form-text">Separados por coma. Vacío si es solo preventivo por perfil.</div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="actions_json">Acciones (JSON)</label>
    <textarea class="form-control font-monospace" id="actions_json" name="actions_json" rows="12" required><?= Html::encode((string) ($model['actions_json'] ?? '')) ?></textarea>
    <div class="form-text">
        Lista de <code>{code, label, description, outcome, draft}</code>.
        Outcomes habituales: <code>modalidad</code>, <code>captura_mensaje</code>.
    </div>
</div>

<div class="form-check mb-3">
    <input type="hidden" name="enabled" value="0">
    <input class="form-check-input" type="checkbox" name="enabled" id="enabled" value="1"
        <?= !empty($model['enabled']) ? 'checked' : '' ?>>
    <label class="form-check-label" for="enabled">Habilitado</label>
</div>

<div class="d-flex gap-2">
    <?= Html::submitButton($submitLabel, ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>

<?php
$this->registerJs(<<<'JS'
(function () {
  var scope = document.getElementById('scope_type');
  var wrap = document.getElementById('wrap-provincia');
  if (!scope || !wrap) return;
  function sync() {
    wrap.style.display = scope.value === 'PROVINCE' ? '' : 'none';
  }
  scope.addEventListener('change', sync);
  sync();
})();
JS
);
?>

<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $model */
/** @var array<int|string, string> $servicios */
/** @var list<string> $placeholders */
/** @var bool $showActivo */
/** @var string $submitLabel */

$showActivo = $showActivo ?? false;
?>

<?php $form = ActiveForm::begin(['options' => ['class' => 'internacion-epicrisis-plantilla-form']]); ?>

<div class="mb-3">
    <label class="form-label" for="plantilla-nombre">Nombre</label>
    <input type="text" class="form-control" id="plantilla-nombre" name="nombre" maxlength="120" required
           value="<?= Html::encode((string) ($model['nombre'] ?? '')) ?>">
</div>

<div class="mb-3">
    <label class="form-label" for="plantilla-servicio">Servicio (opcional)</label>
    <?= Html::dropDownList(
        'id_servicio',
        $model['id_servicio'] ?? '',
        $servicios,
        ['class' => 'form-select', 'id' => 'plantilla-servicio']
    ) ?>
    <div class="form-text">Si no elige servicio, la plantilla aplica a todo el efector.</div>
</div>

<div class="mb-3">
    <label class="form-label" for="plantilla-orden">Orden</label>
    <input type="number" class="form-control" id="plantilla-orden" name="orden" min="0"
           value="<?= (int) ($model['orden'] ?? 0) ?>">
</div>

<div class="mb-3">
    <label class="form-label" for="plantilla-cuerpo">Cuerpo</label>
    <textarea class="form-control font-monospace" id="plantilla-cuerpo" name="cuerpo" rows="14" required><?= Html::encode((string) ($model['cuerpo'] ?? '')) ?></textarea>
    <div class="form-text">
        Placeholders disponibles:
        <?php foreach ($placeholders as $ph): ?>
            <code><?= Html::encode($ph) ?></code>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($showActivo): ?>
<div class="form-check mb-3">
    <input type="hidden" name="activo" value="0">
    <input class="form-check-input" type="checkbox" name="activo" id="plantilla-activo" value="1"
        <?= !empty($model['activo']) ? 'checked' : '' ?>>
    <label class="form-check-label" for="plantilla-activo">Activa</label>
</div>
<?php endif; ?>

<div class="d-flex gap-2">
    <?= Html::submitButton($submitLabel, ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>

<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\DataAccess\DataAccessRoleGrant;
use common\components\Core\DataAccess\ScopeCheckerRegistry;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessRoleGrant */

$entityGroups = (new \common\components\Core\DataAccess\AttributeGroupCatalog())->listEntityGroupOptions();
$roles = DataAccessRoleGrant::roleNameOptions();
$operations = DataAccessRoleGrant::operationOptions();
$selected = array_flip($model->operationsSelected);

$form = ActiveForm::begin();
?>

<div class="data-access-grant-form">

    <?= $form->field($model, 'role_name')->dropDownList($roles, ['prompt' => 'Seleccione rol']) ?>

    <?= $form->field($model, 'entity_group_key')->dropDownList($entityGroups, ['prompt' => 'Seleccione grupo']) ?>

    <div class="form-group field-dataaccessrolegrant-operationsselected">
        <label class="control-label"><?= Html::encode($model->getAttributeLabel('operationsSelected')) ?></label>
        <div class="form-control" style="height: auto;">
            <?php foreach ($operations as $op => $label): ?>
                <?php $checked = isset($selected[$op]) ? ' checked' : ''; ?>
                <div class="form-check">
                    <label class="form-check-label">
                        <input type="checkbox" class="form-check-input" name="DataAccessRoleGrant[operationsSelected][]" value="<?= Html::encode($op) ?>"<?= $checked ?>>
                        <?= Html::encode($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?= Html::error($model, 'operationsSelected', ['class' => 'help-block text-danger']) ?>
        <p class="help-block text-muted small">
            <strong>filter</strong>: usar en filtros de métricas.
            <strong>read</strong>: columnas en listados.
            <strong>aggregate</strong>: conteos y agrupaciones.
        </p>
    </div>

    <?= $form->field($model, 'scope_checker')->dropDownList(ScopeCheckerRegistry::optionsForForm()) ?>

    <?= Html::activeHiddenInput($model, 'active', ['value' => 0, 'id' => 'dataaccessrolegrant-active-hidden']) ?>
    <?= $form->field($model, 'active')->checkbox(['value' => 1, 'uncheck' => null]) ?>

    <?= $form->field($model, 'notas')->textarea(['rows' => 3]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Crear' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', $model->isNewRecord ? ['index'] : ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

</div>

<?php ActiveForm::end(); ?>

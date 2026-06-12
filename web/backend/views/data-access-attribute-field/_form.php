<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\DataAccess\DataAccessAttributeField;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessAttributeField */

$entityGroups = (new \common\components\Core\DataAccess\AttributeGroupCatalog())->listEntityGroupOptions();
if ($model->config_json === null || trim((string) $model->config_json) === '') {
    $model->config_json = $model->configJsonForForm();
}

$form = ActiveForm::begin();
?>

<div class="data-access-attribute-field-form">

    <?= $form->field($model, 'entity_group_key')->dropDownList($entityGroups, ['prompt' => 'Seleccione grupo']) ?>

    <?= $form->field($model, 'field_name')->textInput(['maxlength' => true])
        ->hint('Nombre del input en el formulario (p. ej. vigente_desde, lunes_2).') ?>

    <?= $form->field($model, 'field_type')->dropDownList(DataAccessAttributeField::fieldTypeOptions()) ?>

    <?= $form->field($model, 'label')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'config_json')->textarea(['rows' => 14])
        ->hint('JSON: options, layout, widget_id, value_fields, assets, required, form, source, context_key, include_in_submit, …') ?>

    <?= $form->field($model, 'sort_order')->input('number') ?>

    <?= Html::activeHiddenInput($model, 'active', ['value' => 0, 'id' => 'dataaccessattributefield-active-hidden']) ?>
    <?= $form->field($model, 'active')->checkbox(['value' => 1, 'uncheck' => null]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Crear' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', $model->isNewRecord ? ['index'] : ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

</div>

<?php ActiveForm::end(); ?>

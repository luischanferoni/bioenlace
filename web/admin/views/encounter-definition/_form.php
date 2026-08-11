<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\ActiveForm;
use kartik\select2\Select2;
use common\models\Clinical\EncounterDefinition;

/* @var $this yii\web\View */
/* @var $model common\models\Clinical\EncounterDefinition */
/* @var $form yii\widgets\ActiveForm */
?>

<?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'service_id')->widget(Select2::class, [
        'size' => Select2::LARGE,
        'options' => ['placeholder' => '- Servicio -'],
        'theme' => 'default',
        'pluginOptions' => [
            'minimumInputLength' => 3,
            'ajax' => [
                'url' => Url::to(['servicios/search']),
                'dataType' => 'json',
                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                'cache' => true,
            ],
        ],
    ])->label('Servicio'); ?>

    <div class="col-md-2 col-sm-12 col-xs-12">
        <?= $form->field($model, 'encounter_class')->dropDownList(
            EncounterDefinition::ENCOUNTER_CLASS,
            ['prompt' => '']
        ); ?>
    </div>

    <?= $form->field($model, 'workflow_json')->textarea(['rows' => 10])->hint(
        'JSON con clave conf[]. Campos titulo, relacion, requerido (y opcional sugerido) definen categorías para IA/API.'
    ) ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success float-end']) ?>
    </div>
<?php ActiveForm::end(); ?>

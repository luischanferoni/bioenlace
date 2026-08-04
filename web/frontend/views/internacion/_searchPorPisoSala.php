<?php 
use common\components\Domain\Organization\Service\InfraestructuraDepdropService;
use kartik\depdrop\DepDrop;
use yii\bootstrap5\ActiveForm;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

$formAction = $formAction ?? ($urlReset ?? '');
$form = ActiveForm::begin([
            'action' => $formAction !== '' ? $formAction : null,
            'method' => 'post',
            'options' => ['id' => 'form-internados', 'class' => 'form-horizontal'], 'layout' => 'horizontal',
            'fieldConfig' => [
                'template' => "{label}\n{beginWrapper}\n{input}\n{hint}\n{error}\n{endWrapper}",
                'horizontalCssClasses' => [
                    'label' => 'col-sm-4',
                    'offset' => 'col-sm-offset-4',
                    'wrapper' => 'col-sm-8',
                ],
            ],
        ]);
    ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center flex-wrap">
        <?php
            $piso = ArrayHelper::map($pisos_efector, 'id', 'descripcion');
            echo Select2::widget([
                'name'=> 'piso',
                'id'=> 'piso',
                'data' => $piso,                        
                'theme'=>'default',
                'options' => ['placeholder' => 'Seleccione Piso...'],
                'pluginOptions' => [
                    'allowClear' => true,
                    'width' => '100%',
                    
                ],
            ]);
        ?>
</div>
<div class="d-flex align-items-center flex-wrap">
            <?php
            echo DepDrop::widget([
                'name'=> 'sala',
                'id'=> 'sala',
                'data' => [],                        
                'options' => ['id' => 'descripcion', 'placeholder' => 'Seleccione Sala'],
                'type' => DepDrop::TYPE_SELECT2,
                'select2Options'=>['theme'=>'default','pluginOptions'=>['class'=>'col-5','width' => '100%']],
                'pluginOptions' => [
                    'depends' => ['piso'],
                    'placeholder' => 'Seleccione sala...',
                    'url' => InfraestructuraDepdropService::URL_SALAS_POR_PISO,

                ]
            ]);
            ?>
        </div>
    </div>
    <?php
    $this->registerJs(<<<'JS'
(function () {
  var form = document.getElementById('form-internados');
  if (!form) return;
  function submitFilter() {
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  }
  // Al elegir/limpiar sala (o piso sin sala) se aplica el filtro sin botones Filtrar/Reset.
  $(document).on('change', '#descripcion', submitFilter);
  $(document).on('select2:clear', '#piso', submitFilter);
})();
JS
);
    ?>
    
<?php ActiveForm::end(); ?>  

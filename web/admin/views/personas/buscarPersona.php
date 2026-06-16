<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Búsqueda de Persona';
$this->params['breadcrumbs'][] = $this->title;
//print_r($dataProvider);
?>
<div class="buscar-persona">   
    <?php echo $this->render('_buscar',['model' => $model]); ?>    
</div>
<?php
$script = <<<JS
//$( document ).ready(function() {
  // $('input[type=text]').attr('readonly', true);
  // $('input[type=date]').attr('readonly', true);
  // $('#persona-id_tipodoc').prop('disabled', true);
  // $('#persona-id_tipodoc').val(1);
  // $('#hidden_id_tipodoc').val(1);
  // $('input[type=radio]').prop('disabled', true);
  // $('#persona-motivo_acredita').prop('disabled', true);    
  //   habilitarQrScan();
 // });
JS;
$this->registerJs($script);
?>
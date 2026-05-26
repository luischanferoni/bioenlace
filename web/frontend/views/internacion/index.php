<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use kartik\depdrop\DepDrop;
use yii\bootstrap5\ActiveForm;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

use common\models\Persona;
use frontend\assets\InternacionMapaAsset;
use webvimark\modules\UserManagement\models\User;

InternacionMapaAsset::register($this);


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\SegNivelInternacionBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Internaciones';
$this->params['breadcrumbs'][] = $this->title;
$hola = 1;

?>
<div>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <p class="mb-0">Disponibilidad de Camas</p>
            <?= Html::a(
                'Plantillas de epicrisis',
                ['/internacion-epicrisis-plantilla/index'],
                ['class' => 'btn btn-sm btn-outline-secondary rounded-pill']
            ) ?>
        </div>
    <div class="row">
        <?php $urlReset = "index";
              echo $this->render('_searchPorPisoSala', ['pisos_efector'=>$pisos_efector, 'urlReset'=> $urlReset]) ?>   
    </div> 
    <div class="mx-auto" style="height: 20px;"> 
    </div>
    <div class="row mb-3">
        <?= $this->render('_mapa_camas', ['mapa' => $mapa ?? null, 'pacienteInternado' => $pacienteInternado]) ?>
    </div>
        </div>
</div>
</div>          

<?php

$this->registerJs("
    $(document).ready(function() {        

        var pacienteInternado= ".($pacienteInternado ? 'true' : 'false').";
        console.log(pacienteInternado);
        if (pacienteInternado){
            Swal.fire({
                title: 'La persona seleccionada  ya se encuentra en internación.',
                backdrop: `rgba(60,60,60,0.8)`,
            });
        }        
    });
");
?>
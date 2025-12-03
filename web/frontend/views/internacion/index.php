<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use kartik\depdrop\DepDrop;
use yii\bootstrap5\ActiveForm;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

use common\models\Persona;
use webvimark\modules\UserManagement\models\User;


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
        <p>Disponibilidad de Camas</p>
    <div class="row">
        <?php $urlReset = "index";
              echo $this->render('_searchPorPisoSala', ['pisos_efector'=>$pisos_efector, 'urlReset'=> $urlReset]) ?>   
    </div> 
    <div class="mx-auto" style="height: 20px;"> 
    </div>
    <div class="row mb-3">
        <?php foreach ($pisos_efector as $key => $piso) { 
                if (Yii::$app->request->post()) {
                    $pisoSeleccionado = Yii::$app->request->post('piso');                       
                    if($piso->id != $pisoSeleccionado )   continue;                     
                }
            ?>
                        <div class="card">
                            <div class="card-header bg-soft-info">
                                <h3> Piso: <?= $piso->descripcion; ?></h3>
                            </div>
                            <div class="card-body">
                                <?php $salas = $piso->infraestructuraSalas;

                                foreach ($salas as $key => $sala) { 
                                    if (Yii::$app->request->post()) {
                                        $salaSeleccionada = Yii::$app->request->post('sala');
                                        if($sala->id != $salaSeleccionada )   continue;                     
                                    }
                                    ?>

                                    <h4 class="mb-3">Sala: <?= $sala->descripcion; ?></h4>

                                    <?php $camas = $sala->infraestructuraCamas;
                                    foreach ($camas as $key => $cama) {

                                        if ($cama->estado != 'ocupada') {
                                            $class = 'btn btn-success rounded-pill ' . ($pacienteInternado? 'disabled':'');
                                            $title = "Cama " . $cama->nro_cama . " - Disponible";
                                            $url = "internacion/create";                                        

                                        echo Html::a(
                                            '<svg width="30px" height="30px" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                                <g id="🔍-Product-Icons" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <g id="ic_fluent_bed_24_regular" fill="#ffffff" fill-rule="nonzero">
                                                    <path d="M6.75,4 L17.25,4 C18.7125318,4 19.9084043,5.1417046 19.9949812,6.58247767 L20,6.75 L20.0005538,10.103673 C21.0968245,10.4139878 21.9146811,11.38715 21.993726,12.5627618 L22,12.75 L22,20.25 C22,20.6642136 21.6642136,21 21.25,21 C20.8703042,21 20.556509,20.7178461 20.5068466,20.3517706 L20.5,20.25 L20.5,18 L3.5,18 L3.5,20.25 C3.5,20.6296958 3.21784612,20.943491 2.85177056,20.9931534 L2.75,21 C2.37030423,21 2.05650904,20.7178461 2.00684662,20.3517706 L2,20.25 L2,12.75 C2,11.4910613 2.84596473,10.4297083 4.00044448,10.1033906 L4,6.75 C4,5.28746816 5.1417046,4.09159572 6.58247767,4.00501879 L6.75,4 Z M19.25,11.5 L4.75,11.5 C4.10279131,11.5 3.5704661,11.9918747 3.50645361,12.6221948 L3.5,12.75 L3.5,16.5 L20.5,16.5 L20.5,12.75 C20.5,12.1027913 20.0081253,11.5704661 19.3778052,11.5064536 L19.25,11.5 Z M17.25,5.5 L6.75,5.5 C6.10279131,5.5 5.5704661,5.99187466 5.50645361,6.62219476 L5.5,6.75 L5.5,10 L7,10 C7,9.44771525 7.44771525,9 8,9 L10,9 C10.5128358,9 10.9355072,9.38604019 10.9932723,9.88337887 L11,10 L13,10 C13,9.44771525 13.4477153,9 14,9 L16,9 C16.5128358,9 16.9355072,9.38604019 16.9932723,9.88337887 L17,10 L18.5,10 L18.5,6.75 C18.5,6.10279131 18.0081253,5.5704661 17.3778052,5.50645361 L17.25,5.5 Z" id="🎨-Color">                                        
                                                    </path>
                                                </g>
                                                </g>
                                            </svg> ' . $cama->nro_cama,
                                            [$url, 'id' => $cama->id],
                                            $options = [
                                                'class' => $class,
                                                'title' => $title
                                            ]
                                        );
                                        } 
                                    ?>
                                    <?php } ?>
                                <?php } ?>                        
                            </div>
                        </div>
                    <?php } ?>

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
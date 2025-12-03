<?php

use yii\helpers\Html;
use yii\helpers\Url;

use common\models\Consulta;
use common\models\ServiciosEfector;
?>

<?php 

$consultaInternacion = false;
$consultaGuardia = false;

switch($historia['parent_class']){
    case Consulta::PARENT_CLASSES[Consulta::PARENT_INTERNACION]: 
        $consultaInternacion = true;
        break;

    case Consulta::PARENT_CLASSES[Consulta::PARENT_GUARDIA]:
        $consultaGuardia = true;
        break;

    default:
        break;
}

?>

<?php 

    if ($historia['parent_class'] == Consulta::PARENT_CLASSES[Consulta::PARENT_PASE_PREVIO]) {

        $servPasePrevio = ServiciosEfector::find()->where(['id_servicio'=> $historia['id_servicio']])->one();

        $servicio = isset($servPasePrevio->pasePrevio->nombre) ? $servPasePrevio->pasePrevio->nombre : $historia['servicio'];

        $titulo = 'Consulta de ' .$servicio . ' (Pase Previo)';

    } else {

        $titulo = $consultaInternacion ? 'Atención' : 'Consulta de ' .$historia['servicio'];
    }
 
 ?>

<h5 class="mb-1"><?=$titulo?></h5>

<div class="d-inline-block">                                   
    <div class="d-flex align-items-center">
        <p class="pe-3 border-end">Profesional: <?=$historia['rr_hh']?></p>
        <p class="ps-3 pe-3 border-end">Servicio: <?=$historia['servicio']?></p>
        <p class="ps-3 pe-3"><?= !$consultaInternacion ? "Efector: ".$historia['efector'] : "" ?></p>
    </div>
</div>

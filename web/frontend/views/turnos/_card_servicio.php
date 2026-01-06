<?php
use yii\helpers\Html;
use yii\helpers\Url;

use common\models\ServiciosEfector;
?>

<div class="col-lg-4 livesearchparent">
    <div class="card">
        <div class="card-header <?=isset($con_derivacion) ? 'bg-soft-secondary' : ''?>">
            <div class="d-flex">
                <h4><span style="color: <?php echo $color; ?> !important"><?php echo $servicioEfector->servicio->nombre; ?></span></h4><h8><span style="color: <?php echo $color; ?> !important"><h8>
            </div>
            <?php if($referencia != null): ?>
            <div class="d-inline-flex">
                <h7><span><?php echo common\models\Consulta::getEfectorByIdConsulta($referencia['id_consulta_solicitante'])?></span></h7>
            </div>
            <div class="d-inline-flex">
                <h7><span><?php echo 'Indicaciones: '.$referencia['indicaciones'] ?></span></h7>
            </div>
            <?php endif ?>
        </div>
        
        <div class="card-body <?=isset($con_derivacion) ? 'bg-soft-secondary' : ''?>">
        
            <?php 
            if (isset($con_derivacion)) {
                echo "<div class='text-center'>SOLO CON DERIVACIÓN</div>";
            }
            else if (
                $servicioEfector->formas_atencion == ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS || $servicioEfector->formas_atencion == ServiciosEfector::DERIVACION_ORDEN_LLEGADA_PARA_TODOS) {
                echo "<div class='text-center'>".Html::button(
                    "<b>VER TURNOS</b>",
                    [
                    "class" => "btn btn-sm btn-soft-success rounded-pill ms-3 mb-1 text-dark",
                    "data-bs-target" => "#modal-general",
                    "data-bs-toggle" => "modal",
                    "data-title" => sprintf("Turno para %s, %s", $persona->apellido, $persona->nombre),
                    "data-bioenlace-id_servicio" => $servicioEfector->id_servicio
                    ]
                )."</div>";
            } else {

                foreach ($servicioEfector->rrhhs as $rrhhEfectorServicio) {
                
                    // Hay recursos humanos con id_persona = 0
                    if (!isset($rrhhEfectorServicio->rrhhEfector->persona)) { continue; }
                    
                    $datosParaLiveSearch .= $rrhhEfectorServicio->rrhhEfector->persona->apellido . ' ' . $rrhhEfectorServicio->rrhhEfector->persona->nombre . ' ';
                
                    echo '<div class="border-bottom mb-3">'; ?>
                    
                    <a class="btn btn-soft btn-sm rounded-pill me-3" 
                        style="background-color: <?php echo $color; ?>" 
                        href="<?= Url::to(['turnos/espera', 'rrhh' => $rrhhEfectorServicio->id]) ?>" 
                        target="_blank"
                        data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Imprimir turnos"
                        >
                            <i class="bi bi-printer text-white"></i>
                    </a>
                    <?php echo $rrhhEfectorServicio->rrhhEfector->persona->apellido . ', ' . $rrhhEfectorServicio->rrhhEfector->persona->nombre;


                    
                    if (isset($rrhhEfectorServicio->agenda) && $servicioEfector->formas_atencion !== ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS): ?>

                        
                        
                        <?= Html::button(
                            "<b>VER TURNOS</b>",
                            [
                            "class" => "btn btn-sm btn-soft-success rounded-pill ms-3 mb-1 text-dark",
                            "data-bs-target" => "#modal-general",
                            "data-bs-toggle" => "modal",
                            "data-title" => sprintf("Turno para %s, %s", $persona->apellido, $persona->nombre),
                            "data-bioenlace-id_servicio" => $servicioEfector->id_servicio,
                            "data-bioenlace-id_rrhh_sa" => $rrhhEfectorServicio->id,
                            ]
                        );?>
                    <?php endif ?>

                    <?php if (!isset($rrhhEfectorServicio->agenda) && $servicioEfector->formas_atencion !== ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS) { ?>
                        <span class="ms-3 mb-2 badge bg-soft-danger">SIN AGENDA</span>
                    <?php } ?>           

                    
                    </div>
            <?php 
                } 
            }
            ?>
            <input class="livesearch" type="hidden" value="<?= $datosParaLiveSearch . ' ' . $servicioEfector->servicio->nombre ?>">
        
        </div>
    </div>
</div>
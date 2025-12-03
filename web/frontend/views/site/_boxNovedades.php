<?php 
use yii\helpers\Url;
use yii\helpers\Html;

use common\models\Persona;
use common\models\Consulta;

$cantidadNovedades = count($novedades);

?>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-light">
                <div class="header-title">
                    <div class="row">
                        <div class="col-md-12">
                            <h4>Novedades!</h4>
                        </div>                        
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php 
                $max = 3; $i = 0;
                if($cantidadNovedades > 0){
                    foreach ($novedades as $key => $novedad) {                        
                        if ($i < $max) {                            
                    ?>
                    <div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">                        
                        <div class="ms-3" style="width: 100%;">                                
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="mb-0 d-flex align-items-center">
                                    <?= $novedad->titulo; ?>
                                </h5>                                    
                            </div>
                            <p class="mb-1 text-wrap"><?= $novedad->texto; ?></p>
                        </div>                     
                    </div>   

                <?php 
                    $i++;
                    }
                    else  {  break;   }
                    }
                } else { ?>                            
                    <div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">                    
                        <div class="ms-3" style="width: 100%;">                                
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="mb-0 d-flex align-items-center">
                                    No hay novedades para mostrar.
                                </h5>                                    
                            </div>                        
                        </div>                    
                    </div>
            <?php } ?>    
            </div>
            <div class="card-footer">
                <?= Html::a('Ver +Novedades', ['novedad/index'], ['class' => 'btn btn-success float-end']) ?>
            </div>
        </div>
    </div>
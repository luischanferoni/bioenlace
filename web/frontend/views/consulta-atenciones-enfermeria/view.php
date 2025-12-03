<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Barrios */

$this->title = "Listado de Atenciones hasta 27/03/2024";
$this->params['breadcrumbs'][] = ['label' => 'Enfermería', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="atenciones_enfermeria-view">
    <div class="card">
        <div class="card-header bg-soft-info">
            <h2>Atenciones de Enfermer&iacute;a</h2>
        </div>
        <div class="card-body">

                    <?php

                    if (is_array($model->atencionesEnfermeriaV1) && count($model->atencionesEnfermeriaV1)>0) {
                        echo '<div class="table-responsive mt-2">';
                        echo '<table class="table table-striped table-bordered mb-0 ">';
                        echo '<tr><th>Fecha control/atención</th>'
                            . '<th>Control/Atención</th>';
                        echo '<th>Efector</th>';
                        echo '<th>Observaciones</th>';
                        echo '<th>Profesional</th>';
                        
                        echo '</tr>';
                  

                    foreach ($model->atencionesEnfermeriaV1 as $key => $value) {
                        $datos = json_decode($value->datos, TRUE);
                        
                        $indice = 1;

                        echo '<tr><td>' . Yii::$app->formatter->asDate($value->fecha_creacion, 'dd/MM/yyyy') . '</td>';

                        echo '<td>';
                        echo $value->formatearDatos();
                        echo '</td>';
                        echo '<td class="text-wrap">' . $value->efector->nombre . '</td>';
                        echo '<td class="text-wrap">' . $value->observaciones . '</td>';

                        echo '<td>';
                        if (is_object($value->user)) {
                            echo $value->user->nombre . ' ' . $value->user->apellido;
                        }
                        echo '</td>';

                        echo '</tr>';
                    }
                    echo ' </table>';
                    echo '</div>';

                }
                else
                {
                    echo '<h4 class="text-center"> Este paciente no tiene atenciones de enfermería.</h4>';

                }
                    ?>
               
        </div>
    </div>
</div>
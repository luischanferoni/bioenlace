<?php
use yii\helpers\Html;
use common\models\ConsultaAtencionesEnfermeria;
?>


<div class="card w-100">

    <div class="card-header">
                <h5>Atenciones de Enfermeria</h5>
    </div>

    <div class="card-body table-responsive">

        <table class="table table-bordered">
            <?php
            if (is_array($atencionEnfermeria)&&(count($atencionEnfermeria)>0)) {
                echo '<tr><th>Fecha control/atención</th>'
                    . '<th>Control/Atención</th>';
                echo '<th class="text-wrap">Observaciones</th>';
                echo '<th>Profesional</th>';
                echo '</tr>';
            }

            foreach ($atencionEnfermeria as $key => $value) {
                $datos = json_decode($value->datos, TRUE);
                $valores = $value->formatearDatos();
                $indice = 1;          

                echo '<tr>';
                echo '<td>';
                echo Yii::$app->formatter->asDate($value->fecha_creacion, 'dd/MM/yyyy');
                echo " " . $value->hora_creacion . " hs";
                //echo Yii::$app->formatter->asDate($value->created_at, 'dd/MM/yyyy');
                echo '</td>';
                echo '<td>';
                
                    echo $valores;
                    echo '<br/>';
                
                echo '</td>';
                echo '<td class="text-wrap">';
                echo $value->observaciones;
                echo '</td>';
                echo '<td>';
                if (is_object($value->user)) {
                    echo $value->user->nombre . ' ' . $value->user->apellido;
                }
                echo '</td>';
             
                echo '</tr>';
            }
            ?>
        </table>
    </div>
</div>
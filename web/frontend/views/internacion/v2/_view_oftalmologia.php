<?php

use common\models\ConsultaPracticasOftalmologia;
use yii\helpers\Html;

use common\models\Persona;

?>

<?php
if (is_array($oftalmologias)) { ?>

    <div class="card w-100">
        <div class="card-header">
            <div class="d-flex bd-highlight align-items-center">
                <div class="bd-highlight">
                    <h5>Oftalmologia</h5>
                </div>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table id="oftalmologias" class="table table-striped table-bordered detail-view">
                <tbody>
                    <?php foreach ($oftalmologias as $key => $oftalmologia) {
                        if (is_array($oftalmologia)) {
                            foreach ($oftalmologia as $key => $valueOftalmologia) {
                    ?>
                                <tr>

                                    <td>
                                        <?= ConsultaPracticasOftalmologia::PRACTICAS_EVALUACION[$valueOftalmologia->codigo] ?>
                                    </td>

                                    <td>
                                        <?= $valueOftalmologia->ojo ?>

                                    </td>

                                    <td>
                                        <?= $valueOftalmologia->resultado ?>
                                    </td>

                                    <td class="text-wrap">
                                        <?= $valueOftalmologia->informe ?>
                                    </td>

                                    <td>
                                        <?= $valueOftalmologia->consulta->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON)  ?>
                                    </td>

                                </tr>
                        <?php   }
                        }
                        ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>
<?php }
?>
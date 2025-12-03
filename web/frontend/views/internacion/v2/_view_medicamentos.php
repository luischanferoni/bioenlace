<?php

use yii\helpers\Html;
use common\models\ConsultaMedicamentos;
use common\models\Persona;

?>


<div class="card w-100">
    <div class="card-header">
        <div class="d-flex bd-highlight align-items-center">
            <div class="bd-highlight">
                <h5>Medicamentos</h5>
            </div>
            <?php /* <div class="ms-auto bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                         <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>',
                                    ['/internacion-medicamento/create', 'id' => $model->id],
                                    ['class' => 'btn btn-soft-warning btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Agregar Medicamento']
                                ) ?>

                            </div> */


            ?>
        </div>
    </div>
    <div class="card-body table-responsive">
        <?php
        if (is_array($medicamentos)) { ?>
            <table id="diagnosticos" class="table table-striped table-bordered detail-view">
                <tbody>
                    <?php foreach ($medicamentos as $key => $medicamento) {

                        if (is_array($medicamento)) {
                            foreach ($medicamento as $key => $valueMedicamento) {?>
                                <tr>
                                    <td>
                                        <?= $valueMedicamento->snomedMedicamento->term ?>

                                    </td>

                                    <td>
                                        <?= empty($valueMedicamento->estado) ? " " : ConsultaMedicamentos::ESTADOS[$valueMedicamento->estado] ?>
                                    </td>

                                    <td>
                                        <?= $valueMedicamento->indicaciones ?>
                                    </td>

                                    <td>
                                        <?= $valueMedicamento->consulta->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) ?>
                                    </td>
            
                                <?php 
                                //TODO: realizar la suspension de los medicamentos para internacion
                                /*Html::a('<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                                            </svg>', ['internacion-medicamento/view', 'id' => $valueMedicamento->id], ['class' => 'btn btn-primary btn-sm rounded-pill']);*/
                                ?>
                                <?php /*if ($valueMedicamento->user_suspencion == 0) {
                                    echo Html::a('<i class="bi bi-x-octagon"></i>', ['internacion-medicamento/suspender', 'id' => $valueMedicamento->id, 'id_internacion' => $valueMedicamento->id_internacion], [
                                        'class' => 'btn btn-danger btn-sm',
                                        'title' => 'Suspender',
                                        'data' => [
                                            'confirm' => '¿Está seguro de suspender este medicamento?',
                                            'method' => 'post',
                                        ],
                                    ]);
                                }*/
                                ?>

                            </td>
                        <?php   }
                        }
                        ?>

                            

                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php }
        ?>
    </div>
</div>
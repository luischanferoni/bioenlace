<?php

use yii\helpers\Html;

use common\models\Persona;

?>


                <?php #BEGIN card Evolucion
                ?>
                <div class="card w-100">
                    <div class="card-header">
                        <div class="d-flex bd-highlight align-items-center">
                            <div class="bd-highlight">
                                <h5>Evoluciones</h5>
                            </div>
                            <?php /* <div class="ms-auto bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                            <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>',
                                    ['/internacion-diagnostico/create', 'id' => $model->id],
                                    ['class' => 'btn btn-soft-warning btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Agregar Sintomatología']
                                ) ?>

                            </div> */ ?>
                        </div>
                    </div>
                    <div class="card-body table-responsive">
                        <?php
                        if (is_array($evoluciones)) { ?>
                            <table id="evoluciones" class="table table-striped table-bordered detail-view">
                                <tbody>
                                    <?php foreach ($evoluciones as $key => $evolucion) { ?>
                                        <tr>
                                            <td class="text-wrap">
                                                <?= $evolucion->evolucion ?>
                                            </td>
                                            
                                            <td>
                                                <?= $evolucion->consulta->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) ?>
                                            </td>  

                                            <td>
                                                <?= Yii::$app->formatter->asDateTime($evolucion->consulta->created_at, 'php:d-m-Y H:i')?>
                                            </td>  
                                            
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php }
                        ?>
                    </div>
                </div>
                <?php #END card evolucion
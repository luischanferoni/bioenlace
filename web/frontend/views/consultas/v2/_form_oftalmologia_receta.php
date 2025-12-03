<?php

use yii\helpers\Url;
use yii\web\JsExpression;

use kartik\select2\Select2;
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

?>

<?php $form = ActiveForm::begin(); ?>


<div class="card">
    <div class="card-header">
        <h2>Receta/Lentes</h2>
    </div>

    <div class="card-body">

        <div style="margin-right: 100px;">

            <div class="d-flex justify-content-center">

                <div class="d-flex flex-row aling-items-center ps-5 pe-5 w-40">

                    <div class="d-flex flex-column me-5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="#000000" class="bi bi-eye mx-auto" viewBox="0 0 16 16">
                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z" />
                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z" />
                        </svg>

                        <h3>OJO IZQUIERDO</h3>
                    </div>

                    <div class="d-flex flex-column ms-5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="#000000" class="bi bi-eye mx-auto" viewBox="0 0 16 16">
                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z" />
                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z" />
                        </svg>

                        <h3>OJO DERECHO</h3>
                    </div>

                </div>

            </div>


            <div class="d-flex justify-content-start mt-5">
                <h5 style="margin-left: 20% !important;">Lentes</h5>
            </div>

            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5" style=" margin-left:180px !important;">

                    <div class="d-flex">
                        <h6>Aéreos</h6>
                    </div>
                    <div class="d-flex me-5 justify-content-between align-items-center">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">
                            <select name="" id="">
                                <option value="" selected disabled>Tipo.</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5" style=" margin-left:150px !important;">

                    <div class="d-flex">
                        <h6>de Contacto</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between align-items-center">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">
                            <select name="" id="">
                                <option value="" selected disabled>Tipo.</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>


            <div class="d-flex justify-content-start mt-5">
                <h5 style="margin-left: 20% !important;" >Medicamentos</h5>
            </div>

            <div class="d-flex justify-content-center p-5">

                <?=
                Select2::widget([
                    'name' => 'medicamentos',
                    'data' => [],
                    'size' => Select2::LARGE,
                    'theme' => 'default',
                    'options' => ['placeholder' => '- Seleccione el Medicamento -', 'class' => 'snomed_simple_select2'],
                    'pluginOptions' => [
                        'minimumInputLength' => 4,
                        'width' => '50%',
                        'ajax' => [
                            'url' => Url::to(['snowstorm/medicamentos']),
                            'dataType' => 'json',
                            'delay'=> 500,
                            'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                            'cache' => true
                        ]
                    ],
                ]);
                ?>

            </div>

        </div>


    </div>
</div>



<?= Html::submitButton('Siguiente', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

<?php ActiveForm::end(); ?>
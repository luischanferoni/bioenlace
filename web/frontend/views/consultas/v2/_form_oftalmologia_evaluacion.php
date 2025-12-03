<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;


?>

<?php $form = ActiveForm::begin(); ?>


<div class="card">
    <div class="card-header">
        <h2>Oftalmologia</h2>
    </div>

    <div class="card-body">

        <!-- <div style="border: 1px solid red"> -->
            <div class="d-flex justify-content-center">

                <div class="d-flex flex-row align-items-center ps-5 pe-5 w-40">

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

            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5 w-40" style=" margin-right: 17% !important;">

                    <div class="d-flex">
                        <h6>PIO Presión Intraocular</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                    </div>
                </div>
            </div>


            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5 w-40" style=" margin-right: 22% !important;">

                    <div class="d-flex">
                        <h6>MOI Motilidad Ocular Intrínseca</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5" style=" margin-right: 23% !important;">

                    <div class="d-flex">
                        <h6>MOE Motilidad Ocular Extrínseca</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-start mt-5">
                <h5 style="margin-left: 5% !important;">Examen Biomicroscópico (BMC)</h5>
            </div>

            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5" style=" margin-right:80px !important;">

                    <div class="d-flex">
                        <h6>Córnea</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5" style=" margin-right:140px !important;">

                    <div class="d-flex">
                        <h6>Cámara Anterior</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                    </div>
                </div>
            </div>


            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5" style=" margin-right:58px !important;">

                    <div class="d-flex">
                        <h6>Iris</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center mt-5">

                <div class="d-flex flex-row align-items-center ps-5 pe-5" style=" margin-right:98px !important;">

                    <div class="d-flex">
                        <h6>Cristalino</h6>
                    </div>

                    <div class="d-flex me-5 justify-content-between">

                        <div style="margin-right: 125px !important; margin-left: 70px !important;">

                            <select name="" id="">
                                <option value="" selected disabled>Seleccione.</option>
                            </select>
                        </div>

                        <select name="" id="">
                            <option value="" selected disabled>Seleccione.</option>
                        </select>

                    </div>
                </div>
            </div>

        <!--</div> -->
    </div>

</div>


<?= Html::submitButton('Siguiente', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

<?php ActiveForm::end(); ?>
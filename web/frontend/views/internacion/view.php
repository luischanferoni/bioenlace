<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

use common\models\SegNivelInternacion;
use common\models\Servicio;
use yii\bootstrap5\Modal;

use webvimark\modules\UserManagement\models\User;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacion */

$this->title = "Internación";
$this->params['breadcrumbs'][] = ['label' => 'Internaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

$this->registerJsFile(
    '@web/js/consultas.js',
    ['depends' => [\yii\web\JqueryAsset::class]]
);

?>

<?= $this->render('_modal_alta.php', ['model' => $model]); ?>

<div class="seg-nivel-internacion-view">

    <div class="card">
        <div class="card-header bg-soft-info d-flex align-items-self justify-content-between">
            <h3 class="card-title mt-1"><?= "Cama " . $model->cama->nro_cama . " en Sala " . $model->cama->sala->descripcion . " del Piso/Sector " . $model->cama->sala->piso->descripcion; ?></h3>
            <div class="btn-group" role="group">
                <?php if (Yii::$app->user->getIdEfector() === $model->cama->sala->piso->id_efector) : ?>
                    <?php if ($model->enableCambioCama()) : ?>
                        <?= Html::a(
                            '<svg fill="#000000" width="20" height="20" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                            <g data-name="Layer 21" id="Layer_21">                            
                            <path d="M29,13a1,1,0,0,0-1,1v1.51A2.53,2.53,0,0,0,26.5,15H11.72A2,2,0,0,0,12,14V13a2,2,0,0,0-2-2H5a2,2,0,0,0-1,.28V10a1,1,0,0,0-2,0V29a1,1,0,0,0,2,0V21H28v8a1,1,0,0,0,2,0V14A1,1,0,0,0,29,13ZM5,13h5v1H5Zm21.5,5H5.5a.5.5,0,0,1,0-1h21a.5.5,0,0,1,0,1Z"/>                            
                            <path d="M18,8h1V9a1,1,0,0,0,2,0V8h1a1,1,0,0,0,0-2H21V5a1,1,0,0,0-2,0V6H18a1,1,0,0,0,0,2Z"/>                            
                            <path d="M20,12a5,5,0,1,0-5-5A5,5,0,0,0,20,12Zm0-8a3,3,0,1,1-3,3A3,3,0,0,1,20,4Z"/>                            
                            </g>                            
                            </svg>'
                                . ' Cambiar Cama',
                            ['internacion-hcama/create', 'id' => $model->id],
                            [
                                'class' => 'btn btn-primary rounded-pill mt-2 me-2',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'top',
                                'data-bs-original-title' => 'Cambiar Cama',
                            ]
                        ) ?>
                    <?php endif; ?>
                    <?php

                    if ($puedeAtender && (!$model->internacionConAlta())) : ?>
                        <?= Html::a(
                            '<svg width="20" height="20" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <defs>                        
                        <style>.cls-1{fill:#ff005c;}</style>                        
                        </defs>                                               
                        <g id="stethoscope">                        
                        <path d="M24,12a4,4,0,0,0-1,7.86V22a6,6,0,0,1-6,6,6,6,0,0,1-6-6V19h1l1-1V16.41A8,8,0,0,0,18,9V4L17,3H14V5h2V9a6,6,0,0,1-6,6,5.81,5.81,0,0,1-.86-.06A6,6,0,0,1,4,9V5H6V3H3L2,4V9a8.06,8.06,0,0,0,5,7.41V18l1,1H9v3a8,8,0,0,0,16,0V19.86A4,4,0,0,0,24,12Zm0,6a2,2,0,1,1,2-2A2,2,0,0,1,24,18Z"/>                        
                        <circle class="cls-1" cx="24" cy="16" r="2"/>                        
                        </g>                        
                        </svg>' .
                                ' Atender',
                            $urlSiguiente,
                            [
                                'class' => 'atender modal-consulta me-2 btn btn-warning rounded-pill mt-2',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'top',
                                'data-bs-original-title' => 'Atender Paciente',
                            ]
                        ) ?>
                    <?php endif; ?>

                    <?php if ($model->enableExternacion()) : ?>
                        <?= Html::a(
                            '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-clipboard2-check" viewBox="0 0 16 16">
                                                    <path d="M9.5 0a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5V2a.5.5 0 0 1-.5.5h-5A.5.5 0 0 1 5 2v-.5a.5.5 0 0 1 .5-.5.5.5 0 0 0 .5-.5.5.5 0 0 1 .5-.5h3Z"/>
                                                    <path d="M3 2.5a.5.5 0 0 1 .5-.5H4a.5.5 0 0 0 0-1h-.5A1.5 1.5 0 0 0 2 2.5v12A1.5 1.5 0 0 0 3.5 16h9a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 12.5 1H12a.5.5 0 0 0 0 1h.5a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5v-12Z"/>
                                                    <path d="M10.854 7.854a.5.5 0 0 0-.708-.708L7.5 9.793 6.354 8.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0l3-3Z"/>
                                                </svg> Externación',
                            ['update', 'id' => $model->id],
                            [
                                'class' => 'modal-alta-show-link btn btn-info rounded-pill mt-2',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'top',
                                'data-bs-original-title' => 'Alta administrativa',
                            ]
                        ) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>


    </div>

    <div class="row">

        <div class="col-md-4 col-sm-12 d-flex w-30">

            <div class="card w-100">
                <div class="card-header">
                    <h4>Datos Personales</h4>
                </div>

                <div class="card-body">

                    <?php $model_persona = $model->paciente; ?>

                    <div class="mt-2 mb-2 ms-2">
                        <p class="d-inline text-dark" style="font-weight: bold;">Nombre: </p> <?= $model_persona->nombre . " " . $model_persona->otro_nombre ?>
                    </div>

                    <div class="mb-2 ms-2">
                        <p class="d-inline text-dark" style="font-weight: bold;">Apellido: </p> <?= $model_persona->apellido . " " . $model_persona->otro_apellido ?>
                    </div>

                    <div class="mb-2 ms-2">
                        <p class="d-inline text-dark" style="font-weight: bold;">DNI: </p> <?= $model_persona->documento ?>
                    </div>

                    <div class="mb-2 ms-2">
                        <p class="d-inline text-dark" style="font-weight: bold;">Fecha de Nacimiento: </p> <?= Yii::$app->formatter->format($model_persona->fecha_nacimiento, 'date') ?>
                    </div>

                </div>

            </div>
        </div>


        <div class="col-md-4 col-sm-12 d-flex">
            <div class="card w-100">
                <div class="card-header">
                    <h4>Personal Solicitante</h4>
                </div>
                <div class="card-body">

                    <div class="mt-2 mb-2 ms-2">
                        <p class="d-inline text-dark" style="font-weight: bold;">Profesional a cargo: </p> <?= $model_rrhh->rrhhEfector->persona->apellido . " " . $model_rrhh->rrhhEfector->persona->otro_apellido . ', ' . $model_rrhh->rrhhEfector->persona->nombre . " " . $model_rrhh->rrhhEfector->persona->otro_nombre; ?>
                    </div>

                    <div class="mb-2 ms-2">
                        <?php foreach ($datosProfesional as $profesion => $arrayEspecialidades) { ?>
                            <p class="d-inline text-dark" style="font-weight: bold;">Profesion: </p>
                            <?= $profesion . "<br>" ?>
                            <?php foreach ($arrayEspecialidades as $especialidad) { ?>
                                <?= $especialidad . "<br>" ?>
                            <?php } ?>

                        <?php } ?>



                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-sm-12 d-flex">
            <div class="card w-100">
                <div class="card-header">
                    <h4>Datos de Ingreso</h4>
                </div>
                <div class="card-body">


                    <?php
                    $extra_attrs = [];
                    $efector_origen = $model->efectorOrigen;
                    if (
                        $efector_origen &&
                        $model->id_tipo_ingreso == SegNivelInternacion::TIPO_INGRESO_DERIVACION
                    ) {
                        $extra_attrs[] = [
                            'label' => 'Origen',
                            'value' => $model->efectorOrigen->nombre,
                        ];
                    }
                    ?> <?= DetailView::widget([
                            'model' => $model,
                            'options' => ['class' => 'table table-sm'],
                            'template' => '<tr><th class="text-dark">{label}</th><td{contentOptions}>{value}</td></tr>',
                            'attributes' => array_merge([
                                'fecha_inicio',
                                'hora_inicio',
                                'fecha_fin',
                                'hora_fin'
                            ], $extra_attrs),
                        ]) ?>

                </div>
            </div>
        </div>

    </div>
    <?php if ($puedeAtender) { ?>
        <div class="row">
            <div class="col-sm-12 col-md-6 d-flex">
                <?= $this->render('v2/_view_sintomas', ['sintomas' => $sintomas]); ?>
            </div>
            <div class="col-sm-12 col-md-6 d-flex">
                <?= $this->render('v2/_view_diagnostico', ['diagnosticos' => $diagnosticos]); ?>
            </div>

            <div class="col-sm-12 col-md-6 d-flex">
                <?= $this->render('v2/_view_medicamentos', ['medicamentos' => $medicamentos]); ?>
            </div>

            <div class="col-sm-12 col-md-6 d-flex">
                <?= $this->render('v2/_view_practica', ['practicas' => $practicas]); ?>
            </div>

            <?php if (count($oftalmologias) > 0) { ?>
                
                <div class="col-sm-12 d-flex">
                    <?= $this->render('v2/_view_oftalmologia', ['oftalmologias' => $oftalmologias]); ?>
                </div>

            <?php } ?>

            <div class="col-sm-12 d-flex">
                <?= $this->render('v2/_view_evolucion', ['evoluciones' => $evoluciones]); ?>
            </div>


            <div class="col-sm-12 d-flex">
                <?= $this->render('v2/_view_enfermeria', ['atencionEnfermeria' => $atencionEnfermeria]); ?>
            </div>

            <div class="col-sm-12 d-flex">
                <?= $this->render(
                    '../consulta-balance-hidrico/_internacion_card.php',
                    ['balances_list' => $balances_list]
                ); ?>
            </div>
            <div class="col-sm-12 d-flex">
                <?= $this->render(
                    '../consulta-regimen/_internacion_card.php',
                    ['regimenes_list' => $regimenes_list]
                ); ?>
            </div>

            <?php /*
        <div class="col-sm-12 d-flex">
            <?= $this->render('v2/_view_suministros', ['evoluciones' => $evoluciones]); ?>
        </div>
        */ ?>

        </div>
    <?php } else { ?>
        <div class="card">
            <div class="card-header bg-soft-info">
                <h4>Historia clínica de la internación</h4>
            </div>
            <div class="card-body">
                <h4 class="text-center">Estos datos solo pueden ser visualizados por los profesionales de la salud.</h4>
            </div>
        </div>
    <?php } ?>
</div>



<?php
Modal::begin([
    'title' => '<h4 id="modal-title"></h4>',
    'id' => 'modal-consulta',
    'size' => 'modal-xl',
    'clientOptions' => ['backdrop' => 'static', 'keyboard' => false]
]);
echo "<div id='modal-content-consulta'></div>";
Modal::end();

<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

use common\models\SegNivelInternacion;
use yii\bootstrap5\Modal;
use yii\helpers\Url;
//use common\models\Consulta;
use common\models\ConsultasConfiguracion;


/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacion */

$this->title = "Internación";
$this->params['breadcrumbs'][] = ['label' => 'Internaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

$url = Url::toRoute(
    'internacion/view/'.$model->id.'?atender=true&id_servicio_rr_hh=8&encounter_class='.ConsultasConfiguracion::ENCOUNTER_CLASS_IMP.'&parent_id='.$model->id);
?>

<?= $this->render('_modal_alta.php', ['model'=> $model]);?>

<div class="seg-nivel-internacion-view">

    <div class="card">
        <div class="card-header bg-soft-info d-flex align-items-self justify-content-between">
            <h3 class="card-title mt-1"><?= "Cama " . $model->cama->nro_cama . " en Sala " . $model->cama->sala->descripcion . " del Piso/Sector " . $model->cama->sala->piso->descripcion; ?></h3>
            <div class="btn-group" role="group">
            <?php if($model->enableCambioCama()): ?>
            <?= Html::a(
                    'Cambiar Cama',
                    ['internacion-hcama/create', 'id' => $model->id],
                    ['class' => 'btn btn-soft-success btn-sm rounded-pill', 
                    'data-bs-toggle' => 'tooltip', 
                    'data-bs-placement' => 'top', 
                    'data-bs-original-title' => 'Cambiar Cama',
                    ]
                ) ?>
            <?php endif; ?>
            <?= Html::a(
                    'Atender',
                    $url,
                    ['class' => 'btn btn-soft-success btn-sm rounded-pill modal-consulta', 
                    'data-bs-toggle' => 'tooltip', 
                    'data-bs-placement' => 'top', 
                    'data-bs-original-title' => 'Atender Paciente',
                    ]
                ) ?>
            </div>
        </div>

      
    </div>


    <div class="row">
        <div class="col-sm-12">
            <div class="card-group mb-5 d-flex">
                <?php #BEGIN card datos personales ?>
                <div class="card me-4">
                    <div class="card-header">
                        <h5>Datos Personales</h5>
                    </div>
                    <div class="card-body" style="padding-top: 0;">
                        <?php $model_persona = $model->cama->internacionActual->paciente; ?>
                        <?= DetailView::widget([
                            'model' => $model_persona,
                            'options' => ['class' => 'table table-sm mt-2'],
                            'template' => '<tr><th class="text-dark">{label}</th><td{contentOptions}>{value}</td></tr>',
                            'attributes' => [
                                [
                                    'label' => 'Apellido y Nombre',
                                    'value' => $model_persona->apellido . " " . $model_persona->otro_apellido . ', ' . $model_persona->nombre . " " . $model_persona->otro_nombre,
                                ],
                                [
                                    'label' => 'Documento N°',
                                    'value' => $model_persona->documento,
                                ],
                                [
                                    'label' => 'Fecha de Nacimiento',
                                    'value' => Yii::$app->formatter->format($model_persona->fecha_nacimiento, 'date'),
                                ],
                            ],

                        ]);
                        ?>
                    </div>
                </div>
                <?php #END card datos personales ?>

                <?php #BEGIN card Diagnosticos y sintomas ?>
                <div class="card w-70">
                    <div class="card-header">
                        <div class="d-flex bd-highlight align-items-center">
                            <div class="bd-highlight">
                                <h5>Síntomas, Signos y Problemas</h5>
                            </div>
                            <div class="ms-auto bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                            <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>',
                                    ['/internacion-diagnostico/create', 'id' => $model->id],
                                    ['class' => 'btn btn-soft-warning btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Agregar Sintomatología']
                                ) ?>

                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (is_array($model->segNivelInternacionDiagnosticos)) { ?>
                            <table id="diagnosticos" class="table table-striped table-bordered detail-view">
                                <tbody>
                                    <?php foreach ($model->segNivelInternacionDiagnosticos as $key => $diagnostico) { ?>
                                        <tr>
                                            <td>
                                                <?= $diagnostico->diagnosticoSnomed->term; ?>
                                            </td>
                                            <td>
                                                <?= Html::a('<i class="glyphicon glyphicon-trash"></i>', ['internacion-diagnostico/delete', 'id' => $diagnostico->id, 'id_internacion' => $diagnostico->id_internacion], [
                                                    'class' => 'btn btn-danger btn-sm',
                                                    'title' => 'Eliminar',
                                                    'data' => [
                                                        'confirm' => '¿Está seguro de elimir este elemento?',
                                                        'method' => 'post',
                                                    ],
                                                ]) ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php }
                        ?>
                    </div>
                </div>
                <?php #END card Diagnosticos y sintomas ?>
                
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-sm-12">
            <div class="card-group mb-5">

            <div class="card card me-4">
                <div class="card-header">
                    <h5>Profesional Solicitante</h5>
                </div>
                <div class="card-body" style="padding-top: 0;">
                    <table id="w2" class="table table-sm mt-2">
                        <tbody>
                            <tr>
                                <th class="text-dark">Profesional a cargo</th>
                                <td><?= $model_rrhh->persona->apellido . " " . $model_rrhh->persona->otro_apellido . ', ' . $model_rrhh->persona->nombre . " " . $model_rrhh->persona->otro_nombre; ?></td>
                            </tr>
                            <tr>
                                <th class="text-dark">Profesion</th>
                                <td><?= $model_rrhh->profesion->nombre ?><?php if (isset($model_rrhh->especialidad)) echo " " . $model_rrhh->especialidad->nombre ?> </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex bd-highlight align-items-center">
                            <div class="bd-highlight">
                                <h5>Prácticas</h5>
                            </div>
                            <div class="ms-auto bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                         <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>',
                                    ['/internacion-practica/create', 'id' => $model->id],
                                    ['class' => 'btn btn-soft-warning btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Solicitar Practicas']
                                ) ?>

                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (is_array($model->segNivelInternacionPracticas)) { ?>
                            <table id="diagnosticos" class="table table-striped table-bordered detail-view">
                                <tbody>
                                    <?php foreach ($model->segNivelInternacionPracticas as $key => $practica) { ?>
                                        <tr>
                                            <td>
                                                <?= $practica->practicaSnomed->term; ?>
                                            </td>
                                            <td>
                                                <?php if ($practica->resultado == "") {
                                                    echo  Html::a('<i class="glyphicon glyphicon-edit"></i>', ['internacion-practica/update', 'id' => $practica->id], ['class' => 'btn btn-primary btn-sm', 'title' => 'Cargar Resultado']);
                                                } else {
                                                    echo  Html::a('<i class="glyphicon glyphicon-eye-open"></i>', ['internacion-practica/view', 'id' => $practica->id], ['class' => 'btn btn-success btn-sm', 'title' => 'Ver Resultado']);
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php }
                        ?>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="card-group mb-5">

            <div class="card">
                    <div class="card-header" style="padding-bottom: 1.05rem;">
                        <div class="d-flex bd-highlight">
                            <div class="me-auto bd-highlight">
                                <h5>Datos de Ingreso</h5>
                            </div>
                            <div class="pe-2 bd-highlight">
                                <?php if($model->enableExternacion()): ?>
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-clipboard2-check" viewBox="0 0 16 16">
                                                <path d="M9.5 0a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5V2a.5.5 0 0 1-.5.5h-5A.5.5 0 0 1 5 2v-.5a.5.5 0 0 1 .5-.5.5.5 0 0 0 .5-.5.5.5 0 0 1 .5-.5h3Z"/>
                                                <path d="M3 2.5a.5.5 0 0 1 .5-.5H4a.5.5 0 0 0 0-1h-.5A1.5 1.5 0 0 0 2 2.5v12A1.5 1.5 0 0 0 3.5 16h9a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 12.5 1H12a.5.5 0 0 0 0 1h.5a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5v-12Z"/>
                                                <path d="M10.854 7.854a.5.5 0 0 0-.708-.708L7.5 9.793 6.354 8.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0l3-3Z"/>
                                            </svg>',
                                    ['update', 'id' => $model->id],
                                    ['class' => 'btn btn-soft-success btn-sm rounded-pill modal-alta-show-link', 
                                    'data-bs-toggle' => 'tooltip', 
                                    'data-bs-placement' => 'top', 
                                    'data-bs-original-title' => 'Externacion',
                                    ]
                                ) ?>
                                <?php endif; ?>
                            </div>
                            <div class="bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
                                            <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5ZM11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H2.506a.58.58 0 0 0-.01 0H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1h-.995a.59.59 0 0 0-.01 0H11Zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5h9.916Zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47ZM8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5Z"/>
                                    </svg>',
                                    ['delete', 'id' => $model->id],
                                    [
                                        'class' => 'btn btn-soft-danger btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Eliminar Internacion',
                                        'data' => [
                                            'confirm' => 'Realmente desea borrar este registro?',
                                            'method' => 'post',
                                        ],
                                    ]
                                ) ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="padding-top: 0;">
                      <?php
                      $extra_attrs = [];
                      $efector_origen = $model->efectorOrigen;
                      if ($efector_origen &&
                          $model->id_tipo_ingreso == SegNivelInternacion::TIPO_INGRESO_DERIVACION
                        ) {
                        $extra_attrs[] = [
                            'label' => 'Origen',
                            'value' => $model->efectorOrigen->nombre,
                          ];
                      }
                      ?>
                        <?= DetailView::widget([
                            'model' => $model,
                            'options' => ['class' => 'table table-sm mt-2'],
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

                <div class="card me-4">
                    <div class="card-header">
                        <div class="d-flex bd-highlight align-items-center">
                            <div class="bd-highlight">
                                <h5>Medicamentos</h5>
                            </div>
                            <div class="ms-auto bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                         <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>',
                                    ['/internacion-medicamento/create', 'id' => $model->id],
                                    ['class' => 'btn btn-soft-warning btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Agregar Medicamento']
                                ) ?>

                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (is_array($model->segNivelInternacionMedicamentos)) { ?>
                            <table id="diagnosticos" class="table table-striped table-bordered detail-view">
                                <tbody>
                                    <?php foreach ($model->segNivelInternacionMedicamentos as $key => $medicamento) { ?>
                                        <tr>
                                            <td>
                                                <?= $medicamento->medicamentoSnomed->term; ?>
                                            </td>
                                            <td>
                                                <?= Html::a('<i class="glyphicon glyphicon-eye-open"></i>', ['internacion-medicamento/view', 'id' => $medicamento->id], ['class' => 'btn btn-primary btn-sm']);
                                                ?>
                                                <?php if ($medicamento->user_suspencion == 0) {
                                                    echo Html::a('<i class="glyphicon glyphicon-ban-circle"></i>', ['internacion-medicamento/suspender', 'id' => $medicamento->id, 'id_internacion' => $medicamento->id_internacion], [
                                                        'class' => 'btn btn-danger btn-sm',
                                                        'title' => 'Suspender',
                                                        'data' => [
                                                            'confirm' => '¿Está seguro de suspender este medicamento?',
                                                            'method' => 'post',
                                                        ],
                                                    ]);
                                                }
                                                ?>

                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php }
                        ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex bd-highlight align-items-center">
                            <div class="bd-highlight">
                                <h5>Suministros</h5>
                            </div>
                            <div class="ms-auto bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                         <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>',
                                    ['/internacion-suministro-medicamento/create', 'idi' => $model->id],
                                    ['class' => 'btn btn-soft-warning btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Agregar Suministro']
                                ) ?>

                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                    <?php
                        if (is_array($model->segNivelInternacionSuministroMedicamentos)) { ?>
                            <table id="diagnosticos" class="table table-striped table-bordered detail-view">
                                <tbody>
                                    <?php foreach ($model->segNivelInternacionSuministroMedicamentos as $key => $suminstroMedicamento) { ?>
                                        <tr>
                                            
                                            <td>
                                                <?= Yii::$app->formatter->asDateTime($suminstroMedicamento->fecha, 'php:d-m-Y').'  '.$suminstroMedicamento->hora; ?>
                                            </td>
                                            <td>
                                                <?= $suminstroMedicamento->internacionMedicamento->medicamentoSnomed->term; ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="card-group mb-5">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex bd-highlight align-items-center">
                            <div class="bd-highlight">
                                <h5>Atenciones de Enfermer&iacute;a</h5>
                            </div>
                            <div class="ms-auto bd-highlight">
                                <?= Html::a(
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                         <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>',
                                    ['/internacion-atenciones-enfermeria/create', 'id' => $model->id],
                                    ['class' => 'btn btn-soft-warning btn-sm rounded-pill', 'data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top', 'data-bs-original-title' => 'Agregar Atenciones']
                                ) ?>

                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <?php
                            if (is_array($model->segNivelInternacionAtencionesEnfermeria)) {
                                echo '<tr><th>Fecha control/atención</th>'
                                    . '<th>Control/Atención</th>';
                                echo '<th>Observaciones</th>';
                                echo '<th>Profesional</th>';
                                echo '<th>Acciones</th>';
                                echo '</tr>';
                            }

                            foreach ($model->segNivelInternacionAtencionesEnfermeria as $key => $value) {
                                $datos = json_decode($value->datos, TRUE);
                                $valores = mostrarDato($datos);
                                $indice = 1;

                                echo '<tr>';
                                echo '<td>';
                                echo $datos['SegNivelInternacionAtencionesEnfermeria']['fecha'];
                                echo " " . $datos['SegNivelInternacionAtencionesEnfermeria']['hora'] . " hs";
                                //echo Yii::$app->formatter->asDate($value->created_at, 'dd/MM/yyyy');
                                echo '</td>';
                                echo '<td>';
                                foreach ($valores as $clave => $valor) {
                                    echo $valor;
                                    echo '<br/>';
                                }
                                echo '</td>';
                                echo '<td>';
                                echo $value->observaciones;
                                echo '</td>';
                                echo '<td>';
                                if (is_object($value->user)) {
                                    echo $value->user->nombre . ' ' . $value->user->apellido;
                                }
                                echo '</td>';
                                echo '<td>';
                                echo Html::a(
                                    '<span class="glyphicon glyphicon-trash" aria-hidden="true"> </span>',
                                    ['internacion-atenciones-enfermeria/delete', 'id' =>  $value->id],
                                    [
                                        'data-confirm' => '¿Está seguro de eliminar este elemento?',
                                        'data-method' => 'post', 'data-pjax' => 0
                                    ]
                                );
                                echo '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
function mostrarDato($datos)
{

    if (is_array($datos)) {
        $valores = [];
        foreach ($datos as $key => $value) {
            switch ($key) {
                case 'sistolica':
                case '271649006':
                    $valores[0] = '<strong>Tensi&oacuten Arterial:</strong> ';
                    $valores[0] .= $value . '/';
                    break;
                case 'diastolica':
                case '271650006':
                    if (isset($valores[0])) {
                        $valores[0] .= $value;
                    } else {
                        $valores[0] = $value;
                    }
                    break;
                case 'TensionArterial1':
                    $valores[0] = '<strong>Tensi&oacuten Arterial #1:</strong> ';
                    $valores[0] .= $value[271649006] . '/' . $value[271650006] . '<br/>';
                    break;
                case 'TensionArterial2':
                    $valores[0] .= '<strong>Tensi&oacuten Arterial #2:</strong> ';
                    $valores[0] .= $value[271649006] . '/' . $value[271650006];
                    break;
                case 'peso':
                case '162879003p':
                    $valores[1] = '<strong>Peso/Talla:</strong> ';
                    $valores[1] .= 'P: ' . $value . 'kg. - ';
                    break;
                case 'talla':
                case '162879003t':
                    if (isset($valores[1])) {
                        $valores[1] .= 'T: ' . $value . 'cm.';
                    } else {
                        $valores[1] = 'T: ' . $value . 'cm.';
                    }
                    break;
                case 'agudeza_ojo_izquierdo':
                case '386708005':
                    $valores[2] = '<strong>Agudeza Visual:</strong> ';
                    $valores[2] .= 'OI: ' . $value . ' - ';
                    break;
                case 'agudeza_ojo_derecho':
                case '386709002':
                    $valores[2] .= 'OD: ' . $value;
                    break;
                case 'temperatura':
                case '703421000':
                    $valores[3] = '<strong>Temperatura:</strong> ';
                    $valores[3] .= $value . 'º';
                    break;
                case 'glucemia_capilar':
                case '434912009':
                    $valores[4] = '<strong>Glucemia Capilar:</strong> ';
                    $valores[4] .= $value;
                    break;
                case 'circunferencia_abdominal':
                case '396552003':
                    $valores[5] = '<strong>Circunferencia Abdominal:</strong> ';
                    $valores[5] .= $value . 'cm.';
                    break;
                case 'perimetro_cefalico':
                case '363812007':
                    $valores[6] = '<strong>Perimetro Cefálico:</strong> ';
                    $valores[6] .= $value . 'cm.';
                    break;
                case 'nebulizacion':
                    $valores[7] = '<strong>Nebulización:</strong> ';
                    $valores[7] .= 'SI';
                    break;
                case 'rescate_sbo':
                    $valores[8] = '<strong>Rescate y SBO:</strong> ';
                    $valores[8] .= 'SI';
                    break;
                case 'inyectable':
                    $valores[9] = '<strong>Inyectable:</strong> ';
                    $valores[9] .= 'SI';
                    break;
                case 'inmunizacion':
                    $valores[10] = '<strong>Inmunización:</strong> ';
                    $valores[10] .= 'SI';
                    break;
                case 'extraccion_puntos':
                    $valores[11] = '<strong>Extracción Puntos:</strong> ';
                    $valores[11] .= 'SI';
                    break;
                case 'curacion':
                    $valores[12] = '<strong>Curación:</strong> ';
                    $valores[12] .= 'SI';
                    break;
                case 'internacion_abreviada':
                    $valores[13] = '<strong>Internacion Abreviada:</strong> ';
                    $valores[13] .= 'SI';
                    break;
                case 'visita_domiciliaria':
                    $valores[14] = '<strong>Visita Domiciliaria:</strong> ';
                    $valores[14] .= 'SI';
                    break;
                case 'electrocardiograma':
                    $valores[15] = '<strong>Electrocardiograma:</strong> ';
                    $valores[15] .= 'SI';
                    break;
                case 'temperatura':
                case '415882003':
                    $valores[16] = '<strong>Temperatura:</strong> ';
                    $valores[16] .= 'Axial: ' . $value . ' - ';
                    break;
                case '307047009':
                    if (isset($valores[16])) {
                        $valores[16] .= 'Rectal: ' . $value;
                    } else {
                        $valores[16] = '<strong>Temperatura:</strong> ';
                        $valores[16] = 'Rectal: ' . $value;
                    }
                    break;
                case '307047010':
                    if (isset($valores[16])) {
                        $valores[16] .= 'Digital: ' . $value;
                    } else {
                        $valores[16] = '<strong>Temperatura:</strong> ';
                        $valores[16] = 'Digital: ' . $value;
                    }
                    break;
                case 'medicion_diuresis':
                case '364200006':
                    $valores[17] = '<strong>Medición Diuresis:</strong> ';
                    $valores[17] .= $value . ' ml ';
                    break;
                case 'orina':
                    $valores[18] = '<strong>Orina:</strong> ';
                    $valores[18] .= $value;
                    break;
                case 'heces':
                    $valores[19] = '<strong>Heces:</strong> ';
                    $valores[19] .= $value;
                    break;
                case '434912009':
                    $valores[20] = '<strong>Glucemia Capilar:</strong> ';
                    $valores[20] .= $value;
                    break;
                case '396552003':
                    $valores[21] = '<strong>Circunferencia Abdominal:</strong> ';
                    $valores[21] .= $value . " cm";
                    break;
                case '363812007':
                    $valores[22] = '<strong>Perimetro Cefalico:</strong> ';
                    $valores[22] .= $value . " cm";
                    break;
                case '363812007':
                    $valores[23] = '<strong>Frecuencia Cardíaca:</strong> ';
                    $valores[23] .= $value . " ";
                    break;
                case '86290005':
                    $valores[24] = '<strong>Frecuencia Respiratoria:</strong> ';
                    $valores[24] .= $value . " ";
                    break;
                case '103228002':
                    $valores[25] = '<strong>Saturación de Oxígeno:</strong> ';
                    $valores[25] .= $value . " ";
                    break;
                case '8499008':
                    $valores[26] = '<strong>Pulso:</strong> ';
                    $valores[26] .= $value . " ";
                    break;
                default:
                    break;
            }
        }
        return $valores;
    }
}
?>


<?php
Modal::begin([
    'title' => '<h4 id="modal-title"></h4>',
    'id' => 'modal-consulta',
    'size' => 'modal-lg',
    'clientOptions' => ['backdrop' => 'static', 'keyboard' => false]
]);
echo "<div id='modal-content-consulta'></div>";
Modal::end();

$this->registerJs(
    "
    let mostrarModalConsulta = " . ($atender ? 'true' : 'false') . ";

    $(document).ready(function() {
        
        function lanzarModalConsulta(url) {
            if (url == 'error') {
                alertaFlotante('Hubo un error al intentar crear esta consulta', 'danger');
                return;
            }

            $.ajax({
                url: url,
                type: 'GET',
                success: function (data) {
                    //$('#offcanvas_consulta .offcanvas-body').html(data);
                    $('#modal-consulta .modal-body').html(data);
                },
                error: function () {
                    alertaFlotante('Listo', 'danger');
                }
            });

            //$('#offcanvas_consulta').offcanvas('show');
            $('#modal-consulta').modal('show');
        }

        $(function() {
            $('#modal-consulta').on('submit', 'form', function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = form.serialize();
                $.ajax({
                    url: form.attr('action'),
                    type: form.attr('method'),
                    data: formData,
                    success: function (data) {
                        if(typeof(data.success) !== 'undefined') {
                            alertaFlotante(data.success, 'success');

                            if(typeof(data.url_siguiente) !== 'undefined') {
                                if(data.url_siguiente === 'fin') {
                                    alertaFlotante('Consulta Finalizada', 'success');
                                    $('#modal-consulta').modal('hide');
                                    return;
                                } else {
                                    lanzarModalConsulta(data.url_siguiente);
                                }
                            }
                        } else {
                            if(typeof(data.error) !== 'undefined') {
                                alertaFlotante(data.error, 'danger');
                            } else {
                                $('#modal-consulta .modal-body').html(data);
                            }
                        }
                    },
                    error: function () {
                        $('#modal-consulta .modal-body').append('<div class=\"alert alert-success\" role=\"alert\">'
                            +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>');
                        window.setTimeout(function() { $('.alert').alert('close'); }, 6000);
                    }
                });
                
            });
        });

        // el modal para la consulta se puede lanzar de forma automatica
        // mediante un parametro recibido por url, o escuchando el evento click
        // de lo que tenga class .atender
        if (mostrarModalConsulta == true) {
            lanzarModalConsulta('" . $atender . "');
        }

        $('.atender').click(function(e) {
            e.preventDefault();
            let url = yii.getBaseCurrentUrl() + $(this).attr('href');

            lanzarModalConsulta(url);
        });

        $('.botonModal').click(function(e) {
            e.preventDefault();
            let url = yii.getBaseCurrentUrl() + $(this).attr('href');
            let title = $(this).attr('data-title')

            $.ajax({
                url: url,
                type: 'GET',
                success: function (data) {
                    $('#modal-general .modal-header #modal-title').text(title);
                    $('#modal-general .modal-body').html(data);
                },
                error: function () {
                    alertaFlotante('Listo', 'danger');
                }
            });
            $('#modal-general').modal('show');
        
        });

        $('.eliminar_cambiar_estado').click(function(e) {
            e.preventDefault();
            let parent = $(this).parent();
            sweetAlertConfirm($(this).attr('alert_title'))
                .then((result) => {
                    if (result.isConfirmed) {
                        let url = yii.getBaseCurrentUrl() + $(this).attr('href');

                        $.ajax({
                            url: url,
                            type: 'POST',
                            data: JSON.parse($(this).attr('post_data')),
                            success: function (data) {                                
                                alertaFlotante('Listo', 'success');
                                parent.html(data);
                            },
                            error: function () {
                                alertaFlotante('Ocurrió un error', 'danger');
                            }
                        });
                    }
                });
        });        
    });
    "
);


?>
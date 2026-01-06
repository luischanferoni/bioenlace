<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\bootstrap5\Modal;
use common\components\SisseGhostHtml;

use webvimark\modules\UserManagement\models\User;

use common\models\persona_telefono;
use common\models\Tipo_telefono;
use common\models\Localidad;

/* @var $this yii\web\View */
/* @var $model common\models\persona */

//$this->title = $model->id_persona; 
$this->title = "Datos Personales de: " . $model->apellido . " " . $model->otro_apellido . ", " . $model->nombre . " " . $model->otro_nombre;

$this->params['breadcrumbs'][] = ['label' => 'Personas', 'url' => ['buscar-persona']];
$this->params['breadcrumbs'][] = $this->title;
?>

<style>
    .persona-view-data {
        width: 73%;
        display: inline-block;
        vertical-align: middle;
    }

    .persona-view-more-links {
        width: 25%;
        padding-left: 3rem;
        display: inline-block;
        vertical-align: top;
    }
</style>

<div class="persona-view">
    <div class="persona-view-data" style="clear:left">
        <table class="table table-striped table-bordered detail-view">
            <tbody>
                <tr>
                    <th>Apellido y Nombre</th>
                    <td><?= $model->apellido . " " . $model->otro_apellido . ', ' . $model->nombre . " " . $model->otro_nombre ?></td>
                </tr>
                <tr>
                    <th>Documento N°</th>
                    <td><?= $model->documento ?></td>
                </tr>
                <tr>
                    <th>Fecha de Nacimiento</th>
                    <td><?= Yii::$app->formatter->format($model->fecha_nacimiento, 'date') ?></td>
                </tr>
                <tr>
                    <th style="width: 123px">Teléfono</th>
                    <td>
                        <?php
                        $tels = $model->telefonos;
                        foreach ($tels as $tells) {
                            echo $tells->numero . ' - ' . $tells->tipoTelefono->nombre . '<br>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th style="width: 123px">Email</th>
                    <td>
                        <?php
                        $mailsxpersona = $model->mails;
                        foreach ($mailsxpersona as $email) {

                            echo $email->mail . '<br>';
                            /* echo ' - <a data-pjax="0" aria-label="Actualizar" title="Actualizar" '
                    . 'href="'.Url::toRoute(['domicilios/update', 'id' => $domi['id_persona_mail'], 'idp' => $model->id_persona]).'">'
                    . '<span class="glyphicon glyphicon-pencil"></span></a> <br>';*/
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th style="width: 123px">Domicilio</th>
                    <td>
                        <?php
                        $domicilios = $model->domicilios;
                        foreach ($domicilios as $domi) {
                            $domicilio = $domi->domicilio;
                            $zona = ($domicilio->urbano_rural == 'U') ? 'Urbana' : 'Rural';
                            $barrio = is_object($domicilio->modelBarrio) ? $domicilio->modelBarrio->nombre : "--";
                            $activo = ($domi->activo == 'SI') ? 'Activo' : 'Inactivo';
                            echo " <strong>Calle</strong>: " . $domicilio->calle
                                . " - <strong>Número</strong>: " . $domicilio->numero
                                . " - <strong>Mzn</strong>: " . $domicilio->manzana
                                . " - <strong>Lote</strong>: " . $domicilio->lote
                                . " - <strong>Sector</strong>: " . $domicilio->sector
                                . " - <strong>Gpo</strong>: " . $domicilio->grupo
                                . " - <strong>Torre</strong>: " . $domicilio->torre
                                . " - <strong>Depto</strong>: " . $domicilio->depto
                                . "<br>  <strong>B°</strong>: " . $barrio
                                . " - <strong>Localidad</strong>: " . $domicilio->localidad->nombre
                                . " - <strong>Lat</strong>: " . $domicilio->latitud
                                . " - <strong>Log</strong>: " . $domicilio->longitud
                                . " - <strong>Zona</strong>: " . $zona;
                            echo " - (Domicilio " . $activo . ")<br><br>";
                            /* echo ' - <a data-pjax="0" aria-label="Actualizar" title="Actualizar" '
                    . 'href="'.Url::toRoute(['domicilios/update', 'id' => $domi['id_domicilio'], 'idp' => $model->id_persona]).'">'
                    . '<span class="glyphicon glyphicon-pencil"></span></a> <br>';*/
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="persona-view-more-links">

        <h4>Más datos</h4>
        <?= Html::a(
            '<span class="glyphicon glyphicon-heart-empty" aria-hidden="true"></span> Coberturas',
            ['personas/viewpuco', 'dni' => $model->documento, 'sexo' =>  $model->sexo_biologico],
            $options = [
                'class' => 'modalGeneral',
                'title' => 'Coberturas',
                'data-title' => 'Coberturas'
            ]
        ); ?>
        </br>
        <?= Html::a(
            '<span class="glyphicon glyphicon-pushpin" aria-hidden="true"></span> Vacunas',
            ['personas/vacunas', 'dni' => $model->documento, 'sexo' =>  $model->sexo_biologico],
            $options = [
                'class' => 'modalGeneral',
                'title' => 'Vacunas',
                'data-title' => 'Vacunas'
            ]
        ); ?>
        </br>

        <h4>Administración</h4>
        <?php
        if ($model->id_user != 0) {

            echo SisseGhostHtml::a(
                'Editar acceso a BIOENLACE',
                ['/user/update', 'id' => $model->id_user],
                ['class' => 'ms-2', 'title' => 'Editar datos de acceso a BIOENLACE']
            );
        } else {
            echo Html::a(
                '<span class="glyphicon glyphicon-user" aria-hidden="true"></span> Generar Usuario',
                ['user/crear'],
                $options = ['title' => 'Generar Usuario']
            );
        }
        ?>

        <?php
        echo SisseGhostHtml::a(
            'Administrar RRHH',
            ['/rrhh-efector/create'],
            ['class' => 'ms-2', 'title' => 'Administrar persona como recurso humano']
        );
        ?>
    </div>
</div>

<?php
Modal::begin([
    'id' => 'modal-general',
    'size' => 'modal-lg',
]);
echo "<div id='modal-content'></div>";
Modal::end();
?>
<?php
$this->registerJs(
    "$(function(){
            
            $(function(){
                $('#modal-general').on('submit', 'form', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    var formData = form.serialize();
                    $.ajax({
                        url: form.attr('action'),
                        type: form.attr('method'),
                        data: formData,
                        success: function (data) {
                            if(typeof(data.success) !== 'undefined') {
                                location.reload();
                               $('#modal-general').modal('hide');                                
                            } else {
                                $('#modal-content').html(data);                            
                            }
                        },
                        error: function () {
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>'); 
                            window.setTimeout(function() { $('.alert').alert('close'); }, 6000); 
                            $('#modal-general').modal('hide');
                        }
                    });
                    
                });            
            });
        });                        
    "
);
?>
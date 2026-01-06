<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
//use yii\widgets\ActiveForm;
use yii\bootstrap5\ActiveField;
use nex\chosen\Chosen;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use common\models\Provincia;
use common\models\Departamento;
use common\models\Barrios;
use kartik\depdrop\DepDrop;

// se agregan las librerias google-maps para obtener las coordenadas
//use dosamigos\google\maps\LatLng;
//use dosamigos\google\maps\services\DirectionsWayPoint;
//use dosamigos\google\maps\services\TravelMode;
//use dosamigos\google\maps\overlays\PolylineOptions;
//use dosamigos\google\maps\services\DirectionsRenderer;
//use dosamigos\google\maps\services\DirectionsService;
//use dosamigos\google\maps\overlays\InfoWindow;
//use dosamigos\google\maps\overlays\Marker;
//use dosamigos\google\maps\Map;
//use dosamigos\google\maps\services\DirectionsRequest;
//use dosamigos\google\maps\overlays\Polygon;
//use dosamigos\google\maps\layers\BicyclingLayer;

/* @var $this yii\web\View */
/* @var $model common\models\persona */
/* @var $form yii\widgets\ActiveForm */

$localidades = \common\models\Localidad::find()->indexBy('id_localidad')->asArray()->all();
$lista_localidades = \yii\helpers\ArrayHelper::map($localidades, 'id_localidad', 'nombre');
?>
<script src="//code.jquery.com/jquery-1.10.2.js"></script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=set_to_true_or_false"></script>
<script type="text/javascript">
    /*  $(document).ready(function () {
        load_map();
        var map;
        function load_map() {
            var myLatlng = new google.maps.LatLng(-27.809037, -64.276974);
            var myOptions = {
                zoom: 13,
                center: myLatlng,
                mapTypeId: google.maps.MapTypeId.ROADMAP,                
            };
            map = new google.maps.Map($("#map_canvas").get(0), myOptions);
        }

        $('#indicar').click(function () {
            // Obtenemos la dirección y la asignamos a una variable
            var address = $('#address').val() + ', Santiago del estero';
            // Creamos el Objeto Geocoder
            var geocoder = new google.maps.Geocoder();
            // Hacemos la petición indicando la dirección e invocamos la función
            // geocodeResult enviando todo el resultado obtenido
            geocoder.geocode({'address': address}, geocodeResult);
        });

        function geocodeResult(results, status) {
            // Verificamos el estatus
            if (status == 'OK') {
                // Si hay resultados encontrados, centramos y repintamos el mapa
                // esto para eliminar cualquier pin antes puesto
                var mapOptions = {
                    center: results[0].geometry.location,
                    mapTypeId: google.maps.MapTypeId.ROADMAP
                };
                map = new google.maps.Map($("#map_canvas").get(0), mapOptions);
                // fitBounds acercará el mapa con el zoom adecuado de acuerdo a lo buscado
                map.fitBounds(results[0].geometry.viewport);
                // Dibujamos un marcador con la ubicación del primer resultado obtenido
                var markerOptions = {position: results[0].geometry.location, draggable:true}
                var marker = new google.maps.Marker(markerOptions);
                marker.setMap(map);
                $("#domicilio-latitud").val(results[0].geometry.location.lat());
                $("#domicilio-longitud").val(results[0].geometry.location.lng());

                google.maps.event.addListener(marker, 'dragend', function (event) {
                    $("#domicilio-latitud").val(event.latLng.lat());
                    $("#domicilio-longitud").val(event.latLng.lng());
                });                
            } else {
                // En caso de no haber resultados o que haya ocurrido un error
                // lanzamos un mensaje con el error
                //alert("Geocoding no tuvo éxito debido a: " + status);
            }
        }*/
    // funcion para validar DNI y PUCO
    $("#persona-documento").blur(function() {

        $.ajax({
            type: "POST",
            url: '<?php echo Url::to(['personas/validardni']) ?>',
            data: {
                dni: $("#persona-documento").val(),
                nombre: $("#persona-nombre").val()
            },
            success: function(data) {
                $("div.field-persona-documento div.help-block").html(data);
            }
        });

        return false; // Evitar ejecutar el submit del formulario.
    });
    //$('#localidad-id_localidad').on('depdrop.change', function(event, id, value, count) {
    //   alert($('#localidad-id_localidad').val())
    //});
    /*$( "#localidad-id_localidad" ).change(function() {
      domicilio= $("#domicilio-calle").val()+" "+$("#domicilio-numero").val()+" "+$( "#localidad-id_localidad option:selected" ).text();
      $("#address").val(domicilio);
      var address = $('#address').val();
                // Creamos el Objeto Geocoder
                var geocoder = new google.maps.Geocoder();
                // Hacemos la petición indicando la dirección e invocamos la función
                // geocodeResult enviando todo el resultado obtenido
                geocoder.geocode({'address': address}, geocodeResult);
    });*/
    // });
</script>
<style>
    .panel-body {
        background-color: #F7F7F7
    }

    .center-block {
        float: none !important
    }
</style>
<div class="persona-form">

    <?php $form = ActiveForm::begin(['options' => ['class' => 'form-horizontal', 'id' => 'form-personas'],]); ?>
    <?= $form->errorSummary($model); ?>

    <?php if (!$model->isNewRecord && $model_persona_hc != null) { ?>

        <div class="card">
            <div class="card-header">
                <h2>Historia Clínica</h2>
            </div>
            <div class="card-body">
                <div class="col-sm-3">
                    <?php echo $form->field($model_persona_hc, 'numero_hc')->textInput(); ?>
                </div>
            </div>
        </div>

    <?php } ?>


    <div class="card">
        <div class="card-header">
            <h3>Datos Personales</h3>
        </div>
        <div class="card-body">

            <div class="row align-items-start">
                <?=
                Html::activeLabel($model, 'apellido', [
                    'label' => 'Apellido: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-3">
                    <?php
                    if ($model->isNewRecord) {

                        echo $form->field($model, 'apellido', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Persona"]["apellido"])) ? $_POST["Persona"]["apellido"] : '',]);
                    } else {
                        echo $form->field($model, 'apellido', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true,]);
                        echo $form->field($model, 'otro_apellido', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true,]);
                    }

                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'nombre', [
                    'label' => 'Nombre: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-3">
                    <?php
                    if ($model->isNewRecord) {
                        echo $form->field($model, 'nombre', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Persona"]["nombre"])) ? $_POST["Persona"]["nombre"] : '',]);
                    } else {
                        echo $form->field($model, 'nombre', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true,]);
                        echo $form->field($model, 'otro_nombre', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true,]);
                    }
                    ?>
                </div>
            </div>

            <div class="row align-items-start mt-3">

                <?=
                Html::activeLabel($model, 'apellido_paterno', [
                    'label' => 'Apellido Paterno: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-3">
                    <?php
                    if ($model->isNewRecord) {
                        echo $form->field($model, 'apellido_paterno', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Persona"]["apellido_paterno"])) ? $_POST["Persona"]["apellido_paterno"] : '',]);
                    } else {
                        echo $form->field($model, 'apellido_paterno', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true,]);
                    }

                    ?>
                </div>

                <?=
                Html::activeLabel($model, 'apellido_materno', [
                    'label' => 'Apellido Materno: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-3">
                    <?php
                    if ($model->isNewRecord) {
                        echo $form->field($model, 'apellido_materno', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Persona"]["apellido_materno"])) ? $_POST["Persona"]["apellido_materno"] : '',]);
                    } else {
                        echo $form->field($model, 'apellido_materno', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true,]);
                    }

                    ?>
                </div>


            </div>

            <div class="row align-items-start mt-5">
                <?=
                Html::activeLabel($model, 'id_tipodoc', [
                    'label' => 'Tipo Doc.',
                    'class' => 'col-sm-2 control-label mt-2'
                ])
                ?>
                <div class="col-sm-2">
                    <?php
                    if ($model->isNewRecord) {
                        $tip = (isset($_POST["Persona"]["id_tipodoc"])) ? $_POST["Persona"]["id_tipodoc"] : 1;
                        echo $form->field($model, 'id_tipodoc', [
                            'template' => "<div class=''>{input}{error}{hint}</div>"
                        ])->dropDownList(
                            common\models\Tipo_documento::getListaTiposDocumento(),
                            ['options' => [$tip => ['Selected' => true]], 'prompt' => ' -- Elija una opcion --']
                        );
                    } else {
                        echo $form->field($model, 'id_tipodoc', [
                            'template' => "<div class=''>{input}{error}{hint}</div>"
                        ])->dropDownList(
                            common\models\Tipo_documento::getListaTiposDocumento(),
                            ['prompt' => ' -- Elija una opcion --']
                        );
                    }
                    ?>


                </div>
                <?=
                Html::activeLabel($model, 'documento', [
                    'label' => 'N°',
                    'class' => 'col-sm-1 control-label text-end mt-2'
                ])
                ?>
                <div class="col-sm-3">
                    <?php
                    if ($model->isNewRecord) {
                        echo $form->field($model, 'documento', [
                            'template' => '{input}{error}{hint}'
                        ])
                            ->textInput([
                                'maxlength' => true,
                                'value' => (isset($_POST["Persona"]["documento"])) ? $_POST["Persona"]["documento"] : '',
                            ]);
                    } else {
                        echo $form->field($model, 'documento', [
                            'template' => '{input}{error}{hint}'
                        ])
                            ->textInput(['maxlength' => true,]);
                    }
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'documento_propio', [
                    'label' => 'Documento Propio: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?php
                    if ($model->isNewRecord && !isset($_POST["Persona"]["documento_propio"])) {
                        $model->documento_propio = 1;
                    }
                    echo $form->field($model, 'documento_propio', [
                        'template' => '{input}{error}{hint}'
                    ])->radioList(['1' => 'Si', '0' => 'No'], ['prompt' => '']);

                    ?>
                </div>
            </div>

            <div class="row align-items-start mt-5">

                <?=
                Html::activeLabel($model, 'sexo_biologico', [
                    'label' => 'Sexo Biologico: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-4">
                    <?php
                    if ($model->isNewRecord) {
                        $model->sexo_biologico = (isset($_POST["Persona"]["sexo_biologico"])) ? $_POST["Persona"]["sexo_biologico"] : 0;
                        echo $form->field($model, 'sexo_biologico', [
                            'template' => '{input}{error}{hint}'
                        ])->radioList([2 => 'Masculino', 1 => 'Femenino'], ['prompt' => '']);
                    } else {
                        echo $form->field($model, 'sexo_biologico', [
                            'template' => '{input}{error}{hint}'
                        ])->radioList([2 => 'Masculino', 1 => 'Femenino'], ['prompt' => '']);
                    }
                    ?>
                </div>

                <?=
                Html::activeLabel($model, 'genero', [
                    'label' => 'Genero Legal: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-4">
                    <?php
                    if ($model->isNewRecord) {
                        $model->genero = (isset($_POST["Persona"]["genero"])) ? $_POST["Persona"]["genero"] : 0;
                        echo $form->field($model, 'genero', [
                            'template' => '{input}{error}{hint}'
                        ])->radioList([1 => 'Femenino (F)', 2 => 'Masculino (M)', 3 => 'Otro', 4 => 'Indefinido (-)'], ['prompt' => '']);
                    } else {
                        echo $form->field($model, 'genero', [
                            'template' => '{input}{error}{hint}'
                        ])->radioList([1 => 'Femenino (F)', 2 => 'Masculino (M)', 3 => 'Otro', 4 => 'Indefinido (-)'], ['prompt' => '']);
                    }
                    ?>
                </div>


            </div>

            <div class="row align-items-start mt-5">
                <?=
                Html::activeLabel($model, 'fecha_nacimiento', [
                    'label' => 'Fecha Nacimiento: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'fecha_nacimiento', [
                        'template' => '{input}{error}{hint}',
                    ])->widget(\yii\jui\DatePicker::className(), ['dateFormat' => 'dd/MM/yyyy']);
                    ?>

                </div>
                <?php
                if (!$model->isNewRecord) {
                    echo Html::activeLabel($model, 'fecha_defuncion', [
                        'label' => 'Fecha Defunción: ',
                        'class' => 'col-sm-2 control-label'
                    ]);

                ?>
                    <div class="col-sm-2">
                        <?=
                        $form->field($model, 'fecha_defuncion', [
                            'template' => '{input}{error}{hint}'
                        ])->widget(\yii\jui\DatePicker::className(), ['dateFormat' => 'dd/MM/yyyy']);
                        ?>
                    </div>
                <?php } ?>
                <?=
                Html::activeLabel($model, 'id_estado_civil', [
                    'label' => 'Estado Civil: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?php
                    $id_ec = (isset($_POST["Persona"]["id_estado_civil"])) ? $_POST["Persona"]["id_estado_civil"] : 0;
                    echo $form->field($model, 'id_estado_civil', [
                        'template' => '{input}{error}{hint}'
                    ])->dropDownList(
                        common\models\EstadoCivil::getListaEstadosCiviles(),
                        ['options' => [$id_ec => ['Selected' => true]], 'prompt' => ' -- Elija una opcion --']
                    );
                    ?>
                </div>

            </div>
            <?php //$form->field($model, 'usuario_alta')->textInput(['maxlength' => true])    
            ?>

            <?php //$form->field($model, 'fecha_alta')->textInput()    
            ?>

            <?php //$form->field($model, 'usuario_mod')->textInput(['maxlength' => true])    
            ?>

            <?php //$form->field($model, 'fecha_mod')->textInput()    
            ?>

            <?php
            if ($model->isNewRecord) {
                //CREATE
            ?>
                <!-- --------------------DATOS TELEFONO ----------------------------->
                <div class="row">
                    <?=
                    Html::activeLabel($model_tipo_telefono, 'id_tipo_telefono', [
                        'label' => 'Tipo de Teléfono: ',
                        'class' => 'col-sm-2 control-label'
                    ])
                    ?>
                    <div class="col-sm-2">
                        <?php
                        $tip_te = (isset($_POST["Tipo_telefono"]["id_tipo_telefono"])) ? $_POST["Tipo_telefono"]["id_tipo_telefono"] : 0;
                        echo $form->field($model_tipo_telefono, 'id_tipo_telefono', [
                            'template' => '{input}{error}{hint}'
                        ])->dropDownList(
                            common\models\Tipo_telefono::getListaTiposTelefono(),
                            ['options' => [$tip_te  => ['Selected' => true]], 'prompt' => ' -- Elija una opcion --']
                        );
                        ?>
                    </div>
                    <?=
                    Html::activeLabel($model_persona_telefono, 'numero', [
                        'label' => 'Número de Telefono: ',
                        'class' => 'col-sm-2 control-label'
                    ])
                    ?>
                    <div class="col-sm-2">
                        <?=
                        $form->field($model_persona_telefono, 'numero', [
                            'template' => '{input}{error}{hint}'
                        ])->textInput(['maxlength' => true, 'value' => (isset($_POST["PersonaTelefono"]["numero"])) ? $_POST["PersonaTelefono"]["numero"] : '',])
                        ?>
                    </div>
                </div>
                <!-- --------------------DATOS EMAIL --------------------------*-->
                <div class="row">
                    <?=
                    Html::activeLabel($model_persona_mails, 'mail', [
                        'label' => 'Mail: ',
                        'class' => 'col-sm-2 control-label'
                    ])
                    ?>
                    <div class="col-sm-3">
                        <?php
                        if ($model->isNewRecord) {
                            echo $form->field($model_persona_mails, 'mail', [
                                'template' => '{input}{error}{hint}'
                            ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Persona_mails"]["mail"])) ? $_POST["Persona_mails"]["mail"] : '',]);
                        } else {
                            echo $form->field($model_persona_mails, 'mail', [
                                'template' => '{input}{error}{hint}'
                            ])->textInput(['maxlength' => true,]);
                        }
                        ?>
                    </div>

                </div>

        </div>
    </div>


    <div class="card">
        <div class="card-header">
            <h3>Datos Domicilio</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <?=
                Html::activeLabel($model_domicilio, 'calle', [
                    'label' => 'Calle: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-4">
                    <?=
                    $form->field($model_domicilio, 'calle', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["calle"])) ? $_POST["Domicilio"]["calle"] : '',])
                    ?>
                </div>
                <?=
                Html::activeLabel($model_domicilio, 'numero', [
                    'label' => 'Número: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-1">
                    <?=
                    $form->field($model_domicilio, 'numero', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["numero"])) ? $_POST["Domicilio"]["numero"] : '',])
                    ?>
                </div>
            </div>
            <div class="row">
                <?=
                Html::activeLabel($model, 'entre_calle_1', [
                    'label' => 'Entre calle 1: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'entre_calle_1', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["entre_calle_1"])) ? $_POST["Domicilio"]["entre_calle_1"] : '',])
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'entre_calle_2', [
                    'label' => 'Entre calle 2: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'entre_calle_2', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["entre_calle_2"])) ? $_POST["Domicilio"]["entre_calle_2"] : '',])
                    ?>
                </div>
            </div>
            <div class="row">
                <?=
                Html::activeLabel($model_domicilio, 'manzana', [
                    'label' => 'Mzna: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-1">
                    <?=
                    $form->field($model_domicilio, 'manzana', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["manzana"])) ? $_POST["Domicilio"]["manzana"] : '',])
                    ?>
                </div>
                <?=
                Html::activeLabel($model_domicilio, 'lote', [
                    'label' => 'Lote: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-1">
                    <?=
                    $form->field($model_domicilio, 'lote', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["lote"])) ? $_POST["Domicilio"]["lote"] : '',])
                    ?>
                </div>
                <?=
                Html::activeLabel($model_domicilio, 'sector', [
                    'label' => 'Sector: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-1">
                    <?=
                    $form->field($model_domicilio, 'sector', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["sector"])) ? $_POST["Domicilio"]["sector"] : '',])
                    ?>
                </div>

            </div>
            <div class="row">
                <?=
                Html::activeLabel($model_domicilio, 'grupo', [
                    'label' => 'Grupo: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-1">
                    <?=
                    $form->field($model_domicilio, 'grupo', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["grupo"])) ? $_POST["Domicilio"]["grupo"] : '',])
                    ?>
                </div>
                <?=
                Html::activeLabel($model_domicilio, 'torre', [
                    'label' => 'Torre: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-1">
                    <?=
                    $form->field($model_domicilio, 'torre', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["torre"])) ? $_POST["Domicilio"]["torre"] : '',])
                    ?>
                </div>
                <?=
                Html::activeLabel($model_domicilio, 'depto', [
                    'label' => 'Depto: ',
                    'class' => 'col-sm-1 control-label'
                ])
                ?>
                <div class="col-sm-1">
                    <?=
                    $form->field($model_domicilio, 'depto', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true, 'value' => (isset($_POST["Domicilio"]["depto"])) ? $_POST["Domicilio"]["depto"] : '',])
                    ?>
                </div>
            </div>
            <div class="row">
                <?php
                //****************************************************************************
                // Select dependiente de PROVINCIA

                echo Html::activeLabel($model_provincia, 'id_provincia', [
                    'label' => 'Provincia: ',
                    'class' => 'col-sm-2 control-label'
                ]);
                ?>
                <div class="col-sm-3">
                    <?php
                    $provincia = ArrayHelper::map(Provincia::find()->asArray()->all(), 'id_provincia', 'nombre');
                    echo $form->field($model_provincia, 'id_provincia', [
                        'template' => '{input}{error}{hint}'
                    ])->dropDownList($provincia, [
                        'id' => 'id_provincia',
                        'prompt' => 'Seleccione Provincia',
                    ]);
                    ?>
                </div>
                <?php
                //******************************************************************
                // Select dependiente de DEPARTAMENTOS
                echo Html::activeLabel($model_departamento, 'id_departamento', [
                    'label' => 'Departamento: ',
                    'class' => 'col-sm-2 control-label'
                ]);
                ?>
                <div class="col-sm-3">
                    <?php
                    echo $form->field($model_departamento, 'id_departamento', [
                        'template' => '{input}{error}{hint}'
                    ])->widget(DepDrop::classname(), [
                        //  'options' => ['id' => 'id_departamento', 'name' => 'nombre'],
                        'options' => ['id' => 'id_departamento'],
                        'pluginOptions' => [
                            'depends' => ['id_provincia'],
                            'placeholder' => 'Seleccione Departamento',
                            'url' => Url::to(['/personas/subcat'])
                        ]
                    ]);
                    ?>
                </div>
            </div>
            <div class="row">
                <?php
                echo Html::activeLabel($model_domicilio, 'id_localidad', [
                    'label' => 'Localidad: ',
                    'class' => 'col-sm-2 control-label'
                ]);
                ?>
                <div class="col-sm-3">
                    <?php
                    echo $form->field($model_localidad, 'id_localidad', [
                        'template' => '{input}{error}{hint}'
                    ])->widget(DepDrop::classname(), [
                        //                    'options' => ['id' => 'id_localidad', 'name' => 'nombre'],
                        'pluginOptions' => [
                            'depends' => ['id_departamento'],
                            'placeholder' => 'Seleccione Localidad',
                            'url' => Url::to(['/personas/loc'])
                        ]
                    ]);
                    ?>
                </div>
                <?=
                Html::activeLabel($model_domicilio, 'barrio', [
                    'label' => 'Barrio: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-3">
                    <?php
                    echo $form->field($model_domicilio, 'barrio', [
                        'template' => '<div class="input-group ">
                                  {input}
                                  <span class="input-group-btn">
                                    <button type="button" url="' . Url::to(['barrios/create']) . '" title="Nuevo Barrio" class="cargar_modal btn btn-info"><i class="glyphicon glyphicon-plus"></i></button> 
                                  </span>                                                      
                               </div>
                               {error}{hint}'
                    ])->widget(DepDrop::classname(), [
                        // 'options' => ['id' => 'id_barrio'],
                        'pluginOptions' => [
                            'depends' => ['localidad-id_localidad'],
                            'placeholder' => 'Seleccione Barrio',
                            'url' => Url::to(['/personas/barrio'])
                        ]
                    ]);
                    ?>
                </div>
            </div>
            <!--******************************************************************-->
            <!--  Select dependiente de LOCALIDADES-->
            <div class="row">

                <?=
                Html::activeLabel($model_domicilio, 'urbano_rural', [
                    'label' => 'Zona: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model_domicilio, 'urbano_rural', [
                        'template' => '{input}{error}{hint}'
                    ])->radioList(['U' => 'Urbano', 'R' => 'Rural',], ['prompt' => 'Elija una opción...'])
                    ?>

                </div>

            </div>
            <!--************************************ -->
            <!--***************MAPA********************* -->
            <!--************************************ -->
            <?php /*
        <div class="row">            
            <div class="col-xs-6 center-block">
                <div class="input-group">
                   <input type="text" class="form-control" id="address" placeholder="Ingrese Dirección">
                   <span class="input-group-btn">
                        <button class="btn btn-default" id="indicar" type="button">Buscar</button>
                   </span>
                </div>            
                <!--<input type="text" class="form-control" maxlength="100" id="address" placeholder="Ingrese Dirección" />
                <input type="button" id="indicar" value="Buscar" />-->
            
            <div id='map_canvas' style="width:100%; height:200px;"></div>
            </div>
            <?php
            // echo Html::activeLabel($model_domicilio, 'latitud', [
            //     'label' => 'Latitud: ',
            //     'class' => 'col-sm-2 control-label'
            // ])
            ?>
            <!--<div class="col-sm-2">-->
                <?=
                $form->field($model_domicilio, 'latitud', [
                    'template' => '{input}{error}{hint}'
                ])->hiddenInput(['maxlength' => true])
                ?>
            <!--</div>-->
            <?php
            // echo Html::activeLabel($model_domicilio, 'longitud', [
            //     'label' => 'Longitud: ',
            //     'class' => 'col-sm-2 control-label'
            // ])
            ?>
            <!--<div class="col-sm-2">-->
                <?=
                $form->field($model_domicilio, 'longitud', [
                    'template' => '{input}{error}{hint}'
                ])->hiddenInput(['maxlength' => true])
                ?>
            <!--</div>-->            
        </div>
        */ ?>
            <!--***************FIN MAPA********************* -->
        </div>
    </div>

<?php
            } else {
                //UPDATE
                //Solo muestro los telefonos y el domicilio
?>
    <br>
    <table class="table table-striped table-bordered detail-view">
        <tbody>
            <tr>
                <th style="width: 100px">Teléfono</th>
                <td>
                    <?php
                    foreach ($tels as $tells) {
                        $tipo_tel = \common\models\Tipo_telefono::findOne($tells['id_tipo_telefono']);
                        //.'/bioenlace/web/index.php?r=personas_telefono/update&id=' . $tells['id_persona_telefono'] . '&idp=' . $model->id_persona                            
                        echo $tells['numero'] . ' - ' . $tipo_tel->nombre . ' (' . $tells['comentario'] . ') ';
                        echo ' - <a data-pjax="0" aria-label="Actualizar" title="Actualizar" '
                            . 'href="' . Url::toRoute(['persona-telefono/update', 'id' => $tells['id_persona_telefono'], 'idp' => $model->id_persona]) . '">'
                            . '<span class="glyphicon glyphicon-pencil"></span></a> <br>';
                    }
                    ?>
                    <a href="<?php echo Url::toRoute(['persona-telefono/create', 'idp' => $model->id_persona]); ?>" class="btn btn-success">
                        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Telefonos
                    </a>
                </td>
            </tr>

        </tbody>
    </table>
    <!-- -------------------------EMAILS ---------------------------------->
    <table class="table table-striped table-bordered detail-view">
        <tbody>
            <tr>
                <th style="width: 100px">EMAILS</th>
                <td>
                    <?php
                    foreach ($mailsxpersona as $email) {

                        echo $email['mail'];
                        echo ' - <a data-pjax="0" aria-label="Actualizar" title="Actualizar" '
                            . 'href="' . Url::toRoute(['personas-mails/update', 'id' => $email['id_persona_mail'], 'idp' => $model->id_persona]) . '">'
                            . '<span class="glyphicon glyphicon-pencil"></span></a> <br>';
                    }
                    ?>
                    <a href="<?php echo Url::toRoute(['personas-mails/create', 'idp' => $model->id_persona]); ?>" class="btn btn-success">
                        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Mails
                    </a>
                </td>
            </tr>

        </tbody>
    </table>

    <table class="table table-striped table-bordered detail-view">
        <tbody>
            <tr>
                <th style="width: 100px">Domicilio</th>
                <td>
                    <?php
                    foreach ($domicilios as $domi) {
                        $localidad = \common\models\Localidad::findOne($domi['id_localidad']);
                        $zona = $domi['urbano_rural'] == 'U' ? 'Urbana' : 'Rural';
                        $activo = $domi['activo'] == 'SI' ? 'Activo' : 'Inactivo';
                        echo "Domicilio " . $activo . ' (Fecha alta:' . $domi['fecha_alta'] . ')';
                        echo ' - <a data-pjax="0" aria-label="Actualizar" title="Actualizar" '
                            . 'href="' . Url::toRoute(['domicilios/update', 'id' => $domi['id_domicilio'], 'idp' => $model->id_persona]) . '">'
                            . '<span class="glyphicon glyphicon-pencil"></span></a> <br>';
                        echo "Calle: " . $domi['calle'] . ' - Número:' . $domi['numero']
                            . ' - Mzn:' . $domi['manzana'] . " - Lote:" . $domi['lote'] . ' - Sector: ' . $domi['sector']
                            . ' - Gpo:' . $domi['grupo'] . ' - Torre' . $domi['torre'] . ' - Depto:' . $domi['depto']
                            . ' - B°:' . $domi['barrio'] . ' - Localidad:' . $localidad->nombre
                            . ' - Lat:' . $domi['latitud'] . ' - Log:' . $domi['longitud']
                            . ' - Zona:' . $zona . '<br><br>';
                    }
                    ?>

                    <?php
                    //************************ CODIGO PARA AGREGAR GOOGLE MAP***********************
                    //    if( ($domi['latitud']!='') && ($domi['longitud']!='' )){
                    //        $coord = new LatLng(['lat' => $domi['latitud'], 'lng' => $domi['longitud']]);
                    //        $map = new Map([
                    //            'center' => $coord,
                    //            'zoom' => 15,
                    //        ]);
                    //        // lets use the directions renderer
                    //        $home = new LatLng(['lat' => -27.787435, 'lng' => -64.259644]);
                    //
                    //        // Now the renderer
                    //        $directionsRenderer = new DirectionsRenderer([
                    //            'map' => $map->getName(),
                    ////            'polylineOptions' => $polylineOptions
                    //        ]);
                    //        // Lets add a marker now
                    //        $marker = new Marker([
                    //            'position' => $coord,
                    //            'title' => 'My Home Town',
                    //        ]);
                    //        // Provide a shared InfoWindow to the marker
                    //        $marker->attachInfoWindow(
                    //                new InfoWindow([
                    //            'content' => '<p>This is my super cool content</p>'
                    //                ])
                    //        );
                    //
                    //        // Add marker to the map
                    //        $map->addOverlay($marker);
                    //
                    //        // Lets show the BicyclingLayer :)
                    //        $bikeLayer = new BicyclingLayer(['map' => $map->getName()]);
                    //
                    //        // Append its resulting script
                    //        $map->appendScript($bikeLayer->getJs());
                    //
                    //        // Display the map -finally :)
                    //        echo $map->display();
                    //        }
                    ?>

                    <a href="<?php echo Url::toRoute(['domicilios/create', 'idp' => $model->id_persona]); ?>" class="btn btn-success">
                        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Domicilio
                    </a>
                </td>
            </tr>
        </tbody>
    </table>
<?php
            }
?>



<div class="form-group">
    <?= Html::submitButton($model->isNewRecord ? 'Agregar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success float-end' : 'btn btn-primary float-end']) ?>
</div>

<?php ActiveForm::end(); ?>

</div>

<div class="modal" id="modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span></button>
                <h4 id="modalHeader" class="modal-title"></h4>
            </div>
            <div class="modal-body">
                <p>Esperando</p>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

<?php
$this->registerJs(
    "
        $(document).ready(function(){            

            $(document).on('click', '.cargar_modal', function(){
                var p = $('#id_provincia').val();
                var d = $('#id_departamento').val();
                var l = $('#localidad-id_localidad').val();                
                if(($('#modal').data('bs.modal') || {}).isShown){
                    $('#modal').find('.modal-body').load($(this).attr('url') + '&p='+p+'&d='+d+'&l='+l);
                    document.getElementById('modalHeader').innerHTML = $(this).attr('title');
                } else {
                    //if modal isn't open; open it and load content
                    $('#modal').modal('show')
                            .find('.modal-body')
                            .load($(this).attr('url') + '&p='+p+'&d='+d+'&l='+l);
                     //dynamiclly set the header for the modal via title tag
                    document.getElementById('modalHeader').innerHTML = $(this).attr('title');
                }
            });

            $('#modal').on('submit', 'form', function(e) {
                var form = $(this);
                var formData = form.serialize();
                $.ajax({
                    url: form.attr('action'),
                    type: form.attr('method'),
                    data: formData,
                    success: function (data) {
                        if(typeof(data.success) != 'undefined'){
                            $('#domicilio-barrio').append(data.opts);
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-thumbs-up fa-1x\"></i> '+data.success+'</div>');
                            $('#modal').modal('hide');
                        }else{
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Se produjo un error al intentar guardar</div>');                            
                        }
                    },
                    error: function () {
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>'); 
                    }
                });
                window.setTimeout(function() { $('.alert').alert('close'); }, 3000); 
                
                e.preventDefault();
            });                
        });
"
);

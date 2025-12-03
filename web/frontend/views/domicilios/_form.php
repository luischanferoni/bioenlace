<script src="//code.jquery.com/jquery-1.10.2.js"></script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=set_to_true_or_false"></script>
<script type="text/javascript">
    //     $(document).ready(function () {
    //         load_map();
    //         var map;
    //         function load_map() {
    //             var myLatlng = new google.maps.LatLng(<?php //echo $model->latitud 
                                                            ?>, <?php //echo $model->longitud 
                                                                ?>);
    //             var myOptions = {
    //                 zoom: 16,
    //                 center: myLatlng,
    //                 mapTypeId: google.maps.MapTypeId.ROADMAP
    //             };
    //             map = new google.maps.Map($("#map_canvas").get(0), myOptions);

    //             var markerOptions = {position: myLatlng, draggable: true}
    //             var marker = new google.maps.Marker(markerOptions);
    //             marker.setMap(map);

    //             google.maps.event.addListener(marker, 'dragend', function (event) {
    //                 $("#domicilio-latitud").val(event.latLng.lat());
    //                 $("#domicilio-longitud").val(event.latLng.lng()); 
    //             });             
    //         }

    //         $('#indicar').click(function () {
    //             // Obtenemos la dirección y la asignamos a una variable
    //             var address = $('#address').val();
    //             // Creamos el Objeto Geocoder
    //             var geocoder = new google.maps.Geocoder();
    //             // Hacemos la petición indicando la dirección e invocamos la función
    //             // geocodeResult enviando todo el resultado obtenido
    //             geocoder.geocode({'address': address}, geocodeResult);
    //         });

    //         function geocodeResult(results, status) {
    //             // Verificamos el estatus
    //             if (status == 'OK') {
    //                 // Si hay resultados encontrados, centramos y repintamos el mapa
    //                 // esto para eliminar cualquier pin antes puesto
    //                 var mapOptions = {
    //                     center: results[0].geometry.location,
    //                     mapTypeId: google.maps.MapTypeId.ROADMAP
    //                 };
    //                 map = new google.maps.Map($("#map_canvas").get(0), mapOptions);
    //                 // fitBounds acercará el mapa con el zoom adecuado de acuerdo a lo buscado
    //                 map.fitBounds(results[0].geometry.viewport);
    //                 // Dibujamos un marcador con la ubicación del primer resultado obtenido
    //                 var markerOptions = {position: results[0].geometry.location, draggable: true}
    //                 var marker = new google.maps.Marker(markerOptions);
    //                 marker.setMap(map);
    //                 $("#domicilio-latitud").val(results[0].geometry.location.lat());
    //                 $("#domicilio-longitud").val(results[0].geometry.location.lng());

    //                 google.maps.event.addListener(marker, 'dragend', function (event) {
    //                     $("#domicilio-latitud").val(event.latLng.lat());
    //                     $("#domicilio-longitud").val(event.latLng.lng());
    //                 });                 
    //             } else {
    //                 // En caso de no haber resultados o que haya ocurrido un error
    //                 // lanzamos un mensaje con el error
    //                 alert("Geocoding no tuvo éxito debido a: " + status);
    //             }
    //         }

    // //$('#localidad-id_localidad').on('depdrop.change', function(event, id, value, count) {
    // //   alert($('#localidad-id_localidad').val())
    // //});
    // $( "#domicilio-id_localidad" ).change(function() {
    //   domicilio= $("#domicilio-calle").val()+" "+$("#domicilio-numero").val()+" "+$( "#domicilio-id_localidad option:selected" ).text();
    //   $("#address").val(domicilio);
    //   var address = $('#address').val();
    //             // Creamos el Objeto Geocoder
    //             var geocoder = new google.maps.Geocoder();
    //             // Hacemos la petición indicando la dirección e invocamos la función
    //             // geocodeResult enviando todo el resultado obtenido
    //             geocoder.geocode({'address': address}, geocodeResult);
    // });
    //     });
</script>
<?php
/**
 * Form Persona
 *  * @autor: Stella
 *  * @modificacion: 02/12/2015
 * 
 */

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use common\models\Provincia;
use common\models\Departamento;
use kartik\depdrop\DepDrop;
use yii\db\Query;


/* @var $this yii\web\View */
/* @var $model common\models\domicilio */
/* @var $form yii\widgets\ActiveForm */

$localidades = \common\models\Localidad::find()->indexBy('id_localidad')->asArray()->all();
$lista_localidades = \yii\helpers\ArrayHelper::map($localidades, 'id_localidad', 'nombre');
if (!$model->isNewRecord) {
    // cuando es un UPDATE consulto los datos de provincia y departamento para armar los select dependientes
    //     
    $query = new Query;
    $query->select('`provincias`.`id_provincia`,`provincias`.`nombre`,`departamentos`.`nombre` as departamento,`departamentos`.`id_departamento`,`localidades`.`nombre` as localidad')
        ->from('provincias')
        ->join(
            'INNER JOIN',
            'departamentos',
            '`departamentos`.`id_provincia`=`provincias`.`id_provincia`'
        )
        ->join('INNER JOIN', 'localidades', '`localidades`.`id_departamento`= `departamentos`.`id_departamento`')
        ->where(['`localidades`.`id_localidad`' => $model->id_localidad])
        ->indexBy('id_provincia');
    $command = $query->createCommand();
    $data = $command->queryAll();
?>


    <script>
        $(document).ready(function() {
            CargarSubcat();
            CargarLoc();
            // CargarBarrios();

            function CargarSubcat() {
                $.ajax({
                    url: '<?php echo Url::to(['personas/subcat']) ?>',
                    type: 'post',
                    data: {
                        id_provincia: <?php echo $data[0]['id_provincia']; ?>,
                        id_departamento: <?php echo $data[0]['id_departamento'] ?>
                    },
                    success: function(data) {
                        $("select#id_departamento").html(data);

                    }

                });
            }

            function CargarLoc() {
                $.ajax({
                    url: '<?php echo Url::to(['personas/loc']) ?>',
                    type: 'post',
                    data: {
                        id_localidad: <?php echo $model->id_localidad ?>,
                        id_departamento: <?php echo $data[0]['id_departamento'] ?>
                    },
                    success: function(data) {
                        $("select#id_localidad").html(data);
                        $("select#id_localidad").change();

                    }

                });
            }

            function CargarBarrios() {
                $.ajax({
                    url: '<?php echo Url::to(['personas/barrio']) ?>',
                    type: 'post',
                    data: {
                        depdrop_parents: [<?php echo $model->id_localidad ?>],
                        depdrop_params: [<?php echo $model->barrio ?>]
                    },
                    success: function(data) {
                        $("select#id_barrio").data(data.output);

                    }

                });
            }
        });
    </script>
<?php
}
if (!$model->isNewRecord) {
    // cuando es un UPDATE se habilitan los select dependientes
    // ya que ya tienen los datos cargados
?>
    <script>
        $(document).ready(function() {
            $('select#id_departamento').on('depdrop.init', function(event) {
                $("select#id_departamento").prop("disabled", false)
            });
            $('select#id_localidad').on('depdrop.init', function(event) {
                $("select#id_localidad").prop("disabled", false)
            });
        });
    </script>
<?php }
?>
<style>
    .center-block {
        float: none !important
    }
</style>
<div class="domicilio-form">

    <?php $form = ActiveForm::begin(['options' => ['class' => 'form-horizontal'],]); ?>


    <div class="card">

        <div class="card-header bg-soft-info">
            <h2>Datos del nuevo domicilio para: <?= $model_persona->apellido . ', ' . $model_persona->nombre ?></h2>
        </div>

        <div class="card-body">
            <div class="row">

                <?=
                Html::activeLabel($model, 'calle', [
                    'label' => 'Calle: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-4">
                    <?=
                    $form->field($model, 'calle', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'numero', [
                    'label' => 'Número: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-4">
                    <?=
                    $form->field($model, 'numero', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
            </div>
            <div class="row">
                <?=
                Html::activeLabel($model, 'manzana', [
                    'label' => 'Manzana: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'manzana', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'lote', [
                    'label' => 'Lote: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'lote', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'sector', [
                    'label' => 'Sector: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'sector', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
            </div>
            <div class="row">
                <?=
                Html::activeLabel($model, 'grupo', [
                    'label' => 'Grupo: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'grupo', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'torre', [
                    'label' => 'Torre: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'torre', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'depto', [
                    'label' => 'Depto: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'depto', [
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>
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
                    ])->textInput(['maxlength' => true])
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
                    ])->textInput(['maxlength' => true])
                    ?>
                </div>

            </div>
            <div class="row">
                <?=
                Html::activeLabel($model, 'id_provincia', [
                    'label' => 'Provincia: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-4">
                    <?php
                    $provincia = ArrayHelper::map(Provincia::find()->all(), 'id_provincia', 'nombre');
                    if (!$model->isNewRecord) {
                        echo $form->field($model_provincia, 'id_provincia', [
                            'template' => '{input}{error}{hint}'
                        ])->dropDownList($provincia, [
                            'id' => 'id_provincia',
                            'prompt' => 'Seleccione Provincia',
                            'options' => [$data[0]['id_provincia'] => ['selected ' => true]]
                        ]);
                    } else {
                        echo $form->field($model_provincia, 'id_provincia', [
                            'template' => '{input}{error}{hint}'
                        ])->dropDownList($provincia, [
                            'id' => 'id_provincia',
                            'prompt' => 'Seleccione Provincia'
                        ]);
                    }
                    ?>
                </div>
                <?=
                Html::activeLabel($model_departamento, 'id_departamento', [
                    'label' => 'Departamento: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-4">
                    <?php
                    $departamentos = ArrayHelper::map(Departamento::find()->all(), 'id_departamento', 'nombre');
                    if ($model->isNewRecord) {
                        echo $form->field($model_departamento, 'id_departamento', [
                            'template' => '{input}{error}{hint}'
                        ])->widget(DepDrop::classname(), [
                            //                   'options' => ['id' => 'id_departamento'],
                            'options' => ['id' => 'id_departamento'],
                            'pluginOptions' => [
                                'depends' => ['id_provincia'],
                                'placeholder' => 'Seleccione Departamento',
                                'url' => Url::to(['/personas/subcat']),
                                // 'params' => ['model_id1']
                            ]
                        ]);
                    } else {
                        echo $form->field($model_departamento, 'id_departamento', [
                            'template' => '{input}{error}{hint}'
                        ])->widget(DepDrop::classname(), [
                            //  'options' => ['id' => 'id_departamento', 'name' => 'nombre'],
                            'options' => ['id' => 'id_departamento', $data[0]['id_departamento'] => ['selected ' => true]],
                            'pluginOptions' => [
                                'depends' => ['id_provincia'],
                                'placeholder' => 'Seleccione Departamento',
                                'url' => Url::to(['/personas/subcat']),
                                // 'params' => ['model_id1']
                            ]
                        ]);
                    }
                    ?>
                </div>
            </div>
            <div class="row">
                <?php
                echo Html::activeLabel($model, 'id_localidad', [
                    'label' => 'Localidad: ',
                    'class' => 'col-sm-2 control-label'
                ]);
                ?> <div class="col-sm-4">
                    <?php
                    if ($model->isNewRecord) {
                        echo $form->field($model, 'id_localidad', [
                            'template' => '{input}{error}{hint}'
                        ])->widget(DepDrop::classname(), [
                            'options' => ['id' => 'id_localidad'],
                            'pluginOptions' => [
                                'depends' => ['id_departamento,id_provincia'],
                                'placeholder' => 'Seleccione Localidad',
                                'url' => Url::to(['/personas/loc'])
                            ]
                        ]);
                    } else {
                        echo $form->field($model, 'id_localidad', [
                            'template' => '{input}{error}{hint}'
                        ])->widget(DepDrop::classname(), [
                            'options' => ['id' => 'id_localidad', $model->id_localidad => ['selected ' => true]],
                            'pluginOptions' => [
                                'depends' => ['id_departamento,id_provincia'],
                                'placeholder' => 'Seleccione Localidad',
                                'url' => Url::to(['/personas/loc'])
                            ]
                        ]);
                    }

                    //****************************************************************************
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'barrio', [
                    'label' => 'Barrio: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-3">
                    <?php
                    $b = [];
                    $init = false;
                    if (!$model->isNewRecord) {
                        $b_bd = $model->modelBarrio;
                        $b = isset($b_bd->id_barrio) ? [$b_bd->id_barrio => $b_bd->nombre] : [];
                        $init = true;
                    }
                    echo $form->field($model, 'barrio', [
                        'template' => '<div class="input-group ">
                            {input}                                         
                            </div>
                            {error}{hint}'
                    ])->widget(DepDrop::classname(), [
                        'options' => ['id' => 'id_barrio'],
                        'data' => $b,
                        'pluginOptions' => [
                            'initialize' => $init,
                            'depends' => ['id_localidad'],
                            'placeholder' => 'Seleccione Barrio',
                            'url' => Url::to(['/personas/barrio'])
                        ]
                    ]);
                    ?>
                </div>
            </div>
            <div class="row">

                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'latitud', [
                        'template' => '{input}{error}{hint}'
                    ])->hiddenInput(['maxlength' => true])
                    ?>
                </div>

                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'longitud', [
                        'template' => '{input}{error}{hint}'
                    ])->hiddenInput(['maxlength' => true])
                    ?>
                </div>
                <?=
                Html::activeLabel($model, 'urbano_rural', [
                    'label' => 'Zona: ',
                    'class' => 'col-sm-2 control-label'
                ])
                ?>
                <div class="col-sm-2">
                    <?=
                    $form->field($model, 'urbano_rural', [
                        'template' => '{input}{error}{hint}'
                    ])->radioList(['U' => 'Urbano', 'R' => 'Rural',], ['prompt' => ''])
                    ?>
                </div>
            </div>

            <!--************************************ -->
            <!--***************MAPA********************* -->
            <!--************************************ -->
            <?php /* //Sacamos el mapa por el momento
        <div class="row">
<!--             <div>
                <input type="text" maxlength="100" id="address" placeholder="Ingrese Dirección" />
                <input type="button" id="indicar" value="Buscar" />
            </div><br/>
            <div id='map_canvas' style="width:600px; height:400px;"></div> -->
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
        </div>
        */ ?>
            <!--***************FIN MAPA********************* -->


            <?php
            if ($model->isNewRecord) {
            } else {

                $model_persona_domicilio = common\models\Persona_domicilio::findOne($model->id_domicilio);
                echo ' <div class="row">';
                echo Html::activeLabel($model_persona_domicilio, 'activo', [
                    'label' => 'Activo: ',
                    'class' => 'col-sm-2 control-label'
                ]);

                echo $form->field($model_persona_domicilio, 'activo', [
                    'template' => '{input}{error}{hint}'
                ])->radioList(['SI' => 'Activo', 'NO' => 'Inactivo',], [], ['prompt' => '']);
                echo '</div>';
            }
            ?>

        </div>

        <div class="form-group">
            <?= Html::submitButton($model->isNewRecord ? 'Agregar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success float-end me-5' : 'btn btn-primary float-end me-5']) ?>
        </div>

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
                var l = $('#id_localidad').val();                
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
                            $('#id_barrio').append(data.opts);
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

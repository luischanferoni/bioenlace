<?php

use kartik\select2\Select2;

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\bootstrap5\Modal;
use yii\bootstrap5\ActiveForm;

use common\models\OdontoNomenclador;
use common\models\ConsultaOdontologiaEstados;

$this->registerJs(
    'var codigoEstado = '.json_encode(
                        [
                            'estadosDiagnostico' => ConsultaOdontologiaEstados::estadosPiezasDiagnosticos, 
                            'estadosEstados' => ConsultaOdontologiaEstados::estadosPiezasPracticas
                        ]
                        ).';'
                );

$this->registerJs(
    'var idConsulta = '.($idConsulta ? $idConsulta : 0).';
    var practicas = '.json_encode($modelosOdontoConsultaPracticas).';
    var estadosAgregados = '.json_encode($modelosOdontoConsultaEstados).';
    var diagnosticos = '.json_encode($modelosOdontoConsultaDiagnosticos).';
    var contraPartidas = '.json_encode(OdontoNomenclador::CONTRAPARTIDA_TEMPORAL_DEFINITIVA).';' 
    );

    //var_dump($modelOdontoConsultaPracticas);die;
$script = <<<JS
    
    function quitarSimbolo(idSVG, idPath)
    {
        let svg = document.querySelector(idSVG);
        let simboloAgregado = svg.getElementById(idPath);
        if (simboloAgregado) simboloAgregado.remove();
    }

    function agregarSimbolo(idSVG, idPath, path)
    {        
        quitarSimbolo(idSVG, idPath);
        let newpath = document.createElementNS("http://www.w3.org/2000/svg","path");
        newpath.setAttributeNS(null, "d", path.d);
        newpath.setAttributeNS(null, "fill", path.fill);
        newpath.setAttributeNS(null, "stroke", path.stroke);
        newpath.setAttributeNS(null, "transform", path.transform);
        newpath.setAttributeNS(null, "style", path.style);
        newpath.setAttributeNS(null, "stroke-width", "0px");
        newpath.setAttributeNS(null, "id", idPath);
        document.querySelector(idSVG).appendChild(newpath);
    }

    function esNueva(el)
    {
        if (idConsulta === 0 && typeof(el.id_consulta) === "undefined") {
            return true;
        }

        if (idConsulta !== 0 && typeof(el.id_consulta) == "undefined") {
            return true;
        }

        if (idConsulta === el.id_consulta) {
            return true;
        }

        return false;
    }

    function pintarPiezasChicas()
    {
        // limpio el indicador de cambios en la pieza
        $(".numero_pieza").removeClass("text-success");
        $(".numero_pieza").removeClass("fs-3");

        // limpio primero todas las piezas        
        var svgs = $(".svg_pieza_chica");
        for (let index = 0; index < svgs.length; index++) {
            $(svgs[index]).children("path").attr("fill", fillBlanco);
            $(svgs[index]).children("ellipse").attr("fill", fillBlanco);
        }

        for (let index = 0; index < svgs.length; index++) {
            $(svgs[index]).children("#simboloAgregadoPiezaChica").remove();
        }

        let indiceC = 0;
        let indiceP = 0;
        let indiceO = 0;
        let indicec = 0;
        let indicee = 0;
        let indiceo = 0;

        // estados
        let tipoEstadoSeleccionado;
        for (let index = 0; index < estadosAgregados.length; index++) {
            tipoEstadoSeleccionado = (typeof codigoEstado["estadosDiagnostico"][estadosAgregados[index].codigo] == "undefined") ? "estadosEstados" : "estadosDiagnostico";

            if (estadosAgregados[index].pieza >= 51) {
                // indice ceo para piezas temporales
                if (estadosAgregados[index].codigo === "C" || estadosAgregados[index].codigo == 80967001 || estadosAgregados[index].codigo == "IE") {
                    indicec++;
                }
                if (estadosAgregados[index].codigo === "P") {
                    indicee++;
                }
                if (estadosAgregados[index].codigo === "O") {
                    indiceo++;
                }
                if (estadosAgregados[index].codigo === "RE") {
                    indiceo++;
                }

            } else {

                if (estadosAgregados[index].codigo === "C" || estadosAgregados[index].codigo == 80967001 || estadosAgregados[index].codigo == "IE") {
                    indiceC++;
                }
                if (estadosAgregados[index].codigo === "P") {
                    indiceP++;
                }
                if (estadosAgregados[index].codigo === "O") {
                    indiceO++;
                }
                if (estadosAgregados[index].codigo === "RE") {
                    indiceO++;
                }
            }
            
            if (typeof codigoEstado[tipoEstadoSeleccionado][estadosAgregados[index].codigo] != "undefined") {
                if (estadosAgregados[index].codigo == "RE" || estadosAgregados[index].codigo  == 80967001) {
                    if (!Array.isArray(estadosAgregados[index].caras)) {
                        if (estadosAgregados[index].caras == "") {
                            estadosAgregados[index].caras = [];
                        } else {                
                            estadosAgregados[index].caras = estadosAgregados[index].caras.split("-");
                        }
                    }                    
                    for (let indexCaras = 0; indexCaras < estadosAgregados[index].caras.length; indexCaras++) {
                        let rgb = estadosAgregados[index].codigo == "RE" ? fillRojo : fillAzul;
                        $(".svg_pieza_chica[data-id_pieza=\'"+estadosAgregados[index].pieza+"\'] > [data-parte=\'"+estadosAgregados[index].caras[indexCaras].toLowerCase()+"\']").attr("fill", rgb);
                    }
                } else {
                    agregarSimbolo("svg[data-id_pieza=\'" + estadosAgregados[index].pieza +"\']", 
                            "simboloAgregadoPiezaChica",  
                            codigoEstado[tipoEstadoSeleccionado][estadosAgregados[index].codigo].pathPiezaChica);
                }
            }

            if (esNueva(estadosAgregados[index])) {
                $("span[data-span_pieza="+estadosAgregados[index].pieza+"]").removeClass("text-success").addClass("text-success");
                $("span[data-span_pieza="+estadosAgregados[index].pieza+"]").removeClass("fs-3").addClass("fs-3");
            }
        }
        
        $("#indiceC").html(indiceC);
        $("#indiceP").html(indiceP);
        $("#indiceO").html(indiceO);
        $("#indiceCPO").html(parseInt(indiceC) + parseInt(indiceP) + parseInt(indiceO));

        $("#indiceCC").html(indicec);
        $("#indiceEE").html(indicee);
        $("#indiceOO").html(indiceo);
        $("#indiceceo").html(parseInt(indicec) + parseInt(indicee) + parseInt(indiceo));
                
        // diagnosticos
        for (let index = 0; index < diagnosticos.length; index++) {
            if (!Array.isArray(diagnosticos[index].caras)) {
                if (diagnosticos[index].caras == "") {
                    diagnosticos[index].caras = [];
                } else {
                    diagnosticos[index].caras = diagnosticos[index].caras.split("-");
                }
            }
            for (let indexCaras = 0; indexCaras < diagnosticos[index].caras.length; indexCaras++) {
                $(".svg_pieza_chica[data-id_pieza=\'"+diagnosticos[index].pieza+"\'] > [data-parte=\'"+diagnosticos[index].caras[indexCaras].toLowerCase()+"\']").attr("fill", fillAzul);
            }
            if (esNueva(diagnosticos[index])) {
                $("span[data-span_pieza="+diagnosticos[index].pieza+"]").removeClass("text-success").addClass("text-success");
                $("span[data-span_pieza="+diagnosticos[index].pieza+"]").removeClass("fs-3").addClass("fs-3");
            }            
        }

        // practicas
        for (let index = 0; index < practicas.length; index++) {
            
            /*if (practicas[index].tiempo == "PRESENTE" && practicas[index].codigo == "173291009") {
                agregarSimbolo("svg[data-id_pieza=\'" + practicas[index].pieza +"\']", 
                        "simboloAgregadoPiezaChica",  
                        codigoEstado["estadosEstados"]["P"].pathPiezaChica);
                continue;
            }*/

            rgb = practicas[index].tiempo == "FUTURA" ? fillAzul : fillRojo;

            if (!Array.isArray(practicas[index].caras)) {
                if (practicas[index].caras == "") {
                    practicas[index].caras = [];
                } else {                
                    practicas[index].caras = practicas[index].caras.split("-");                
                }
            }

            for (let indexCaras = 0; indexCaras < practicas[index].caras.length; indexCaras++) {
                $(".svg_pieza_chica[data-id_pieza=\'"+practicas[index].pieza+"\'] > [data-parte=\'"+practicas[index].caras[indexCaras].toLowerCase()+"\']").attr("fill", rgb);
            }
            if (esNueva(practicas[index])) {
                $("span[data-span_pieza="+practicas[index].pieza+"]").removeClass("text-success").addClass("text-success");
                $("span[data-span_pieza="+practicas[index].pieza+"]").removeClass("fs-3").addClass("fs-3");
            }            
        }
    }

    pintarPiezasChicas();
JS;

$this->registerJs($script);

$this->registerJsFile(
    '@web/js/odontograma.js',
    ['depends' => [\yii\web\JqueryAsset::class]]
);

?>

<style>
    .svg_pieza_individual{
        display: flex;
        align-items: center;
        height: 50%; 
    }
    .svg_pieza:hover path {stroke: coral;}
    .svg_pieza:hover ellipse {stroke: coral;}
    .svg_pieza_chica:hover path {stroke: coral;}
    .svg_pieza_chica:hover ellipse {stroke: coral;}
    .numero_pieza::first-letter {  font-size: 1.5rem;font-weight: bolder;margin-right:1px}
    #modal-pieza_completa .modal-body{min-height: 200px;}
    .hidden{display:none;}    
    </style>
<?php
/* 
    --------------------------------
    RESUMEN DE DATOS DE RELEVAMIENTO
    --------------------------------

    CALCULAR CPOD = C+P+O (caries + perdidos + obturados)
    CODIGO DE COLOR 
    AZUL: a realizar ( caries )
    ROJO: realizado ( perdido / obturado )
    
    CARAS DE LOS DIENTES
    CO - Oclusal: arriba
    CM - Mesial: superficie de un diente más próxima a la línea media de la cara
    CD - Distal: opuesta a la mesial
    CV - Vestibular: cara externa
    CP - Palatina: cara interna
*/
?>


<div class="row">
    <div class="col-6 text-start fs-4">
        Índice CPO: &nbsp;
        <label for="indiceC">C:</label>
        <span id="indiceC" class="fw-bolder"></span>
        <label for="indiceP">P:</label>
        <span id="indiceP" class="fw-bolder"></span>
        <label for="indiceO">O:</label>
        <span id="indiceO" class="fw-bolder border-end border-5 pe-2"></span>
        <label for="indiceCPO">CPO:</label>
        <span id="indiceCPO" class="fw-bolder"></span>
    </div>
    <div class="col-6 text-end fs-4">
        Índice ceo: &nbsp;
        <label for="indiceCC">c:</label>
        <span id="indiceCC" class="fw-bolder"></span>
        <label for="indiceEE">e:</label>
        <span id="indiceEE" class="fw-bolder"></span>
        <label for="indiceOO">o:</label>
        <span id="indiceOO" class="fw-bolder border-end border-5 pe-2"></span>
        <label for="indiceceo">ceo:</label>
        <span id="indiceceo" class="fw-bolder"></span>
    </div>
</div>    

<!-- GRÁFICO ODONTOGRAMA -->
<div class="card">
    <div class="card-body bg-soft-dark" id="body_odontograma">
        <div class="hstack gap-1 p-2">
            <div class="vstack mb-2 mr-2">
                <div class="hstack">
                    <?= $this->render('_odontologia_pieza', 
                        ['piezas' => OdontoNomenclador::CUADRANTE_DERECHA_SUPERIOR_DEFINITIVO]); ?>
                </div>
                <div class="hstack ps-5 pe-5 ms-5 me-5 hstack_temporal hidden">
                    <?= $this->render('_odontologia_pieza', 
                        ['piezas' => OdontoNomenclador::CUADRANTE_DERECHA_SUPERIOR_TEMPORAL]); ?>
                </div>
                <hr style="border: 1px solid">
                <div class="hstack ps-5 pe-5 ms-5 me-5 hstack_temporal hidden">
                    <?= $this->render('_odontologia_pieza', 
                        ['piezas' => OdontoNomenclador::CUADRANTE_DERECHA_INFERIOR_TEMPORAL]); ?>
                </div>
                <div class="hstack">
                    <?= $this->render('_odontologia_pieza', 
                        ['piezas' => OdontoNomenclador::CUADRANTE_DERECHA_INFERIOR_DEFINITIVO]); ?>
                </div>
            </div>
            <div class="vr"></div>
            <button type="button" id="boton_mostrar_temporal" class="btn btn-sm btn-success" 
                    style="position:absolute;margin-left: 39%;margin-top:2px;z-index: 99;">MOSTRAR DETNICIÓN TEMPORAL</button>
            <div class="vstack mb-2 ms-2">
                <div class="hstack">
                    <?= $this->render('_odontologia_pieza', 
                            ['piezas' => OdontoNomenclador::CUADRANTE_IZQUIERDA_SUPERIOR_DEFINITIVO]); ?>
                </div>
                <div class="hstack ps-5 pe-5 ms-5 me-5 hstack_temporal hidden">
                    <?= $this->render('_odontologia_pieza', 
                        ['piezas' => OdontoNomenclador::CUADRANTE_IZQUIERDA_SUPERIOR_TEMPORAL]); ?>
                </div>
                <hr style="border: 1px solid">
                <div class="hstack ps-5 pe-5 ms-5 me-5 hstack_temporal hidden">
                    <?= $this->render('_odontologia_pieza', 
                        ['piezas' => OdontoNomenclador::CUADRANTE_IZQUIERDA_INFERIOR_TEMPORAL]); ?>
                </div>
                <div class="hstack">
                    <?= $this->render('_odontologia_pieza', 
                                ['piezas' => OdontoNomenclador::CUADRANTE_IZQUIERDA_INFERIOR_DEFINITIVO]); ?>
                </div>
            </div>
        </div>
        <!-- FIN GRÁFICO ODONTOGRAMA -->
    </div>

    <?php $form = ActiveForm::begin(); ?>

        <input type="hidden" name="practicas_boca" id="boca_completa_a_guardar"></input>
        <input type="hidden" name="diagnosticos" id="diagnosticos_a_guardar"></input>
        <input type="hidden" name="practicas" id="practicas_a_guardar"></input>
        <input type="hidden" name="estados" id="estados_a_guardar"></input>
        
        <hr class="border border-info border-1 opacity-50">

        <?php //var_dump($errores);die;?>
        <?php if (count($errores) > 0) { ?>
            <div style="font-size: 13px;padding: 10px;" class="alert alert-danger d-flex align-items-center" role="alert">        
                <?php foreach($errores as $error) { ?>
                    <div><?php echo $error ?></div>
                <?php } ?>
            </div>
        <?php } ?> 

        <?php if ($modelConsulta->urlAnterior) { ?>
            <?= Html::a('Anterior', $modelConsulta->urlAnterior, ['class' => 'btn btn-primary atender rounded-pill float-start']) ?>
        <?php } ?>
        <?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

    <?php ActiveForm::end(); ?>    
</div>

<?php
Modal::begin([
    'title' => '',
    'id' => 'modal-pieza_completa',
    'size' => 'modal-lg',
]);
?>
    <ul class="nav nav-tabs mb-3 nav-fill" id="tabs-odontogramas" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active tab_estados_piezas" id="tab-estados" data-bs-toggle="tab" 
                data-bs-target="#nav-estados" href="#" role="tab" aria-selected="true">
                Estados
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link tab_diagnosticos_piezas" id="tab-diagnosticos" data-bs-toggle="tab" 
                data-bs-target="#nav-diagnosticos" href="#" role="tab" aria-selected="false" tabindex="-1">
                Diagnósticos
            </a>
        </li>        
        <li class="nav-item" role="presentation">
            <a class="nav-link tab_estados_piezas" id="tab-practicas" data-bs-toggle="tab" 
                data-bs-target="#nav-practicas" href="#" role="tab" aria-selected="false" tabindex="-1">
                Prácticas
            </a>
        </li>            
    </ul>

    <div class="tab-content" id="nav-tabContent">
        <div class="tab-pane fade show active" id="nav-estados" role="tabpanel" aria-labelledby="nav-estados-tab" tabindex="0">
            <div class="row" style="min-height: 220px">
                <div class="col-12 text-center">
                    <div class="row">
                        <div class="col-auto pt-4" id="div_botones_caras"></div>
                        <div class="col" id="div_pieza_estados">
                            <?php //echo $this->render('_odontologia_pieza_completa', ['id' => 'pieza_completa_estados']); ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="btn-group mt-1 checkboxradio" id="radios_pieza_completa" role="group">
                            <?php foreach (ConsultaOdontologiaEstados::estadosPiezasPracticas as $key => $estadoPieza) { ?>
                                <input class="btn-check checkbox-estado" data-codigo-estado="<?=$key?>" type="radio" name="flexRadioDefault" id="estado_pieza-<?=$key?>">
                                <label for="estado_pieza-<?=$key?>" class="btn btn-outline-danger text-center">
                                    <?php if (isset($estadoPieza['pathReferencia']) && $estadoPieza['pathReferencia']['d'] != "") { ?>
                                        <svg class="pt-1" width="22px" height="22px">
                                            <path fill="<?=$estadoPieza['pathReferencia']['fill']?>"
                                                    d="<?=$estadoPieza['pathReferencia']['d']?>" stroke="<?=$estadoPieza['pathReferencia']['stroke']?>" 
                                                    transform="<?=$estadoPieza['pathReferencia']['transform']?>" style="<?=$estadoPieza['pathReferencia']['style']?>"
                                                    stroke-width="0px"></path>
                                        </svg>
                                    <?php } ?>
                                    <?=$estadoPieza['nombre']?>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="btn-group mt-1 checkboxradio" id="radios_pieza_completa" role="group">
                            <?php foreach (ConsultaOdontologiaEstados::estadosPiezasDiagnosticos as $key => $estadoPieza) { ?>
                                <input class="btn-check checkbox-estado" data-codigo-estado="<?=$key?>" type="radio" name="flexRadioDefault" id="estado_pieza-<?=$key?>">
                                <label for="estado_pieza-<?=$key?>" class="btn btn-outline-info text-center">
                                    <?php if (isset($estadoPieza['pathReferencia']) && $estadoPieza['pathReferencia']['d'] != "") { ?>
                                        <svg class="pt-1" width="22px" height="22px">
                                            <path fill="<?=$estadoPieza['pathReferencia']['fill']?>"
                                                    d="<?=$estadoPieza['pathReferencia']['d']?>" stroke="<?=$estadoPieza['pathReferencia']['stroke']?>" 
                                                    transform="<?=$estadoPieza['pathReferencia']['transform']?>" style="<?=$estadoPieza['pathReferencia']['style']?>"
                                                    stroke-width="0px"></path>
                                        </svg>
                                    <?php } ?>
                                    <?=$estadoPieza['nombre']?>
                                </label>
                            <?php } ?>
                        </div>
                    </div>                    
                </div>
                <div class="col-12 text-center mt-2">
                    <div class="row mt-2">
                        <div class="col-12"><p id="ayuda_estados" class="alert alert-left alert-info fade show mb-3"></p></div>
                        <div class="col-12">                    
                            <div class="msj_previo_guardar hidden"></div>
                            <button class="btn btn-success" id="btn-agregar-estado" disabled>Establecer estado</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="nav-diagnosticos" role="tabpanel" aria-labelledby="nav-diagnosticos-tab" tabindex="1">
            <div class="row" style="height: 220px">
                <div class="col-7 mt-5">                    
                    <?php 
                        echo Select2::widget([
                                            'name' => 'select_pieza_completa',
                                            'id' => 'select_diagnostico',
                                            'value' => '',
                                            'theme' => Select2::THEME_DEFAULT,
                                            'options' => ['placeholder' => 'Buscar diagnóstico...'],
                                            'pluginOptions' => [
                                                'minimumInputLength' => 4,
                                                'dropdownParent' => '#modal-pieza_completa',
                                                'width' => '100%',
                                                'ajax' => [
                                                    'url' => Url::to(['snowstorm/diagnosticos-odontologia']),
                                                    'dataType' => 'json',
                                                    'delay'=> 500,
                                                    'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                                    'cache' => true
                                                ],
                                            ],
                                        ]);
                    ?>
                </div>
                <div class="col-5 text-center">
                    <div class="col" id="div_pieza_diagnosticos"></div>
                    <?php //echo $this->render('_odontologia_pieza_completa', ['id' => 'pieza_completa_diagnosticos']); ?>
                    <div class="row mt-2">
                        <div class="col-12"><p id="ayuda_diagnosticos" class="alert alert-left alert-info fade show text-start p-1">Seleccione al menos una cara</p></div>
                        <div class="col-12">
                            <div class="msj_previo_guardar hidden"></div>
                            <button class="btn btn-success" id="btn-agregar-diagnostico" disabled>Agregar diagnóstico</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <hr style="height:1px;" class="bg-info"/>
                    <h5 class="text-center">Historial de diagnósticos</h5>
                    <div id="historial-diagnosticos"></div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="nav-practicas" role="tabpanel" aria-labelledby="nav-practicas-tab" tabindex="1">
            <div class="row">
                <div id="diagnosticos_practicas" class="ps-1 pe-1"></div>
                <?php /* ?>
                <div class="col-6 mt-5">
                    <h5>Añadir practica existente</h5>
                    <?php
                        echo Select2::widget([
                                            'name' => 'select_pieza_completa',
                                            'id' => 'select_practicas',
                                            'value' => '',
                                            'data' => $dataNomencladorPiezayCara,
                                            'theme' => Select2::THEME_DEFAULT,
                                            'options' => ['placeholder' => 'Ingresar mas prácticas...'],
                                            'pluginOptions' => [
                                                'dropdownParent' => '#modal-pieza_completa',
                                                'allowClear' => true,
                                                "width" => "100%"
                                            ],                                   
                                        ]);
                    ?>
                </div>
                <div class="col-6 text-center">
                    <?= $this->render('_odontologia_pieza_completa', ['id' => 'pieza_completa_practicas']); ?>
                    <div class="row mt-2">
                        <div class="col-12"><p id="ayuda_practicas" class="alert alert-left alert-danger fade show mb-3"></p></div>
                        <div class="col-12">
                            <div class="msj_previo_guardar hidden"></div>
                            <button class="btn btn-success" id="btn-agregar-practica" disabled>Agregar practica</button>
                        </div>                
                    </div>
                </div>
                <?php */ ?>
            </div>
            <div class="row mt-5">
                <div class="col-12">
                    <hr style="height:1px;"/>
                    <h5 class="text-center">Historial de practicas existentes/a realizar</h5>
                    <div id="historial-practicas"></div>
                </div>
            </div>
        </div>
    </div>

<?php 
Modal::end();

$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);
?>
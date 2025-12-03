<?php
use yii\web\View;
use yii\helpers\ArrayHelper;

use common\models\OdontoNomenclador;
use common\models\ConsultaOdontologiaEstados;

?>

<style>
    .numero_pieza::first-letter {  font-size: 1.5rem;font-weight: bolder;margin-right:1px}
</style>

<!-- GRÁFICO ODONTOGRAMA -->
<div class="card">
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
            <label for="indicec">c:</label>
            <span id="indicec" class="fw-bolder"></span>
            <label for="indicee">e:</label>
            <span id="indicee" class="fw-bolder"></span>
            <label for="indiceo">o:</label>
            <span id="indiceo" class="fw-bolder border-end border-5 pe-2"></span>
            <label for="indiceceo">ceo:</label>
            <span id="indiceceo" class="fw-bolder"></span>
        </div>
    </div>

    <div class="card-body bg-soft-dark">
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
</div>
<h5>Estados</h5>
<table class="table table-sm table-bordered border-secondary">
    <thead class="table-light">
        <tr>
            <th>Pieza #</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($estados) == 0) { ?>
            <tr><td colspan="4" class="text-center">Sin registros</td></tr>
        <?php }?>        
        <?php foreach($estados as $estado) { ?>
            <tr>
                <td><?= $estado->pieza?></td>
                <td>
                    <?php 
                    if (isset(ConsultaOdontologiaEstados::estadosPiezasDiagnosticos[$estado->codigo])) {
                        $svg = ConsultaOdontologiaEstados::estadosPiezasDiagnosticos[$estado->codigo];
                    } else {
                        $svg = ConsultaOdontologiaEstados::estadosPiezasPracticas[$estado->codigo];
                    }
                    ?>                
                    <svg class="pt-1" width="20px" height="20px">
                        <path fill="<?=$svg['pathReferencia']['fill']?>"
                                d="<?=$svg['pathReferencia']['d']?>" stroke="<?=$svg['pathReferencia']['stroke']?>" 
                                transform="<?=$svg['pathReferencia']['transform']?>" style="<?=$svg['pathReferencia']['style']?>"
                                stroke-width="0px"></path>
                    </svg>
                    <?=$svg['nombre']?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<h5>Diagnosticos por pieza</h5>
<table class="table table-sm table-bordered border-secondary">
    <thead>
        <tr>
            <th>Pieza #</th>
            <th>Caras</th>
            <th>Aplica a</th>
            <th>Codigo</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($diagnosticos) == 0) { ?>
            <tr><td colspan="4" class="text-center">Sin registros</td></tr>
        <?php }?>        
        <?php 
        foreach($diagnosticos as $diagnostico) { 
            $term = '-';
            if (!is_null($diagnostico->codigo)) {
                if (!is_null($diagnostico->snomedDiagnostico)) {
                    $term = $diagnostico->snomedDiagnostico->term;
                }
            }
            ?>
            <tr>
                <td><?= $diagnostico->pieza?></td>
                <td><?= $diagnostico->caras?></td>
                <td><?= $diagnostico->tipo?></td>
                <td><?= $diagnostico->codigo.' '.$term?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<h5>Practicas por pieza</h5>
<table class="table table-sm table-bordered border-secondary">
    <thead>
        <tr>
            <th>Pieza #</th>
            <th>Caras</th>
            <th>Aplica a</th>
            <th>Diagnostico</th>
            <th>Codigo</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($practicas) == 0) { ?>
            <tr><td colspan="5" class="text-center">Sin registros</td></tr>
        <?php }?>

        <?php foreach($practicas as $practica) {
    
            if (!is_null($practica->odontoNomenclador)) {
                $term = $practica->odontoNomenclador->detalle_nomenclador;
            } else {
                $term = $practica->snomedPractica->term;
            }
                        
            ?>
            <tr class="<?=$practica->tiempo === 'PRESENTE' ? "bg-soft-danger" : "bg-soft-primary"?>">
                <td><?= $practica->pieza?></td>
                <td><?= $practica->caras?></td>
                <td><?= $practica->tipo?></td>
                <td><?= $practica->diagnostico.' '.($practica->diagnostico ? $practica->snomedDiagnostico->term : '')?></td>
                <td><?= $practica->codigo.' '.$term?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<?php 
echo $this->render('_detalle_consulta_amb', [
    'model' => $modelConsulta,
    'model_diagnosticos_consulta' => $modelConsulta->diagnosticoConsultas,
    'model_medicamentos_consulta' => $modelConsulta->consultaMedicamentos,
    'model_consulta_practicas' => $modelConsulta->consultaPracticas,
    'model_consulta_derivaciones' => $modelConsulta->derivacionesSolicitadas,
]) ?>

<?php
$estados = ArrayHelper::toArray($estados, [
    'common\models\ConsultaOdontologiaEstados' => [
        'id_consultas_odontologia_estados',
        'pieza',                
        'caras',
        'codigo',
        'tipo'
    ],
]);

$diagnosticos = ArrayHelper::toArray($diagnosticos, [
    'common\models\ConsultaOdontologiaDiagnosticos' => [
        'id',
        'pieza',                
        'caras',
        'codigo',
        'tipo',
    ],
]);

$practicas = ArrayHelper::toArray($practicas, [
    'common\models\ConsultaOdontologiaPracticas' => [
        'id_consultas_odontologia_practicas',
        'pieza',                
        'caras',
        'diagnostico',
        'codigo',
        'tipo',                
        'tiempo'
    ],
]);

$this->registerJs(
    'var codigoEstado = '.json_encode(
                        [
                            'estadosDiagnostico' => ConsultaOdontologiaEstados::estadosPiezasDiagnosticos, 
                            'estadosEstados' => ConsultaOdontologiaEstados::estadosPiezasPracticas
                        ]
                        ).';'
                );

$this->registerJs(
    'var practicas = '.json_encode($practicas).';
    var estadosAgregados = '.json_encode($estados).';
    var diagnosticos = '.json_encode($diagnosticos).';'
    );

$script = <<<JS
    
    const fillAzul = "rgb(8, 177, 186)";
    const fillBlanco = "rgb(255, 255, 255)";
    const fillGrey = "rgb(172, 164, 188)";
    const fillRojo = "rgb(192, 50, 33)";

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

    function pintarPiezasChicas()
    {
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

        let tipoEstadoSeleccionado;
        for (let index = 0; index < estadosAgregados.length; index++) {
            tipoEstadoSeleccionado = (typeof codigoEstado["estadosDiagnostico"][estadosAgregados[index].codigo] == "undefined") ? "estadosEstados" : "estadosDiagnostico";

            if (estadosAgregados.pieza >= 51) {
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
        }
        
        $("#indiceC").html(indiceC);
        $("#indiceP").html(indiceP);
        $("#indiceO").html(indiceO);
        $("#indiceCPO").html(parseInt(indiceC) + parseInt(indiceP) + parseInt(indiceO));

        $("#indicec").html(indicec);
        $("#indicee").html(indicee);
        $("#indiceo").html(indiceo);

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
        }
    }

    pintarPiezasChicas();
JS;

$this->registerJs($script);
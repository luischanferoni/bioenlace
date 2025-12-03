<?php 

use kartik\select2\Select2;

use common\models\ConsultaOdontologiaEstados;

?>

<div class="collapse" id="collapse-estados">
    <div class="btn-group mt-1 checkboxradio" id="radios_pieza_completa" role="group">
        <?php foreach (ConsultaOdontologiaEstados::estadosPiezas as $key => $estadoPieza) { ?>
            <input class="btn-check check-estado_general" type="radio" name="flexRadioDefault" id="estado_pieza-<?=$key?>">
            <label for="estado_pieza-<?=$key?>" class="btn btn-outline-primary ps-2">                
                <?php if (isset($estadoPieza['pathReferencia'])) { ?>
                    <svg class="pt-1" width="20px" height="20px">
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

<div class="collapse" id="collapse-practicas-pieza-completa">
    <h5>Practicas</h5>
    <div class="col-12">
        <div class="btn-group mt-1 checkboxradio" id="radios_practicas_pieza_completa" role="group"></div>
    </div>

    <div class="col-12">
        <?php 
            echo Select2::widget([
                                'name' => 'select_pieza_completa',
                                'id' => 'select_pieza_completa',
                                'value' => '',
                                'data' => $dataNomencladorPorPiezaYCara,
                                'theme' => Select2::THEME_DEFAULT,
                                'options' => ['placeholder' => 'Ingresar mas prácticas...'],
                                'pluginOptions' => [
                                    'allowClear' => true,
                                    'width' => '70%'
                                ],                                   
                            ]);
        ?>
    </div>
</div>

<?php /* ?>
<div class="collapse" id="collapse-practicas-caras">

    <h5>Practicas a la caras de las piezas</h5>
    <div class="col-12">
        <div class="btn-group mt-1 checkboxradio" id="radios_practicas_caras" role="group"></div>
    </div>

    <div class="col-12">
        <?php 
            echo Select2::widget([
                                'name' => 'select_pieza_caras',
                                'id' => 'select_pieza_caras',
                                'value' => '',
                                'data' => $dataNomencladorCaras,
                                'theme' => Select2::THEME_DEFAULT,
                                'options' => ['placeholder' => 'Ingresar mas prácticas...'],
                                'pluginOptions' => [
                                    'allowClear' => true,
                                    'width' => '70%'
                                ],
                                'pluginEvents' => [
                                    "select2:select" => 'function(e) {
                                        var select_val = $(e.currentTarget).val();
                                        let checkbox = "<input id="+select_val+" class=\"btn-check check-estado_general\" type=\"radio\" name=\"flexRadioDefault\">";
                                            checkbox += "<label for="+select_val+" class=\"btn btn-outline-primary ps-2 mb-4\">"+$("#select_pieza_caras :selected").text()+"</label>";

                                        $("#radios_practicas_caras").html($("#radios_practicas_caras").html() + checkbox);                                            
                                    }',
                                ]                                           
                            ]);
        ?>
    </div>            
</div>
<?php */ ?>
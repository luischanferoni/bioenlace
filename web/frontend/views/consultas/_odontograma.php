<?php

use kartik\select2\Select2;

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;

$this->registerJsFile(
    '@web/js/odontograma.js',
    ['depends' => [\yii\web\JqueryAsset::class]]
);

?>

<!------ Formulario Dinámico ------->
        <div class="">
            <div class="card-header d-flex justify-content-between">
                    <h4 class="card-title">Odontograma</h4></div>
            <div class="card-body">
                <?php
 /*               DynamicFormWidget::begin([
                    'widgetContainer' => 'dynamicform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
                    'widgetBody' => '.container-items', // required: css class selector
                    'widgetItem' => '.item', // required: css class
                    'limit' => 4, // the maximum times, an element can be cloned (default 999)
                    'min' => 0, // 0 or 1 (default 1)
                    'insertButton' => '.add-item', // css class
                    'deleteButton' => '.remove-item', // css class
                    'model' => $model_odonto_consulta_persona[0],
                    'formId' => 'dynamic-form',
                    'formFields' => [
                       'id_odonto_consulta',
                        'ID_CONSULTA',
                        'ID_TURNO',
                        'ID_PERSONA',
                        'ID_NOMENCLADOR_ODONTO',
                        'id_odonto_pieza',
                        'estado_pieza',
                        'piezasArray',
                        'select_pieza_completa_todos',
                        'select_pieza_caras',
                        'tipo_consulta',
                        'boca_completa',
                    ],
                ]);*/
                ?>
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

$pieza_dental_base_svg = '
<svg _ngcontent-bsj-c568="" viewBox="0 25 40 48" fill="white" xmlns="http://www.w3.org/2000/svg">
        <g _ngcontent-bsj-c568="" clip-path="url(#clip0)">
            <path _ngcontent-bsj-c568="" id="left" fill="white" d="M10.4072 57.2055L0.409788 67.2002L0.409785 28.4207L10.4072 38.5153C10.4072 38.5153 10.4072 57.3054 10.4072 57.2055Z" stroke="#4B4B4B" stroke-miterlimit="10" stroke-linejoin="round">
            </path>
            <path _ngcontent-bsj-c568="" id="internal" fill="white" d="M29.2028 57.2055L39.2002 67.2002L0.410145 67.2002L10.5076 57.2055C10.5076 57.2055 29.3027 57.2055 29.2028 57.2055Z" stroke="#4B4B4B" stroke-miterlimit="10" stroke-linejoin="round">
            </path>
            <path _ngcontent-bsj-c568="" id="right" fill="white" d="M29.2028 38.5153L39.2002 28.5206L39.2002 67.2002L29.2028 57.1055C29.2028 57.2055 29.2028 38.3154 29.2028 38.5153Z" stroke="#4B4B4B" stroke-miterlimit="10" stroke-linejoin="round">
            </path>
            <path _ngcontent-bsj-c568="" id="external" fill="white" d="M10.4076 38.4155L0.41014 28.4208L39.2002 28.4208L29.1028 38.4155C29.1028 38.4155 10.3076 38.4155 10.4076 38.4155Z" stroke="#4B4B4B" stroke-miterlimit="10" stroke-linejoin="round">
            </path>
            <path _ngcontent-bsj-c568="" id="central" fill="white" d="M10.4079 57.3057L29.2031 57.3057L29.2031 38.5156L10.4079 38.5156L10.4079 57.3057Z" stroke="#4B4B4B" stroke-miterlimit="10" stroke-linejoin="round">
            </path>
        </g>
        <defs _ngcontent-bsj-c568="">
            <clipPath _ngcontent-bsj-c568="" id="clip0">
                <rect _ngcontent-bsj-c568="" fill="white" transform="translate(40 68) rotate(180)">
                </rect>
            </clipPath>
        </defs>
</svg>
';
echo '<input type="hidden" id="piezasArray" name="piezasArray" value="">';


?>
<style>
.referencias{
    overflow: auto;
}
.referencias_titulo{
    margin: 20px;
}
.espacio {
    padding: 20px;
    display: flex;
}
.referencias_svg{
    width: 30%;
    height: 30%;
}
.svg_pieza_individual{
    display: flex;
    align-items: center;
    height: 50%; 
}
.odontoSVG{
    pointer-events: none;
}
</style>
            <div class="card espacio" style="flex-direction: row; justify-content: space-around;">
                    <div class="card mb-3 border-secondary " style="width: 30%">
                            <h5 class="card-title text-secondary">Consulta</h5>
                            <?php   echo Select2::widget([
                                            'name' => 'tipo_consulta',
                                            'id' => 'tipo_consulta',
                                            'value' => '',
                                            'data' => $dataNomencladorConsulta,
                                            'options' => ['id' => 'tipo_consulta','multiple' => false, 'placeholder' => 'Ingresar tipo consulta...']
                                        ]);                
                            ?>
                    </div>
                    <div class="card mb-3 border-secondary " style="width: 30%">
                            <h5 class="card-title text-secondary">Tratamientos y Estudios complementarios</h5>
                            <?php   echo Select2::widget([
                                            'name' => 'boca_completa',
                                            'id' => 'boca_completa',
                                            'value' => '',
                                            'data' => $dataNomencladorTto,
                                            'options' => ['id' => 'boca_completa','multiple' => false, 'placeholder' => 'Ingresar tratamientos...']
                                        ]);                
                            ?>
                    </div>
                    <div class="card mb-3 border-secondary " style="width: 30%; display: block;">
                            <h5 class="card-title text-secondary">Índice CPO</h5>
                            <label for="indiceC">C:</label>
                            <input style="width:20px; border: none;" type="text" id="indiceC" readonly value="<?php echo $indice['C']; ?>" />
                            <label for="indiceP">P:</label>
                            <input style="width:20px; border: none;"  type="text" id="indiceP" readonly value="<?php echo $indice['P']; ?>" />
                            <label for="indiceO">O:</label>
                            <input style="width:20px; border: none;"  type="text" id="indiceO" readonly value="<?php echo $indice['O']; ?>" />
                            <label for="indiceCPO">CPO:</label>
                            <input style="width:20px; border: none;"  type="text" id="indiceCPO" readonly value="<?php echo $indice['CPO']; ?>" />
                    </div>

            </div>
            <div id="odonto_gral" style=" justify-content: center; align-items:center " class="panel panel-default">    <!-- GRÁFICO ODONTOGRAMA -->

                    <div style="display: block; border-radius: 15px; background-color: white; width:70%; height: 70%; margin: auto; margin-bottom: 10px;" class="panel panel-default">    <!-- div conteiner general de gráfico de ODONTOGRAMA -->
                        <?php echo $grafico; ?>
                    </div> <!-- FIN GRÁFICO ODONTOGRAMA -->

        

                    <div id="pieza_detalle" class="card"> <!-- PIEZA DENTAL - DETALLE -->
                            <div style="margin-left: 2%">
                                
                            </div>
                            <div class="d-grid gap-card grid-cols-3">
                                    <div class="card espacio" style="display:flex;"><!-- card1  --->
                                            <div class="card-header d-flex align-items-center justify-content-between">
                                                        <div class="header-title">
                                                        <h5 class="card-title"><strong>Pieza dental<span class="pieza-titulo"></span></strong></h5>
                                                        <input id="pieza-titulo-numero" type="hidden" value="" >
                                                        </div>
                                                </div> 
                                                <section class="cards" style="display: flex; justify-content: space-between;"> 
                                                        <div id="svg_pieza_individual" class="card svg_pieza_individual">
                                                        </div>
                                                        <div id="referencias" class="card referencias_div_titulo espacio">
                                                        </div>
                                                </section>
                                    </div>
                                    <div class="card espacio">                      <!-- card2 --->
                                                <div class="card-header d-flex align-items-center justify-content-between">
                                                        <div class="header-title">
                                                            <h5 class="card-title">Prácticas - detalle</h5>
                                                        </div>
                                                </div>
                                                <div class="card-body" >
                                                            <div class="card mb-3 border-secondary ">
                                                                    <h6 class="card-title text-secondary">Pieza completa</h6>
                                                                    <?php   echo Select2::widget([
                                                                                    'name' => 'select_pieza_completa',
                                                                                    'id' => 'select_pieza_completa',
                                                                                    'value' => '',
                                                                                    'data' => $dataNomencladorPorPieza,
                                                                                    'options' => ['multiple' => true, 'placeholder' => 'Ingresar prácticas...']
                                                                                ]);                
                                                                    ?>
                                                            </div>
                                                            <div class="card mb-3 border-secondary ">
                                                                    <h6 class="card-title text-secondary">Caras</h6>
                                                                    <?php   echo Select2::widget([
                                                                                    'name' => 'select_pieza_caras',
                                                                                    'id' => 'select_pieza_caras',
                                                                                    'value' => '',
                                                                                    'data' => $dataNomencladorCaras,
                                                                                    'options' => ['multiple' => true, 'placeholder' => 'Ingresar prácticas...']
                                                                                ]);                
                                                                    ?>
                                                            </div>
                                            
                                                </div>
                                    </div>  

                                    <div class="card espacio">                      <!-- card3 --->
                                                <div class="card-header d-flex align-items-center justify-content-between">
                                                        <div id="historial" class="header-title">
                                                            <h5 class="card-title">Historial</h5>
                                                        </div>
                                                </div>
                                                <div class="card-body" >
                                                        <?php echo $historial;  ?>

                                                </div>

                                    </div>
                            </div>
                    </div> <!-- FIN PIEZA DENTAL - DETALLE -->
                    <button id="finalizar" type="button" class="btn btn-success action-button float-end" style="margin: 20px">Finalizar Actualización de Odontograma</button>
        <!--               <table class="table table-striped margin-b-none">
                            <thead>
                                <tr>
                                    <th class="required">Descripción</th>
                                    <th style="width: 188px;">Tipo de Diagnóstico</th>
                                    <th style="width: 90px; text-align: center"></th>
                                </tr>
                            </thead>
                            <tbody  class="container-items">  -->
                            <?php //foreach ($model_odonto_consulta_persona as $i => $model_d_c): ?>
        <!--                       <tr class="item">
                                    <td> -->

                                    <?php 
                                    /*    $form->field($model_d_c, "[{$i}]ID_TURNO")->widget(Select2::classname(), [
                                            'data' => $data,
                                            'theme' => 'bootstrap',
                                            'language' => 'es',
                                            'options' => ['placeholder' => '-Seleccione el Diagnóstico-', 'class' => 'diagnostico_select'],
                                            
                                        ])*/?>
                                    


                                    </td>
                                <?php // if ($model->isNewRecord){?>
        <!--                             <td class="text-center vcenter">
                                        <br>                                    
                                        <button type="button" class="remove-item btn btn-danger btn-xs"><i class="glyphicon glyphicon-minus"></i></button>
                                    </td> -->
                                    <?php //}?>
        <!--                            </tr> -->
                                <?php //endforeach; ?>
        <!--               </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2"></td>
                                    <td><button type="button" class="add-item btn btn-success btn-sm"><span class="glyphicon glyphicon-plus"></span>Nuevo Diagnóstico</button></td>
                                </tr>
                            </tfoot> 
                        </table> -->
                        
                    <?php /////DynamicFormWidget::end(); ?>
            </div>

         </div> 
    </div>
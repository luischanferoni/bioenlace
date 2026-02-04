<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\web\View;
use yii\helpers\Url;
use yii\bootstrap5\Modal;
use kartik\select2\Select2;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use yii\bootstrap5\Dropdown;

use common\models\Persona;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\TurnoBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

/* @var $this yii\web\View */
/* @var $model common\models\Turno */
/* @var $form yii\widgets\ActiveForm */


$this->title = 'Turnos';
$this->params['breadcrumbs'][] = $this->title;

$datosParaLiveSearch = "";
?>
<div class="iq-loader-box" id="cover-spin">
  <div class="iq-loader-14"></div>
</div>


<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
          <h4 class="mb-3">RECURSOS HUMANOS AGRUPADOS POR SERVICIO</h4>
          <div class="mb-md-0 mb-2 col-12 d-flex align-items-center gap-2">
            <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M14.5715 13.5941L20.4266 7.72014C20.7929 7.35183 21 6.84877 21 6.32376V4.60099C21 3.52002 20.1423 3 19.0844 3H4.91556C3.85765 3 3 3.52002 3 4.60099V6.3547C3 6.85177 3.18462 7.33087 3.51772 7.69419L8.89711 13.5632C8.9987 13.674 9.14034 13.7368 9.28979 13.7378L14.1915 13.7518C14.3332 13.7528 14.4699 13.6969 14.5715 13.5941Z" fill="currentColor"></path>
              <path opacity="0.4" d="M9.05615 13.6858V20.2904C9.05615 20.5309 9.17728 20.7575 9.37557 20.8873C9.48889 20.9621 9.61978 21.0001 9.75068 21.0001C9.84934 21.0001 9.948 20.9791 10.0398 20.9372L14.0057 19.0886C14.2539 18.9739 14.4131 18.7213 14.4131 18.4429V13.6858H9.05615Z" fill="currentColor"></path>
            </svg>
            <input name="livesearch" id="live-search-box" value="" class="form-control" placeholder="Escriba aqui para buscar" />
          </div>
          <div class="d-flex align-items-center flex-wrap"></div>
        </div>
      </div>
    </div>
  </div>

    <?php if (count($referencias) > 0) { ?>
		<div class="row">
			<div class="col-12">
				<div class="col-12 p-4 show mb-4 h4 bg-soft-warning alert-left alert-warning d-inline-block rounded">
					<span>Derivaciones solicitadas para este paciente</span>
				</div>
			</div>
		</div>
    <?php } ?>

    <?php
        // desde las referencias, recorremos los servicios que tiene el efector
        // los dos foreach son exactamente iguales, no he encontrado una manera sencilla
        // de juntarlos + quitar la referencia para luego no mostrarlo
        foreach ($referencias as $referencia) {
            foreach ($serviciosXEfector['SIN_DERIVACION'] as $key => $servicioEfector) {                
                if ($servicioEfector->id_servicio == $referencia['id_servicio']) {
                    $arrayColor = unserialize($servicioEfector->servicio->parametros);
                    $color = isset($arrayColor['color'])?$arrayColor['color']:"#000";            
                    echo $this->render('_card_servicio', [
                                                    'color' => $color,
                                                    'servicioEfector' => $servicioEfector,
                                                    'persona' => $persona,
                                                    'datosParaLiveSearch' => $datosParaLiveSearch,
                                                    'referencia' => $referencia
                                                ]);
                    // lo quitamos al servicio para que no se muestre mas adelante
                    unset($serviciosXEfector['SIN_DERIVACION'][$key]);
                }
            }
            foreach ($serviciosXEfector['CON_DERIVACION'] as $key => $servicioEfector) {
                if ($servicioEfector->id_servicio == $referencia['id_servicio']) {
                    $arrayColor = unserialize($servicioEfector->servicio->parametros);
                    $color = isset($arrayColor['color'])?$arrayColor['color']:"#000";            
                    echo $this->render('_card_servicio', [
                                                    'color' => $color, 'servicioEfector' => $servicioEfector, 
                                                    'persona' => $persona,
                                                    'datosParaLiveSearch' => $datosParaLiveSearch,
                                                    'referencia' => null
                                                    ]);
                    // lo quitamos al servicio para que no se muestre mas adelante
                    unset($serviciosXEfector['CON_DERIVACION'][$key]);
                }
            }    
        }
    ?>

    <div class="row">
        <div class="col-12">
            <div class="col-12 p-4 show mb-4 h4 bg-soft-success alert-left alert-success d-inline-block rounded">
                <span>Servicios disponibles</span>
            </div>
        </div>
    </div>
    
    <?php
        foreach ($serviciosXEfector['SIN_DERIVACION'] as $servicioEfector) { 
            
            $arrayColor = unserialize($servicioEfector->servicio->parametros);
            $color = isset($arrayColor['color'])?$arrayColor['color']:"#000";

            echo $this->render('_card_servicio', [
                                            'color' => $color,
                                            'servicioEfector' => $servicioEfector,
                                            'persona' => $persona,
                                            'datosParaLiveSearch' => $datosParaLiveSearch,
                                            'referencia' => null
                                            ]);
        }
    
    ?>

	<?php if (count($serviciosXEfector['CON_DERIVACION']) > 0) { ?>
		<div class="row">
			<div class="col-12">
				<div class="col-12 p-4 show mb-4 h4 bg-soft-dark alert-left alert-soft-dark d-inline-block rounded">
					<span>Servicios que requieren derivación previa para otorgar un turno</span>
				</div>
			</div>
		</div>
	<?php } ?>

  	<?php
        foreach ($serviciosXEfector['CON_DERIVACION'] as $servicioEfector) {

            $arrayColor = unserialize($servicioEfector->servicio->parametros);
            $color = isset($arrayColor['color'])?$arrayColor['color']:"#000";

            echo $this->render('_card_servicio', [
                                            'color' => $color, 'servicioEfector' => $servicioEfector, 
                                            'persona' => $persona,
                                            'con_derivacion' => true,
                                            'datosParaLiveSearch' => $datosParaLiveSearch,
                                            'referencia' => null
                                        ]);
        } 
    ?>  

</div>

<?php
$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/tiny-slider/2.9.4/tiny-slider.css');
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/tiny-slider/2.9.2/min/tiny-slider.js');

$this->registerJs("
    $(document).ready(function () {
        $('.livesearch').each(function() {
          $(this).attr('data-search-term', $(this).val().toLowerCase());
        });
          
        $('#live-search-box').on('keyup', function(){
          
          var searchTerm = $(this).val().toLowerCase();

          $('.livesearch').each(function() {
              if ($(this).filter(\"[data-search-term*='\" + searchTerm + \"']\").length > 0 || searchTerm.length < 1) {
                  $(this).parent().closest('.livesearchparent').show();
              } else {
                $(this).parent().closest('.livesearchparent').hide();
              }
          });    
        });
  });  
  ", yii\web\View::POS_LOAD);

echo $this->render("@app/views/turnos/_calendario.php", ["feriados" => $feriados]);
?>
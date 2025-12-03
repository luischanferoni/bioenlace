<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\CovidEntrevistaTelefonica */

$this->title = 'Entrevista de '.$model->persona->nombreCompleto;
$this->params['breadcrumbs'][] = ['label' => 'Covid Entrevista Telefonica', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

$array_sino = [0 => 'No', 1 => 'Si'];
?>
<div class="covid-entrevista-telefonica-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-<?= $model->getPuntajeEntrevista()[1]; ?>" role="alert">
      <div class="row">
        <div class="col-md-9">
            <?= $model->getPuntajeEntrevista()[0]; ?><br>
            <span class="glyphicon glyphicon-warning-sign" aria-hidden="true"></span> Nota: El haber estado en UTI con Respirador implica directamente alto riesgo independientemente del puntaje obtenido.
        </div>
      </div>
    </div>

    
<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">Información Personal</h3>
  </div>
  <div class="panel-body">
    <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>Apellido y Nombre:</label>&nbsp;<?= $model->persona->nombreCompleto  ?>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>Edad:</label>&nbsp;<?php echo ($model->persona->edad > 4)?$model->persona->edad:$model->persona->edadBebe; ?>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label><?= $model->persona->tipoDocumento->nombre ?>:</label>&nbsp;<?= $model->persona->documento ?>
    </div>
</div>
<div class="row">
    <div class="col-md-6 col-sm-12 col-xs-12">
        <label>Domicilio:</label>&nbsp;
    <?php if(is_object($model->persona->domicilioActivo)){ ?>
    <?= 'Calle:'. $model->persona->domicilioActivo->calle. ' Nro: '. $model->persona->domicilioActivo->numero. 'Mza: '.$model->persona->domicilioActivo->manzana. ' Lote: '. $model->persona->domicilioActivo->lote;?>
    <?php if(is_object($model->persona->domicilioActivo->modelBarrio)){?>
     <?= '  B°: '. $model->persona->domicilioActivo->modelBarrio->nombre; ?>
    <?php } } ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
    <?php if(is_object($model->persona->domicilioActivo)){ ?>
        <label>Localidad:</label>&nbsp;<?= $model->persona->domicilioActivo->idLocalidad->nombre; ?>
    <?php } ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label>Nro de contacto:</label>&nbsp;<?php echo $model->persona->telefonoContacto; ?>
    </div>
</div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12"><label>Convivientes:</label>&nbsp;<?= $array_sino[$model->convivientes] ?></div>
    <div class="col-md-8 col-sm-12 col-xs-12"><label>Datos:</label>&nbsp;<?= $model->convivientes_datos ?></div>
 </div>
 <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12"><label>Resultado Test:</label>&nbsp;<?= $model->resultado ?></div>
    <div class="col-md-4 col-sm-12 col-xs-12"><label>Telefono de contacto:</label>&nbsp;<?= $model->telefono_contacto ?></div>
    <div class="col-md-4 col-sm-12 col-xs-12"><label>Ocupación:</label>&nbsp;<?= $model->ocupacion ?></div>
 </div>
 <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12"><label>Vacunado:</label>&nbsp;<?= $array_sino[$model->vacunado] ?></div>
    <div class="col-md-4 col-sm-12 col-xs-12"><label>Primera Dosis:</label>&nbsp;<?= $model->fecha_primera_dosis ?></div>
    <div class="col-md-4 col-sm-12 col-xs-12"><label>Segunda Dosis:</label>&nbsp;<?= $model->fecha_segunda_dosis ?></div>
 </div>
 </div>
 </div>
<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">Factores de Riesgo <span class="badge"><?= $array_sino[$model->factores_riesgo] ?></span></h3>
  </div>
  <?php if($model->factores_riesgo == 1) { ?> 
  <div class="panel-body">
    <div class="row">
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('asma') ?></label>&nbsp;
        <?= $array_sino[$model->covidFactoresRiesgo->asma] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('diabetes') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->diabetes] ?>
</div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('dialisis') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->dialisis] ?>
</div>
   <div class="col-md-3 col-sm-12 col-xs-12">
        <label> <?= $model->covidFactoresRiesgo->getAttributeLabel('embarazo_puerperio') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->embarazo_puerperio] ?>
    </div>
    </div>        
    <div class="row">
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('enfermedad_hepatica') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->enfermedad_hepatica] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('enfermedad_neurologica') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->enfermedad_neurologica] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('oncologico') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->oncologico] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('enfermedad_renal') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->enfermedad_renal] ?>
    </div>
    </div>        
    <div class="row">
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('epoc') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->epoc] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('fumador_exfumador') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->fumador_exfumador] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('enfermedad_cardiovascular') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->enfermedad_cardiovascular] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('inmunosuprimido')?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->inmunosuprimido] ?>
    </div>
    </div>        
    <div class="row">
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('obeso')?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->obeso] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('neumonia_previa')?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->neumonia_previa] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('tuberculosis')?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->tuberculosis] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('hta') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->hta] ?>
    </div>
    </div>        
    <div class="row">
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('otro') ?></label>&nbsp;
    <?= $array_sino[$model->covidFactoresRiesgo->otro] ?>
    </div>
    <div class="col-md-9 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('otro_texto') ?></label>&nbsp;
    <?= $model->covidFactoresRiesgo->otro_texto ?>
    </div>
    </div> 
     <div class="row">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <label><?= $model->covidFactoresRiesgo->getAttributeLabel('medicacion') ?></label>&nbsp;
    <?= $model->covidFactoresRiesgo->medicacion ?>
    </div>
    </div>         
            
</div>
<?php } ?>
</div>

<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">Investigación Epidemiológica</h3>
  </div>
  <div class="panel-body">
    <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('fecha_inicio_sintomas') ?></label>&nbsp;
    <?= $model->covidInvestigacionEpidemiologica->fecha_inicio_sintomas ?>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('fecha_notificacion_positivo') ?></label>&nbsp;
    <?= $model->covidInvestigacionEpidemiologica->fecha_notificacion_positivo ?>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('fecha_fin_aislamiento') ?></label>&nbsp;
    <?= $model->covidInvestigacionEpidemiologica->fecha_fin_aislamiento ?>
    </div>
    </div>
    <div class="row">
    <div class="col-md-3 col-sm-12 col-xs-12">
      <label><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('internacion')?></label>&nbsp;
      <?= $array_sino[$model->covidInvestigacionEpidemiologica->internacion] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('requiere_oxigeno')?></label>&nbsp;
    <?= $array_sino[$model->covidInvestigacionEpidemiologica->requiere_oxigeno] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('respirador')?></label>&nbsp;
    <?= $array_sino[$model->covidInvestigacionEpidemiologica->respirador] ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('internacion_uti') ?></label>&nbsp;
    <?= $array_sino[$model->covidInvestigacionEpidemiologica->internacion_uti] ?>
    </div>
    </div> 
    <table class="table">
        <caption><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('sintomas') ?> <span class="badge"><?= $array_sino[$model->covidInvestigacionEpidemiologica->sintomas] ?></span></caption>
        <?php if($model->covidInvestigacionEpidemiologica->sintomas == 1) { ?>
        <tbody>
            <tr>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('fiebre') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->fiebre] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('tos') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->tos] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('diarrea_vomitos') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->diarrea_vomitos] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('anosmia_disgeusia') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->anosmia_disgeusia] ?></td>
            </tr>
            <tr>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('dificultad_respiratoria') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->dificultad_respiratoria] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('malestar_general') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->malestar_general] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('cefalea') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->cefalea] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('rinitis_secrecion_nasal') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->rinitis_secrecion_nasal] ?></td>
            </tr>
        </tbody>
    <?php } ?>
    </table>

    <table class="table">
        <caption><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('medicamentos') ?> <span class="badge"><?= $array_sino[$model->covidInvestigacionEpidemiologica->medicamentos] ?></span></caption>
        <?php if($model->covidInvestigacionEpidemiologica->medicamentos == 1) { ?>
        <tbody>
            <tr>
                <th>Indicado por:</th>
                <td colspan="7">
                    <?php 
                        $indicado_por = [];
                        if($model->covidInvestigacionEpidemiologica->indicado_por_medico == 1) {
                            $indicado_por[]= $model->covidInvestigacionEpidemiologica->getAttributeLabel('indicado_por_medico');
                        }
                        if($model->covidInvestigacionEpidemiologica->indicado_equipo_seguimiento == 1) {
                            $indicado_por []= $model->covidInvestigacionEpidemiologica->getAttributeLabel('indicado_equipo_seguimiento');
                        }  
                        if($model->covidInvestigacionEpidemiologica->indicado_familiar == 1) {
                            $indicado_por []= $model->covidInvestigacionEpidemiologica->getAttributeLabel('indicado_familiar');
                        } 
                        if($model->covidInvestigacionEpidemiologica->indicado_automedicado == 1) {
                            $indicado_por []= $model->covidInvestigacionEpidemiologica->getAttributeLabel('indicado_automedicado');
                        }
                        $indicado = implode(", ", $indicado_por); 
                    ?>
                    <?= $indicado ?>
                </td>
            </tr>
            <tr>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('paracetamol') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->paracetamol] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('azitromicina') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->azitromicina] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('corticoides') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->corticoides] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('aspirina') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->aspirina] ?></td>
            </tr>
            <tr>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('ivermectina') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->ivermectina] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('levofloxacina') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->levofloxacina] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('amoxicilina_clavulanico') ?></th>
                <td><?= $array_sino[$model->covidInvestigacionEpidemiologica->amoxicilina_clavulanico] ?></td>
                <th><?= $model->covidInvestigacionEpidemiologica->getAttributeLabel('otro') ?></th>
                <td><?= $model->covidInvestigacionEpidemiologica->otro ?></td>
            </tr>
        </tbody>
    <?php } ?>
    </table>
</div>
</div>
<div class="panel panel-primary">
  <div class="panel-heading">
    <h3 class="panel-title">Entrevista Telefónica con la Persona</h3>
  </div>
  <div class="panel-body">
    <div class="row">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <label>a. <?= $model->getAttributeLabel('continua_sintomas') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->continua_sintomas] ?></span>
    </div>
</div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>b. <?= $model->getAttributeLabel('falta_aire') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->falta_aire] ?></span>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label><?= $model->getAttributeLabel('falta_aire_reposo') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->falta_aire_reposo] ?></span>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label><?= $model->getAttributeLabel('falta_aire_caminar') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->falta_aire_caminar] ?></span>
    </div>
</div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>c. <?= $model->getAttributeLabel('dolor_pecho') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->dolor_pecho] ?></span>
    </div>
    <div class="col-md-8 col-sm-12 col-xs-12">
        <label><?= $model->getAttributeLabel('taquicardia_palpitaciones') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->taquicardia_palpitaciones] ?></span>
    </div>
    
</div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>d. <?= $model->getAttributeLabel('perdida_memoria') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->perdida_memoria] ?></span>
    </div>
    </div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>e. <?= $model->getAttributeLabel('cefalea_dolor_cabeza') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->cefalea_dolor_cabeza] ?></span>
    </div>
   </div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>f. <?= $model->getAttributeLabel('falta_fuerza') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->falta_fuerza] ?></span>
    </div>
    <div class="col-md-8 col-sm-12 col-xs-12">
        <label><?= $model->getAttributeLabel('dolor_muscular') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->dolor_muscular] ?></span>
    </div>
    
</div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>g. <?= $model->getAttributeLabel('secrecion_rinitis_constante') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->secrecion_rinitis_constante] ?></span>
    </div>
    </div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>h. <?= $model->getAttributeLabel('llanto_espontaneo') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->llanto_espontaneo] ?></span>
    </div>
    <div class="col-md-8 col-sm-12 col-xs-12">
        <label><?= $model->getAttributeLabel('cuesta_salir_casa') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->cuesta_salir_casa] ?></span>
    </div>    
</div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>i. <?= $model->getAttributeLabel('tristeza_angustia') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->tristeza_angustia] ?></span>
    </div>
    </div>
    <div class="row">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <label>j. <?= $model->getAttributeLabel('dificultad_realizar_tareas') ?></label>&nbsp;
    <span class="badge"><?= $array_sino[$model->dificultad_realizar_tareas] ?></span>
    </div>
    </div>
</div>
</div>

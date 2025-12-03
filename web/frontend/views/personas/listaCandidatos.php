<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->params['breadcrumbs'][] = $this->title;

$Array_sexo = ['1' => 'Femenino', '2' => 'Masculino', 'F' => 'Femenino', 'M' => 'Masculino', '0' => 'Indefinido'];
$Array_dni = ['1' => 'DNI', '2' => 'DNI de la Madre', '3' => 'Libreta de Enrolamiento', '4' => 'Libreta Cívica', '5' => 'Pasaporte'];
?>
<div class="lista-candidatos">


  <h1><?= Html::encode($this->title) ?></h1>
  <?php echo $this->render('_buscar', ['model' => $model, 'desde' => 'listaCandidatos']); ?>

  <div class="card mt-5">
    <div class="row">
      <div class="card-header ps-5 col-md-12">
        <h3 id="div_lista_candidatos">Candidatos</h3>
      </div>

      <div class="card-body">

        <h6 class="ps-5 alert alert-right alert-warning alert-dismissible fade show mb-5"> <i class="bi bi-info-circle"></i> Personas similares en la base local, seleccione una o agregue una nueva persona con los datos ingresados.</h6>
        <div class="row">
          <div class="col-sm-12">
            <table class="table dataTable">
              <thead>
                <tr>
                  <th scope="col">Apellido</th>
                  <th scope="col">Nombre</th>
                  <th scope="col">Sexo Biologico</th>
                  <th scope="col">Tipo Doc.</th>
                  <th scope="col">Documento</th>
                  <th scope="col">Fecha Nac.</th>
                  <th scope="col">Ranking</th>
                  <th scope="col">&nbsp;</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $peso = 0;
                if (count($lista) > 0) {
                  foreach ($lista as $key => $candidato) { ?>
                    <tr>
                      <th scope="row"><?= $candidato['apellido'] ?></th>
                      <td><?= $candidato['nombre'] ?></td>
                      <td><?= $Array_sexo[$candidato['sexo']] ?></td>
                      <td><?= isset($candidato['tipo_doc']) ? $candidato['tipo_doc'] : $Array_dni[$candidato['tipo_documento']] ?></td>
                      <th><?= isset($candidato['documento']) ? $candidato['documento'] : $candidato['nro_documento'] ?></th>
                      <td><?php
                          $fecha_regexp = '/^([0-2][0-9]|3[0-1])(\/|-)(0[1-9]|1[0-2])\2(\d{4})$/';
                          echo preg_match($fecha_regexp, $candidato['fecha_nacimiento'], $matchFecha) ? $candidato['fecha_nacimiento'] : date('d/m/Y', strtotime($candidato['fecha_nacimiento']));
                          ?></td>
                      <td><?php
                          $peso = isset($candidato['peso_relativo']) ? $candidato['peso_relativo'] : $candidato['score'];
                          if (!is_integer($peso)) {
                            $peso = number_format($peso, 2);
                          }
                          echo $peso . '%';
                          ?></td>
                      <td>
                        <?php if (isset($candidato['tipo']) && $candidato['tipo'] == 'local') { ?>

                          <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Seleccionar">
                            <button class="btn btn-success ver-persona" data-id="<?= isset($candidato['id']) ? $candidato['id'] : $candidato['identificador']; ?>" data-score="<?= $peso ?>" data-tipo='<?= (isset($candidato['tipo'])) ? $candidato['tipo'] : $tipo ?>'><i class="bi bi-check2"></i></button></span>

                        <?php } ?>

                        <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Editar"><button class="btn btn-primary seleccionar-persona" data-id="<?= isset($candidato['id']) ? $candidato['id'] : $candidato['identificador']; ?>" data-score="<?= $peso ?>" data-tipo='<?= (isset($candidato['tipo'])) ? $candidato['tipo'] : $tipo ?>'><i class="bi bi-pencil"></i></button></span>
                      </td>
                    </tr>
                  <?php }
                } else { ?>
                  <tr>
                    <th scope="row" colspan="8">
                      <blockquote>No se encontró ningún candidato en el padrón provincial. Puede proceder a agregarlo.</blockquote>
                    </th>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($bandera_boton_buscar == true) { ?>
          <p>
            <button class="btn btn-success" id="buscar-candidatos"><span class="glyphicon glyphicon-search"></span> Buscar Más Candidatos</button>
          </p>

        <?php } ?>
        <?php if ($bandera_boton_agregar == true) { ?>
          <p>
            <?= Html::a('Agregar Persona', '#', ['class' => 'btn btn-success seleccionar-persona']) ?>
          </p>

        <?php } ?>
      </div>

    </div>
  </div>

</div>
<?php

$url_seleccionar_persona = Url::to(["personas/seleccionar-persona"]);
$url_vista_persona = Url::to(["personas/view"]);

$script = <<<JS
  $(".seleccionar-persona").click(function(){    
    $("#id").val($(this).data('id'));
    $("#tipo").val($(this).data('tipo'));
    $("#score").val($(this).data('score'));
    $('#buscar').attr('action', '$url_seleccionar_persona');  
    $( "#buscar" ).submit();   
  });

  $(".ver-persona").click(function(){    
    $("#id").val($(this).data('id'));
    $("#tipo").val($(this).data('tipo'));
    $("#score").val($(this).data('score'));
    $('#buscar').attr('action', '$url_vista_persona'+'/'+$(this).data('id'));  
    $( "#buscar" ).submit();   
  });
  
  
  $(document).ready(function () {
    location.href = location.href + "#div_lista_candidatos";
  });

$("#buscar-candidatos").click(function(){ 
  $("#tipo").val('masmpi');
 $( "#buscar" ).submit();  
});
JS;
$this->registerJs($script);
?>
<?php

use yii\helpers\Html;

$session = Yii::$app->getSession();
?>
<?php if (isset($session['persona'])) {
  $session_persona = unserialize($session['persona']);
?>

<div class="navbar-expand-xl iq-navbar">
  <div class="col-md-12">

    <div class="alert alert-top alert-info rounded-0">
      <div class="row">
        <div class="col-md-11">
          <strong class="ps-5">Persona: </strong><?= $session_persona->nombre . ' ' . $session_persona->otro_nombre . ' ' . $session_persona->apellido . " " . $session_persona->otro_apellido ?>&nbsp;
          <strong><?= $session_persona->tipoDocumento->nombre . ' </strong> ' . $session_persona->documento ?>&nbsp;&nbsp;
          <?= Html::a('<span class="ps-5" style="font-size: large;"><i class="bi bi-person-lines-fill"></i></span>', ['personas/view', 'id' => $session_persona->id_persona], ['class' => 'alert-link', 'title' => 'Menu Persona']) ?>
          <?= Html::a('<span class="ps-5" style="font-size: large;"><i class="bi bi-file-medical-fill"></i></span>', ['paciente/historia', 'id' => $session_persona->id_persona], ['class' => 'alert-link', 'title' => 'Historia Clínica']) ?>
        </div>

        <div class="col-md-1 d-flex justify-content-end">
          <?= Html::a('<span class=""><i class="bi bi-x-lg"></i></span> ', ['personas/buscar-persona'], ['class' => 'alert-link']) ?>
        </div>
      </div>
    </div>
    
  </div>
</div>

<?php } ?>
<?php

/**
 * @autor: María de los Ángeles Valdez
 * @versión: 1.2.
 * @creación: 15/10/2015
 * @modificación: 05/11/2015
 **/

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;
/* @var $this yii\web\View */
/* @var $model common\models\Efector */

$this->title = 'Actualización de la tabla Efectores';
$this->params['breadcrumbs'][] = $this->title;
$this->params['breadcrumbs'][] = ['label' => 'Listado de Efectores', 'url' => ['index']];
?>

<br>
<h3>Subir archivo al servidor de BIOENLACE</h3> 
<br>

<?php $form = ActiveForm::begin([
     "method" => "post",
     "enableClientValidation" => true,
     "options" => ["enctype" => "multipart/form-data"],
     ]);
?>

<?= $form->field($model, "file")->fileInput(['simple' => true]) ?>

<!-- $form->field($model, "file[]")->fileInput(['multiple' => true]) //Para subir más de un archivo -->

<?= Html::submitButton("Subir", ["class" => "btn btn-primary"]) ?>

<?= $confirmacion ?>

<br>
<br>
<br>
<p>
    <strong><h5>Presione los botones para actualizar la tabla efectores de BIOENLACE</h5></strong>
    <?php
    if (isset($archivos)) {
        foreach ($archivos as $file) {
            $nombreArchivo = substr($file, strrpos($file, '/') + 1);
            echo $nombreArchivo."  "; 
            echo Html::a('Insertar efectores', ['insertar_efectores', 'archivo' => $nombreArchivo], ['class' => 'btn btn-primary']);
            echo "<br>";
        }
    } else {
        echo "No se encuentra ningun archivo para procesar.";
    } ?>
    
    
    <?= "<br><br>".$insertados."<br><br>"?>
    
    
    <?= Html::a('Desactivar efectores', ['desactivar-efectores'], ['class' => 'btn btn-primary']) ?>
    
    <?= "<br><br>".$desactivados."<br><br>"?>
    
</p>

<?php $form->end() ?>
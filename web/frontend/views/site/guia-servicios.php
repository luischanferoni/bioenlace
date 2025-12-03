<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model common\models\ContactForm */

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\captcha\Captcha;
use frontend\assets\CustomAsset;



CustomAsset::register($this);
$this->title = 'Guía de Servicios y Centros de Salud';
$this->params['breadcrumbs'][] = $this->title;


?>

<style type="text/css">   
    
    .head-grupo1 {
        color: #B23437;
        border-color: #B23437;
    }
       
</style>

<div class="site-contact">
    <h1><?= Html::encode($this->title) ?></h1>
	&nbsp;&nbsp;
	
	<div class="panel panel-primary <?= 'head-grupo1'?>">
		<h4 align="center">COMUNICADO</h4>
		&nbsp;
		<p><b>En el marco de la Emergencia Sanitaria por COVID-19, los centros de salud han reorganizado sus servicios, por lo que los días y horarios informados en este portal pueden sufrir modificaciones.
				Ante cualquier duda contáctese telefónicamente con su centro de salud.</b></p>
	</div>
	
   <div class="container1">
       
        <div class="card">
            <img src="<?= Yii ::getAlias('@web') ?>/images/santiago.png">
            <h4>Santiago del Estero</h4>
            <p>Guía de Servicios y Centros de Salud de la ciudad de Santiago del Estero.</p>
            <?= Html::a('Ver más', ['site/centros-salud', 'id' => 4599]);?>            
        </div>
        
        <div class="card">
            <img src="<?= Yii ::getAlias('@web') ?>/images/banda.png">
            <h4>La Banda</h4>
            <p>Guía de Servicios y Centros de Salud de la ciudad de La Banda.</p>
            <?= Html::a('Ver más', ['site/centros-salud', 'id' => 4518]);?>
        </div>        
    </div>
		
	
	<?php 
		require_once("visitas/usuarios.php");
		//require_once("visitas/visitas.php"); 
	?>

	
</div>

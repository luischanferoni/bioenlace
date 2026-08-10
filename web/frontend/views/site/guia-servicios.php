<?php

/* @var $this yii\web\View */

use yii\helpers\Html;

$this->title = 'Guía de Servicios y Centros de Salud';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="container py-4">
    <h1 class="h3 mb-4"><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-danger" role="alert">
        <h2 class="h5 alert-heading text-center">COMUNICADO</h2>
        <p class="mb-0">
            <strong>En el marco de la Emergencia Sanitaria por COVID-19, los centros de salud han reorganizado sus servicios, por lo que los días y horarios informados en este portal pueden sufrir modificaciones.
            Ante cualquier duda contáctese telefónicamente con su centro de salud.</strong>
        </p>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <img class="card-img-top" src="<?= Html::encode(Yii::getAlias('@web')) ?>/images/santiago.png" alt="Santiago del Estero">
                <div class="card-body">
                    <h2 class="h5 card-title">Santiago del Estero</h2>
                    <p class="card-text">Guía de Servicios y Centros de Salud de la ciudad de Santiago del Estero.</p>
                    <?= Html::a('Ver más', ['site/centros-salud', 'id' => 4599], ['class' => 'stretched-link']); ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <img class="card-img-top" src="<?= Html::encode(Yii::getAlias('@web')) ?>/images/banda.png" alt="La Banda">
                <div class="card-body">
                    <h2 class="h5 card-title">La Banda</h2>
                    <p class="card-text">Guía de Servicios y Centros de Salud de la ciudad de La Banda.</p>
                    <?= Html::a('Ver más', ['site/centros-salud', 'id' => 4518], ['class' => 'stretched-link']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

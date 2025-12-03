<?php

use yii\helpers\Html;
use yii\helpers\Url;

use common\models\Consulta;
?>

<div class="timeline-dots1">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-break" viewBox="0 0 16 16">
        <path d="M14 4.5V9h-1V4.5h-2A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v7H2V2a2 2 0 0 1 2-2h5.5zM13 12h1v2a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-2h1v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1zM.5 10a.5.5 0 0 0 0 1h15a.5.5 0 0 0 0-1z"/>
    </svg>
</div>

<h5 class="mb-1">Documento Externo</h5>

<div class="d-inline-block w-100">                                   
    <div class="d-flex align-items-center">
        <p class="pe-3">Título: <?=$historia['resumen']?></p>
    </div>
    <div class="d-flex align-items-center">
        <p class="pe-3 border-end">Subido por: <?=$historia['rr_hh']?></p>
        <p class="ps-3 pe-3 border-end">Servicio: <?=$historia['servicio']?></p>
    </div>
</div>

<br>
<?php
    $adjuntos = common\models\Adjunto::find()->where(['parent_id' => $historia['id'], 'parent_class' => 'DocumentosExternos'])->all();
?>

<b>Adjuntos:</b>
    <?php foreach($adjuntos as $i => $adjunto) { ?>
        <b>
            <?= Html::a(
                "Ver documento ".($i + 1),
                ['adjunto/ver', 'id' => $adjunto->id],
                ['target' => '_blank']
            ); ?>
        </b>
    <?php } ?>

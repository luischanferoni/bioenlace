<?php

use yii\helpers\Html;
use yii\bootstrap5\Modal;
use common\models\SegNivelInternacion;

?>
<div class="timeline-dots1 border-warning text-warning">
    <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 640 512" fill="currentColor">
        <path d="M176 256c44.11 0 80-35.89 80-80s-35.89-80-80-80-80 35.89-80 80 35.89 80 80 80zm352-128H304c-8.84 0-16 7.16-16 16v144H64V80c0-8.84-7.16-16-16-16H16C7.16 64 0 71.16 0 80v352c0 8.84 7.16 16 16 16h32c8.84 0 16-7.16 16-16v-48h512v48c0 8.84 7.16 16 16 16h32c8.84 0 16-7.16 16-16V240c0-61.86-50.14-112-112-112z" />
    </svg>
</div>

<h5 class="mb-1">Internación</h5>

<div class="d-inline-block w-100">
    <p><?= $historia['resumen'] ?></p>
    <div class="d-flex align-items-center">
        <p class="pe-3 border-end">Profesional: <?= $historia['rr_hh'] ?></p>
        <p class="ps-3 pe-3">Efector Origen: <?= $historia['efector'] ?></p>
    </div>
</div>

<div class="iq-media-group iq-media-group-1 timeline_footer mt-2 pb-3 border-bottom">
    <?= SegNivelInternacion::footerTimeline($historia['id']) ?>
    <?php
    /*echo Html::a(
            'Ir a Internación',
            ['internacion/'.$historia['id']],
            [
                'class' => 'btn btn-sm btn-outline-info rounded-pill', 
                'title' => 'Internacion',
                'target' => '_blank'
            ]
        );    
    */ ?>
</div>
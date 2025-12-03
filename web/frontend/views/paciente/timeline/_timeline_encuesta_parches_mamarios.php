<?php

use yii\helpers\Html;
use yii\bootstrap5\Modal;
use common\models\EncuestaParchesMamarios;

?>

<div class="timeline-dots1 border-success text-success">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list" viewBox="0 0 16 16">
        <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z" />
        <path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8zm0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zM4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0z" />
    </svg>
</div>

<h5 class="mb-2 mt-3">Encuesta</h5>

<div class="d-inline-block w-100">
    <p><?= $historia['resumen'] ?></p>
</div>

<?php
echo Html::a(
    'Ver Detalles',
    ['encuesta-parches-mamarios/view', 'id' => $historia['parent_id']],
    $options = [
          'class' => 'btn btn-sm btn-outline-info rounded-pill linkaModalGeneral',
          'data-title' => "Última Encuesta Parches Mamarios",
          'title' => 'Última Encuesta Parches Mamarios',
        ]
);
?>
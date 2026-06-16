<?php
use yii\helpers\Html;
use yii\widgets\LinkPager;

/* @var $this yii\web\View */
/* @var $abreviaturas array */
/* @var $total int */
/* @var $pagination yii\data\Pagination */

$this->title = 'Abreviaturas';
?>

<div class="abreviaturas-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Total: <?= $total ?></p>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Abreviatura</th>
                <th>Expansión</th>
                <th>Categoría</th>
                <th>Especialidad</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($abreviaturas as $item): ?>
            <tr>
                <td><?= Html::encode($item->abreviatura) ?></td>
                <td><?= Html::encode($item->expansion_completa) ?></td>
                <td><?= Html::encode($item->categoria) ?></td>
                <td><?= Html::encode($item->especialidad) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination-wrapper">
        <?= LinkPager::widget(['pagination' => $pagination]) ?>
    </div>
</div>



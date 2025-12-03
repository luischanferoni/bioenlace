<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionDiagnostico */

$this->title = 'Problemas, Síntomas, Signos observados';
$this->params['breadcrumbs'][] = ['label' => 'Internaciones', 'url' => ['/internacion/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="row">
    <div class="col-sm-2"></div>
    <div class="col-sm">

        <div class="card">
            <div class="card-header bg-soft-info">
                <h2><?= Html::encode($this->title) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-sm-2"></div>
</div>

<?= $this->render('_form', [
    'models' => $models,
    'id_internacion' => $id_internacion,
]) ?>

</div>
<?php
$this->registerJs(
    '
        function initSelect2DropStyle(a,b,c){
            initS2Loading(a,b,c);
        }
        function initSelect2Loading(a,b){
            initS2Loading(a,b);
        }
    ',
    yii\web\View::POS_HEAD
)
?>
<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var common\models\Platform\User $model
 */

$this->title = 'Alta de usuario';
$this->params['breadcrumbs'][] = ['label' => 'Usuarios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>

    <div class="card-body">
		<?= $this->render('_form', ['model' => $model]) ?>
    </div>
</div>

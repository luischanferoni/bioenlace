<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var common\models\Platform\User $model
 */

$this->title = 'Editar usuario: ' . $model->username;
$this->params['breadcrumbs'][] = ['label' => 'Usuarios', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->username, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Edición';
?>
<div class="card">

	<div class="card-header">
		<h2 class="lte-hide-title"><?= $this->title ?></h2>
	</div>

	<div class="card-body">
		<?= $this->render('_form', compact('model')) ?>
	</div>

</div>

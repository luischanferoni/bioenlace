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
<div class="user-create">

	<h2 class="lte-hide-title"><?= $this->title ?></h2>

	<div class="panel panel-default">
		<div class="panel-body">

			<?= $this->render('_form', ['model' => $model]) ?>
			
		</div>
	</div>

</div>

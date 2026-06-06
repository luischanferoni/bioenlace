<?php

use yii\widgets\DetailView;

/* @var $model common\models\DataAccess\DataAccessRoleGrant */
?>
<?= DetailView::widget([
    'model' => $model,
    'attributes' => [
        'id',
        'role_name',
        'entity_group_key',
        'operations_csv',
        'scope_checker',
        [
            'attribute' => 'active',
            'value' => (int) $model->active === 1 ? 'Sí' : 'No',
        ],
        'notas:ntext',
    ],
]) ?>

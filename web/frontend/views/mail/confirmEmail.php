<?php

use common\models\User;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var User $user */
/** @var string $confirmUrl */
?>
<p>Hola <?= Html::encode($user->username) ?>,</p>
<p>Confirme su dirección de e-mail para su cuenta en <?= Html::encode(Yii::$app->name) ?>.</p>
<p><?= Html::a('Confirmar e-mail', $confirmUrl) ?></p>
<p>Si usted no solicitó esta confirmación, puede ignorar este mensaje.</p>
<p>El enlace expira en <?= (int) (Yii::$app->params['user.passwordResetTokenExpire'] ?? 3600) / 60 ?> minutos.</p>

<?php

use common\models\User;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var User $user */
/** @var string $resetUrl */
?>
<p>Hola <?= Html::encode($user->username) ?>,</p>
<p>Recibimos una solicitud para restablecer la contraseña de su cuenta en <?= Html::encode(Yii::$app->name) ?>.</p>
<p><?= Html::a('Restablecer contraseña', $resetUrl) ?></p>
<p>Si usted no solicitó este cambio, puede ignorar este mensaje.</p>
<p>El enlace expira en <?= (int) (Yii::$app->params['user.passwordResetTokenExpire'] ?? 3600) / 60 ?> minutos.</p>

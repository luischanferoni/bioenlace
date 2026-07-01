<?php

use common\models\User;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var User $user */
/** @var string $activateUrl */
/** @var int $expireHours */
?>
<p>Hola <?= Html::encode($user->username) ?>,</p>
<p>Se creó tu acceso a <?= Html::encode(Yii::$app->name) ?>. Para activar la cuenta y elegir tu contraseña, usá el siguiente enlace:</p>
<p><?= Html::a('Activar mi cuenta', $activateUrl) ?></p>
<p>Si no esperabas este mensaje, podés ignorarlo.</p>
<p>El enlace expira en <?= (int) $expireHours ?> horas.</p>

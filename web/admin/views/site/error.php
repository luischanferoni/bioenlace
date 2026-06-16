<?php

/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

use yii\helpers\Html;

$this->title = $name;

if ($exception instanceof \yii\base\UserException) {
    $message = $exception->getMessage();
} else {
    $message = 'Ocurrió un error interno del sistema.';
}

?>

<div class="jumbotron alert alert-danger">
    <div class="text-center">                
        <p><?= nl2br(Html::encode($message)) ?></p>
    </div>
</div>
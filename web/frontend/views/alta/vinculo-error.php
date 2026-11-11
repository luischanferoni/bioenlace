<?php

/**
 * Vínculo inválido o de otro dispositivo.
 *
 * @var string $message
 */

use yii\helpers\Html;
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card auth-card">
                <div class="card-body text-center">
                    <h1 class="h4 mb-3">No pudimos abrir el alta</h1>
                    <p class="text-muted"><?= Html::encode($message) ?></p>
                    <p class="text-muted small">
                        Abrí el enlace desde el teléfono con el que escaneaste el código, o
                        volvé a empezar desde la computadora.
                    </p>
                    <?= Html::a('Empezar de nuevo', ['/alta'], ['class' => 'btn btn-primary']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

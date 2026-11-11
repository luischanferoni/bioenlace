<?php

/**
 * Página intermedia que abre el teléfono al escanear el QR de ingreso de la web.
 *
 * Muestra qué navegador pide el acceso y ofrece los caminos disponibles. No hay
 * verificación acá: la app aprueba el desafío o el usuario entra con su passkey.
 *
 * @var string $challenge
 * @var string $appUrl
 * @var string $requestedAt
 * @var string $deviceLabel
 */

use yii\helpers\Html;
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card auth-card">
                <div class="card-body text-center">
                    <h1 class="h4 mb-3">Ingresar a Bioenlace</h1>
                    <p class="text-muted mb-4">
                        Alguien está ingresando desde <strong><?= Html::encode($deviceLabel) ?></strong>
                        a las <?= Html::encode($requestedAt) ?>.
                    </p>

                    <a class="btn btn-primary w-100 mb-3" href="<?= Html::encode($appUrl) ?>">
                        Abrir la app Personal de Salud
                    </a>
                    <p class="text-muted small">
                        Si la app ya está abierta, esto no hace nada: en tu teléfono vas a ver un
                        pedido de acceso para confirmar con tu huella o rostro.
                    </p>

                    <hr class="my-4">

                    <p class="mb-3">
                        ¿No tenés la app en este teléfono?
                    </p>
                    <?= Html::a(
                        'Ingresar con la passkey de este dispositivo',
                        ['/auth/login'],
                        ['class' => 'btn btn-outline-primary w-100 mb-2']
                    ) ?>
                    <?= Html::a(
                        'Pedir ayuda para recuperar el acceso',
                        ['/auth/recovery-request'],
                        ['class' => 'btn btn-link w-100']
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</div>

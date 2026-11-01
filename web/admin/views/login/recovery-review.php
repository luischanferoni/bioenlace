<?php

/**
 * @var array $requests
 */

use yii\helpers\Html;

$this->title = 'Recuperación de acceso';
?>

<div class="container my-4">
    <h1>Recuperación de acceso</h1>
    <p class="text-muted">
        Revisá cada solicitud y verificá la identidad antes de aprobar. Al aprobar se revocan
        las sesiones y passkeys anteriores de la cuenta y se genera un código de un solo uso.
    </p>

    <?php if (empty($requests)): ?>
        <p class="text-muted">No hay solicitudes pendientes.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <th>Nota</th>
                        <th>Solicitada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <?= Html::encode($request['username'] ?? '—') ?>
                                <?php if (!empty($request['email'])): ?>
                                    <br><small class="text-muted"><?= Html::encode($request['email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= Html::encode($request['request_note'] ?? '') ?></td>
                            <td><?= Yii::$app->formatter->asDatetime((int) $request['created_at']) ?></td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm recovery-approve-button"
                                    data-request-id="<?= Html::encode($request['id']) ?>"
                                >
                                    Aprobar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$approveUrl = \yii\helpers\Json::htmlEncode(\yii\helpers\Url::to(['/auth/recovery-approve']));
$this->registerJs(<<<JS
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    document.querySelectorAll('.recovery-approve-button').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!window.confirm('¿Verificaste la identidad? Al aprobar se revocará el acceso actual y se emitirá un código de un solo uso.')) {
                return;
            }
            button.disabled = true;
            try {
                const response = await fetch({$approveUrl}, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({ requestId: button.dataset.requestId }),
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message ?? 'No se pudo aprobar.');
                }
                window.prompt('Código de recuperación (copia y entregá al usuario):', data.recoveryCode);
                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'No se pudo aprobar la solicitud.');
                button.disabled = false;
            }
        });
    });
})();
JS);
?>

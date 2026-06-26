<?php
/** @var yii\web\View $this */
/** @var common\models\Scheduling\Turno $turno */
/** @var common\models\TurnoResolucion $resolucion */
/** @var string $appUrl */

use yii\helpers\Html;

$this->title = 'Reubicar turno';
?>
<div class="site-login" style="max-width: 480px; margin: 2rem auto; padding: 1rem;">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>
        Tu turno del <strong><?= Html::encode($turno->fecha) ?></strong>
        a las <strong><?= Html::encode(substr((string) $turno->hora, 0, 5)) ?></strong>
        requiere un nuevo horario.
    </p>
    <?php if ($resolucion->toPacienteApiArray()['tiene_opciones_vecinas'] ?? false): ?>
        <p>Podés elegir un horario cercano al original o buscar otro en la app.</p>
    <?php else: ?>
        <p>Elegí un nuevo horario disponible en la app.</p>
    <?php endif; ?>
    <p>
        <?= Html::a('Abrir Bioenlace', $appUrl, ['class' => 'btn btn-primary btn-lg btn-block']) ?>
    </p>
    <p class="text-muted" style="font-size: 0.9rem;">
        Si ya tenés la app instalada, iniciá sesión con tu cuenta para completar la reubicación.
    </p>
</div>

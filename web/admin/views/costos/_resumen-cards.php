<?php

use yii\helpers\Html;

/* @var $resumen array<string, mixed> */
/* @var $estimacion array<string, mixed> */

$tokens = is_array($resumen['tokens'] ?? null) ? $resumen['tokens'] : [];
$usd = is_array($estimacion['usd'] ?? null) ? $estimacion['usd'] : [];
?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-6"><?= (int) ($resumen['total_evitadas'] ?? 0) ?></div>
                <div class="text-muted small">Llamadas evitadas</div>
                <div class="small mt-2">
                    cache <?= (int) ($resumen['evitada_por_cache'] ?? 0) ?>
                    · dedup <?= (int) ($resumen['evitada_por_dedup'] ?? 0) ?>
                    · cpu <?= (int) ($resumen['evitada_por_cpu'] ?? 0) ?>
                    · val. <?= (int) ($resumen['evitada_por_validacion'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-6"><?= (int) ($resumen['llamada_simulada'] ?? 0) ?></div>
                <div class="text-muted small">Llamadas simuladas</div>
                <div class="small mt-2 text-muted">Reales: <?= (int) ($resumen['llamada_real'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="h4 mb-0"><?= (int) ($tokens['prompt_token_count'] ?? 0) ?></div>
                <div class="text-muted small">Tokens prompt</div>
                <div class="small mt-2">
                    cache <?= (int) ($tokens['cached_content_token_count'] ?? 0) ?>
                    · out <?= (int) ($tokens['candidates_token_count'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100 border-primary">
            <div class="card-body">
                <div class="h4 mb-0 text-primary">USD <?= number_format((float) ($usd['total'] ?? 0), 6) ?></div>
                <div class="text-muted small">Estimación total</div>
                <div class="small mt-2 text-muted">
                    fuente <?= Html::encode((string) ($estimacion['fuente_tokens'] ?? '')) ?>
                </div>
            </div>
        </div>
    </div>
</div>

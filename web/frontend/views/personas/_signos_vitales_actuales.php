<?php

use yii\helpers\Html;
use yii\helpers\Url;

if (!empty($ultimos_sv)): ?>
    <div class="row g-3 mb-3">
        <?php if (isset($ultimos_sv['peso']['value'])): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card h-100 border-0">
                    <div class="card-body p-1">
                        <h6 class="card-title mb-2 d-flex align-items-center">
                            <i class="bi bi-speedometer2 text-primary me-2"></i>
                            <span>Peso</span>
                        </h6>
                        <p class="card-text fw-bold mb-1 fs-6">
                            <?= Html::encode($ultimos_sv['peso']['value']) ?> kg
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($ultimos_sv['talla']['value'])): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card h-100 border-0">
                    <div class="card-body p-1">
                        <h6 class="card-title mb-2 d-flex align-items-center">
                            <i class="bi bi-rulers text-success me-2"></i>
                            <span>Altura</span>
                        </h6>
                        <p class="card-text fw-bold mb-1 fs-6">
                            <?= Html::encode($ultimos_sv['talla']['value']) ?> cm
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($ultimos_sv['imc']['value'])): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card h-100 border-0">
                    <div class="card-body p-1">
                        <h6 class="card-title mb-2 d-flex align-items-center">
                            <i class="bi bi-graph-up text-info me-2"></i>
                            <span>IMC</span>
                        </h6>
                        <p class="card-text fw-bold mb-1 fs-6">
                            <?= Html::encode($ultimos_sv['imc']['value']) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($ultimos_sv['ta']['sistolica']) && isset($ultimos_sv['ta']['diastolica'])): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card h-100 border-0">
                    <div class="card-body p-1">
                        <h6 class="card-title mb-2 d-flex align-items-center">
                            <i class="bi bi-heart-pulse text-danger me-2"></i>
                            <span>Tensión Arterial</span>
                        </h6>
                        <p class="card-text fw-bold mb-1 fs-6">
                            <?= Html::encode($ultimos_sv['ta']['sistolica']) ?>/<?= Html::encode($ultimos_sv['ta']['diastolica']) ?> mmHg
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="text-muted">
        <i class="bi bi-info-circle"></i> No se encontraron signos vitales registrados
    </div>
<?php endif; ?>

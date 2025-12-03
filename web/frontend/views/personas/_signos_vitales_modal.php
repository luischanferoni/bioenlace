<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;

?>

<div class="signos-vitales-modal">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-heart-pulse"></i> Historial de Signos Vitales
            </h5>
        </div>
        <div class="card-body">
            <?php 
            // Debug temporal - remover después
            echo '<div class="alert alert-info">Debug: Total registros = ' . count($datos_sv) . '</div>';
            ?>
            <?php if (!empty($datos_sv)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Presión Arterial</th>
                                <th>Frecuencia Cardíaca</th>
                                <th>Temperatura</th>
                                <th>Saturación O₂</th>
                                <th>Peso</th>
                                <th>Altura</th>
                                <th>IMC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos_sv as $sv): ?>
                                <tr>
                                    <td>
                                        <?php if (isset($sv['fecha'])): ?>
                                            <?= date('d/m/Y H:i', strtotime($sv['fecha'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($sv['presion_sistolica']) && isset($sv['presion_diastolica'])): ?>
                                            <span class="badge bg-primary">
                                                <?= $sv['presion_sistolica'] ?>/<?= $sv['presion_diastolica'] ?> mmHg
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($sv['frecuencia_cardiaca'])): ?>
                                            <span class="badge bg-info">
                                                <?= $sv['frecuencia_cardiaca'] ?> lpm
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($sv['temperatura'])): ?>
                                            <span class="badge bg-warning">
                                                <?= $sv['temperatura'] ?>°C
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($sv['saturacion_oxigeno'])): ?>
                                            <span class="badge bg-success">
                                                <?= $sv['saturacion_oxigeno'] ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($sv['peso'])): ?>
                                            <?= $sv['peso'] ?> kg
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($sv['altura'])): ?>
                                            <?= $sv['altura'] ?> cm
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($sv['imc'])): ?>
                                            <span class="badge bg-secondary">
                                                <?= number_format($sv['imc'], 1) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No se encontraron registros de signos vitales para este paciente.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Test Action Matching';

$defaultJson = json_encode([
    "intent" => "Solicitar un turno con un especialista",
    "search_keywords" => ["turno", "odontologo"],
    "entity_types" => ["Turnos", "Agendas", "Recursos Humanos"],
    "entity_type" => "Turnos",
    "operation_hints" => ["crear", "solicitar", "agendar"],
    "extracted_data" => [
        "identifiers" => [],
        "dates" => [],
        "names" => [],
        "numbers" => []
    ],
    "filters" => [
        "user_owned" => true,
        "date_range" => null,
        "custom" => [
            "especialidad" => "odontologia"
        ]
    ],
    "query_type" => "create"
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <h1>🧪 Test Action Matching</h1>
            
            <?php $form = ActiveForm::begin([
                'method' => 'post',
                'options' => ['class' => 'test-form']
            ]); ?>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h3 class="card-title">JSON de Criterios</h3>
                    
                    <div class="form-group">
                        <?= Html::label('Criterios (JSON)', 'criteriaJson', ['class' => 'form-label']) ?>
                        <?= Html::textarea('criteriaJson', $defaultJson, [
                            'id' => 'criteriaJson',
                            'class' => 'form-control',
                            'rows' => 15,
                            'style' => 'font-family: monospace; font-size: 12px;'
                        ]) ?>
                        <small class="form-text text-muted">
                            Edita el JSON con los criterios que quieres probar
                        </small>
                    </div>
                    
                    <?= Html::submitButton('🔍 Probar Matching', ['class' => 'btn btn-primary btn-lg']) ?>
                </div>
            </div>
            
            <?php ActiveForm::end(); ?>
            
            <?php if (isset($result)): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title">
                            <?php if ($result['success']): ?>
                                ✅ Resultado del Test
                            <?php else: ?>
                                ❌ Error
                            <?php endif; ?>
                        </h3>
                        
                        <?php if ($result['success']): ?>
                            <?php 
                            $hasAssociation = $result['has_association'] ?? false;
                            $associationAnalysis = $result['association_analysis'] ?? [];
                            $reason = $associationAnalysis['reason'] ?? 'unknown';
                            $details = $associationAnalysis['details'] ?? [];
                            ?>
                            
                            <div class="alert <?= $hasAssociation ? 'alert-success' : 'alert-warning' ?>">
                                <h4>
                                    <?php if ($hasAssociation): ?>
                                        ✅ <strong>ASOCIACIÓN ENCONTRADA</strong>
                                    <?php else: ?>
                                        ❌ <strong>NO HAY ASOCIACIÓN</strong>
                                    <?php endif; ?>
                                </h4>
                                <strong>Razón:</strong> 
                                <?php 
                                $reasonMessages = [
                                    'success' => '✅ Se encontraron acciones asociadas exitosamente',
                                    'no_actions_available' => '⚠️ No hay acciones disponibles para el usuario',
                                    'no_semantic_match' => '❌ Ninguna acción obtuvo score > 0. Los criterios no coinciden con ninguna acción disponible.',
                                    'low_score_threshold' => '⚠️ Algunas acciones obtuvieron score > 0, pero no pasaron el filtro final.',
                                    'error' => '❌ Error al procesar la consulta',
                                ];
                                echo $reasonMessages[$reason] ?? 'Desconocida';
                                ?>
                                <br>
                                <strong>Total de acciones disponibles:</strong> <?= $result['total_actions_available'] ?><br>
                                <strong>Acciones con score > 0:</strong> <?= $result['actions_with_score'] ?><br>
                                <strong>Acciones encontradas:</strong> <?= $result['actions_found'] ?>
                            </div>
                            
                            <?php if (!empty($details)): ?>
                                <div class="alert alert-secondary">
                                    <h5>📋 Detalles del Análisis:</h5>
                                    <p><strong>Mensaje:</strong> <?= Html::encode($details['message'] ?? 'N/A') ?></p>
                                    
                                    <?php if (isset($details['criteria_received'])): ?>
                                        <h6>Criterios Recibidos:</h6>
                                        <pre class="bg-light p-2 rounded"><code><?= Html::encode(json_encode($details['criteria_received'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($details['top_5_actions_checked'])): ?>
                                        <h6>Top 5 Acciones Evaluadas:</h6>
                                        <ul>
                                            <?php foreach ($details['top_5_actions_checked'] as $action): ?>
                                                <li>
                                                    <strong><?= Html::encode($action['display_name'] ?? 'N/A') ?></strong> 
                                                    (<?= Html::encode($action['controller'] ?? 'N/A') ?>/<?= Html::encode($action['action'] ?? 'N/A') ?>) 
                                                    - Score: <span class="badge bg-secondary"><?= number_format($action['score'], 2) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($details['max_score_found'])): ?>
                                        <p><strong>Score máximo encontrado:</strong> <?= number_format($details['max_score_found'], 2) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($details['best_match_score'])): ?>
                                        <p><strong>Score del mejor match:</strong> <?= number_format($details['best_match_score'], 2) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['found_actions'])): ?>
                                <h4>🎯 Acciones Encontradas:</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Action ID</th>
                                                <th>Route</th>
                                                <th>Controller</th>
                                                <th>Action</th>
                                                <th>Entity</th>
                                                <th>Tags</th>
                                                <th>Keywords</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['found_actions'] as $action): ?>
                                                <tr>
                                                    <td><?= Html::encode($action['action_id'] ?? 'N/A') ?></td>
                                                    <td><code><?= Html::encode($action['route'] ?? 'N/A') ?></code></td>
                                                    <td><?= Html::encode($action['controller'] ?? 'N/A') ?></td>
                                                    <td><?= Html::encode($action['action'] ?? 'N/A') ?></td>
                                                    <td><?= Html::encode($action['entity'] ?? 'N/A') ?></td>
                                                    <td><?= Html::encode(implode(', ', $action['tags'] ?? [])) ?></td>
                                                    <td><?= Html::encode(implode(', ', $action['keywords'] ?? [])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    ⚠️ No se encontraron acciones con score > 0
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['top_scored_actions'])): ?>
                                <h4 class="mt-4">📊 Top 10 Acciones por Score:</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Score</th>
                                                <th>Action ID</th>
                                                <th>Route</th>
                                                <th>Controller</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['top_scored_actions'] as $item): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-success">
                                                            <?= number_format($item['score'], 2) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= Html::encode($item['action']['action_id'] ?? 'N/A') ?></td>
                                                    <td><code><?= Html::encode($item['action']['route'] ?? 'N/A') ?></code></td>
                                                    <td><?= Html::encode($item['action']['controller'] ?? 'N/A') ?></td>
                                                    <td><?= Html::encode($item['action']['action'] ?? 'N/A') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['debug_all_actions_scores'])): ?>
                                <h4 class="mt-4">🔍 Debug: Scores de Todas las Acciones Evaluadas</h4>
                                <p class="text-muted">Información de debugging para todas las acciones evaluadas con sus scores. Útil para entender por qué un JSON no se asocia con acciones.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Score</th>
                                                <th>Action ID</th>
                                                <th>Controller</th>
                                                <th>Action</th>
                                                <th>Route</th>
                                                <th>Display Name</th>
                                                <th>Entity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Ordenar por score descendente para ver las mejores primero
                                            $sortedScores = $result['debug_all_actions_scores'];
                                            usort($sortedScores, function($a, $b) {
                                                return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
                                            });
                                            foreach (array_slice($sortedScores, 0, 20) as $scoreInfo): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge <?= ($scoreInfo['score'] ?? 0) > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                                            <?= number_format($scoreInfo['score'] ?? 0, 2) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= Html::encode($scoreInfo['action_id'] ?? 'N/A') ?></td>
                                                    <td><?= Html::encode($scoreInfo['controller'] ?? 'N/A') ?></td>
                                                    <td><?= Html::encode($scoreInfo['action'] ?? 'N/A') ?></td>
                                                    <td><code><?= Html::encode($scoreInfo['route'] ?? 'N/A') ?></code></td>
                                                    <td><?= Html::encode($scoreInfo['display_name'] ?? 'N/A') ?></td>
                                                    <td><?= Html::encode($scoreInfo['entity'] ?? 'N/A') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($result['debug_all_actions_scores']) > 20): ?>
                                    <p class="text-muted">Mostrando las top 20 acciones. Total evaluadas: <?= count($result['debug_all_actions_scores']) ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?= Html::encode($result['error'] ?? 'Error desconocido') ?>
                                <?php if (isset($result['trace']) && YII_DEBUG): ?>
                                    <pre class="mt-3"><code><?= Html::encode($result['trace']) ?></code></pre>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

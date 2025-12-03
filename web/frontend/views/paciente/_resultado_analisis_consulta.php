<?php
/**
 * Vista parcial para mostrar el resultado del análisis de consulta
 * @var array $datos Datos extraídos por la IA
 * @var array $sugerencias Sugerencias adicionales
 * @var array $categorias Categorías de configuración
 * @var bool $tieneDatosFaltantes Indica si faltan datos requeridos
 */

use yii\helpers\Html;
?>

<div class="analysis-results">
    <h6 class="text-dark mb-3">Análisis de la Consulta:</h6>
    
    <?php if (isset($datos['datosExtraidos']['Error']) && $datos['datosExtraidos']['Error']['tipo'] === 'error_sistema'): ?>
        <!-- Mostrar error del sistema -->
        <div class="alert alert-danger" role="alert">
            <h6 class="alert-heading">
                <i class="bi bi-exclamation-triangle-fill"></i> Error en el Procesamiento
            </h6>
            <p class="mb-0"><?= Html::encode($datos['datosExtraidos']['Error']['texto']) ?></p>
            <hr>
            <p class="mb-0"><strong>Recomendación:</strong> <?= Html::encode($datos['datosExtraidos']['Error']['detalle']) ?></p>
        </div>

    <?php elseif (!empty($categorias)): ?>
        <?php foreach ($categorias as $categoria): ?>
            <?php
            $titulo = $categoria['titulo'];
            $esRequerida = $categoria['requerido'] ?? false;
            $datosCategoria = $datos[$titulo] ?? [];
            $sugerenciasCategoria = $sugerencias['sugerencias_' . strtolower(str_replace(' ', '_', $titulo))] ?? [];
            ?>
            
            <div class="mb-3">
                <h6 class="border-bottom border-2 border-dark pb-2">
                    <?= Html::encode($titulo) ?>
                    <?php if ($esRequerida): ?>
                        <span class="badge bg-danger">Requerido</span>
                    <?php endif; ?>
                </h6>
                
                <?php if (!empty($datosCategoria)): ?>
                    <ul class="list-unstyled">
                        <?php foreach ($datosCategoria as $key => $item): ?>
                            <?php if (is_string($item)): ?>
                                <!-- String directo (para Motivos de Consulta y Evaluación) -->
                                <li>
                                    • <?= Html::encode($item) ?>
                                </li>
                            <?php elseif (is_array($item)): ?>
                                <!-- Array con estructura específica -->
                                <li class="mb-3 p-3 border rounded bg-light">
                                    <?php foreach ($item as $subKey => $subValue): ?>
                                        <div class="mb-1">
                                            <strong><?= Html::encode($subKey) ?>:</strong> <?= Html::encode($subValue) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (isset($item['conceptId']) && !empty($item['conceptId'])): ?>
                                        <?php
                                        $confianza = $item['confianza_snomed'] ?? 0;
                                        $metodo = $item['metodo_snomed'] ?? 'semantico';
                                        
                                        // Color del badge según confianza
                                        $colorBadge = 'success'; // Verde por defecto
                                        if ($confianza < 0.5) {
                                            $colorBadge = 'danger'; // Rojo
                                        } elseif ($confianza < 0.8) {
                                            $colorBadge = 'warning'; // Amarillo
                                        }
                                        ?>
                                        <span class="badge bg-<?= $colorBadge ?> ms-2" 
                                              title="Confianza: <?= round($confianza * 100) ?>% - Método: <?= ucfirst($metodo) ?>">
                                            SNOMED: <?= Html::encode($item['conceptId']) ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php else: ?>
                                <!-- Otros tipos de datos -->
                                <li>
                                    • <?= Html::encode($item) ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <?php if ($esRequerida): ?>
                        <p class="text-danger fw-bolder">
                            <i class="bi bi-exclamation-triangle"></i> Esta información es requerida
                        </p>
                    <?php else: ?>
                        <p class="text-warning fw-bolder">No se especificó información para esta categoría</p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($sugerenciasCategoria)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3 mt-2">
                        <h6 class="pt-2 ms-2">Sugerencias de <?= strtolower($titulo) ?>:</h6>
                        <?php foreach ($sugerenciasCategoria as $index => $sugerencia): ?>
                            <button type="button" class="btn btn-outline-dark btn-sm sugerencia-btn" 
                                    data-categoria="<?= strtolower($titulo) ?>" 
                                    data-valor="<?= Html::encode($sugerencia) ?>"
                                    data-index="<?= $index ?>">
                                <i class="bi bi-plus-circle"></i> <?= Html::encode($sugerencia) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($sugerencias['alertas'])): ?>
        <div class="mb-3">
            <h6 class="border-bottom border-2 border-danger pb-2 text-danger">Alertas y Precauciones:</h6>
            <div class="d-flex flex-wrap gap-2 mb-3 mt-2">
                <?php foreach ($sugerencias['alertas'] as $index => $alerta): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm sugerencia-btn" 
                            data-categoria="alertas" 
                            data-valor="<?= Html::encode($alerta) ?>"
                            data-index="<?= $index ?>">
                        <i class="bi bi-plus-circle"></i> <?= Html::encode($alerta) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
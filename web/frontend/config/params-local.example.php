<?php
/**
 * Overrides solo web/API (asistente, captura clínica, STT, tuning IA).
 *
 * Copiar a params-local.php (no commitear).
 * Secretos (API keys, GCP JSON, OAuth integraciones): common/config/params-local.php
 *
 * Merge:
 *   common/params.php → common/params-local.php → frontend/params.php → ESTE
 */
return [

    // --- JWT API (solo si distinto del default de frontend/params.php) ---
    // 'jwtSecret' => '…',

    // --- Sistema híbrido corrección clínica (si se reactiva en params.php) ---
    // 'hf_modelo_clinico' => 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es',
    // 'sistema_hibrido' => [ … ],
];

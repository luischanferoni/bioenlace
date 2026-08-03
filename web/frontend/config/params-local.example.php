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

    // --- Demo sandbox (sitio https://bioenlace.io → Probar demo) ---
    // 'demo_sandbox_habilitado' => true,
    // 'demo_sandbox' => [
    //     'app_base_url' => 'https://app.bioenlace.io',
    //     'ttl_seconds' => 900,
    //     'session_ttl_seconds' => 14400,
    //     'max_per_ip_hour' => 10,
    //     'require_captcha' => true,
    //     'captcha_ttl_seconds' => 300,
    //     'id_efector' => 863,
    //     'servicio_nombre' => 'MED GENERAL',
    //     'seed' => [
    //         'pacientes' => 4,
    //         'turnos' => 2,
    //         'with_agenda' => true,
    //         'with_guardia' => true,
    //         'with_internacion' => true,
    //     ],
    //     'profiles' => [
    //         'staff' => [
    //             'label' => 'Médico demo (captura y turnos)',
    //             'mode' => 'ephemeral',
    //         ],
    //     ],
    // ],

    // --- Sistema híbrido corrección clínica (si se reactiva en params.php) ---
    // 'hf_modelo_clinico' => 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es',
    // 'sistema_hibrido' => [ … ],
];

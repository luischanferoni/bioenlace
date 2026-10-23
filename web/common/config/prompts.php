<?php

/**
 * Mapa clave → archivo YAML de cada prompt de IA (ver {@see \common\components\Platform\Ai\Prompt\PromptRegistry}).
 * Convención: cada módulo guarda sus prompts en su carpeta `Prompts/`.
 */
$components = dirname(__DIR__) . '/components';

return [
    // Platform (agnóstico de dominio)
    'assistant.preprocess' => $components . '/Platform/Assistant/Prompts/preprocess.yaml',
    'assistant.guide' => $components . '/Platform/Assistant/Prompts/guide.yaml',

    // Domain: Clinical
    'clinical.word_correction' => $components . '/Domain/Clinical/Shared/Prompts/medical-word-correction.yaml',
    'clinical.abbreviation_disambiguation' => $components . '/Domain/Clinical/Shared/Prompts/abbreviation-disambiguation.yaml',
    'clinical.text_ia' => $components . '/Domain/Clinical/Shared/Prompts/clinical-text-ia.yaml',
    'clinical.care_pack' => $components . '/Domain/Clinical/CareCohort/Prompts/care-pack.yaml',
    'clinical.appointment_reason_summary' => $components . '/Domain/Clinical/Encounter/Prompts/appointment-reason-summary.yaml',
    'clinical.appointment_reason_insights' => $components . '/Domain/Clinical/Encounter/Prompts/appointment-reason-insights.yaml',
];

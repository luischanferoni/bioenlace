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
    'clinical.capture_extraction' => $components . '/Domain/Clinical/Capture/Prompts/capture-extraction.yaml',
    'clinical.automatic_coding' => $components . '/Domain/Clinical/Encounter/Prompts/automatic-coding.yaml',
    'terminology.snomed_context' => $components . '/Domain/Terminology/Prompts/snomed-context.yaml',

    // Domain: Veterinary
    'veterinary.capture_extraction' => $components . '/Domain/Veterinary/Capture/Prompts/capture-extraction.yaml',

    // Domain: Clinical (el resto)
    'clinical.care_pack' => $components . '/Domain/Clinical/CareCohort/Prompts/care-pack.yaml',
    'clinical.appointment_reason_summary' => $components . '/Domain/Clinical/Encounter/Prompts/appointment-reason-summary.yaml',
    'clinical.appointment_reason_insights' => $components . '/Domain/Clinical/Encounter/Prompts/appointment-reason-insights.yaml',
];

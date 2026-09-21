<?php

namespace common\components\Domain\Clinical\Capture\Application\Pipeline;

/**
 * Soporte interno del pipeline de captura (no entrypoint HTTP).
 *
 * Casos de uso públicos: `CreateOrUploadClinicalCapture`, `TranscribeClinicalCapture`,
 * `AnalyzeClinicalCaptureDraft`, `SaveClinicalCapture`, etc.
 */

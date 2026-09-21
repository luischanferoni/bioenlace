<?php

namespace common\components\Domain\Clinical\Capture\Domain\Port;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureId;

/**
 * Puerto de persistencia del aggregate ClinicalCapture.
 */
interface ClinicalCaptureRepository
{
    public function findById(ClinicalCaptureId $id): ?ClinicalCapture;

    public function findByClientCaptureId(string $clientCaptureId): ?ClinicalCapture;

    /**
     * Captura abierta (no COMPLETED/DISCARDED) por client id o id interno.
     */
    public function findOpenByClientOrId(?string $clientCaptureId, ?int $id): ?ClinicalCapture;

    public function save(ClinicalCapture $capture): void;
}

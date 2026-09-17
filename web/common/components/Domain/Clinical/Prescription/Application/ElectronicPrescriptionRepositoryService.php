<?php

namespace common\components\Domain\Clinical\Prescription\Application;

use common\components\Domain\Clinical\Prescription\Infrastructure\External\Dto\PrescriptionRepositoryRegisterResult;
use common\components\Domain\Clinical\Prescription\Infrastructure\External\Exception\RecetaDigitalRepositoryException;
use common\components\Domain\Clinical\Prescription\Infrastructure\External\RecetaDigitalRepositoryRegistry;
use common\models\Clinical\ElectronicPrescription;
use Yii;

/**
 * Orquesta el envío (o skip) al repositorio nacional tras emitir en Bioenlace.
 */
final class ElectronicPrescriptionRepositoryService
{
    public function syncAfterIssue(ElectronicPrescription $rx, string $fhirBundleJson): PrescriptionRepositoryRegisterResult
    {
        try {
            $connector = RecetaDigitalRepositoryRegistry::get();

            return $connector->registerIssuedPrescription($rx, $fhirBundleJson);
        } catch (RecetaDigitalRepositoryException $e) {
            Yii::warning($e->getMessage(), 'electronic-prescription-repository');

            return PrescriptionRepositoryRegisterResult::failed(
                (string) (Yii::$app->params['recetaDigitalRepository']['default'] ?? 'unknown'),
                $e->getMessage()
            );
        }
    }
}

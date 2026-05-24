<?php

namespace common\components\Clinical\Prescription\Service;

use common\components\Integrations\Prescription\Dto\PrescriptionRepositoryRegisterResult;
use common\components\Integrations\Prescription\Exception\RecetaDigitalRepositoryException;
use common\components\Integrations\Prescription\RecetaDigitalRepositoryRegistry;
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

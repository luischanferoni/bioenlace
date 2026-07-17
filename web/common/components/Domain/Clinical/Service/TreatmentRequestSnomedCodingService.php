<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Terminology\Snomed\CodificadorSnomedIA;
use common\components\Domain\Terminology\Snomed\SnomedCodeSystem;
use common\components\Platform\Core\Product\SnomedTerminologyMetadata;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;
use Yii;

/**
 * Completa códigos SNOMED faltantes en las actividades clínicas del plan.
 *
 * Se ejecuta después del commit de documentación: una indisponibilidad terminológica
 * nunca revierte ni invalida la indicación registrada por el profesional.
 */
final class TreatmentRequestSnomedCodingService
{
    private CodificadorSnomedIA $codificador;

    public function __construct(?CodificadorSnomedIA $codificador = null)
    {
        $this->codificador = $codificador ?? new CodificadorSnomedIA();
    }

    public function codeAndPersistForEncounter(Encounter $encounter): int
    {
        $resources = SnomedTerminologyMetadata::config()['request_coding']['resources'] ?? [];
        if (!is_array($resources)) {
            return 0;
        }

        $saved = 0;
        $medicationConfig = $resources['medication_request'] ?? [];
        if (is_array($medicationConfig) && ($medicationConfig['enabled'] ?? false) === true) {
            $saved += $this->codeMedications(
                (int) $encounter->id,
                (string) ($medicationConfig['snomed_category'] ?? '')
            );
        }

        $serviceConfig = $resources['service_request'] ?? [];
        if (is_array($serviceConfig) && ($serviceConfig['enabled'] ?? false) === true) {
            $allowedCategories = $serviceConfig['allowed_categories'] ?? [];
            $saved += $this->codeServiceRequests(
                (int) $encounter->id,
                (string) ($serviceConfig['snomed_category'] ?? ''),
                is_array($allowedCategories) ? array_values(array_map('strval', $allowedCategories)) : []
            );
        }

        return $saved;
    }

    private function codeMedications(int $encounterId, string $snomedCategory): int
    {
        if ($snomedCategory === '') {
            return 0;
        }

        $rows = MedicationRequest::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->andWhere(['or', ['medication_code' => null], ['medication_code' => '']])
            ->all();

        $saved = 0;
        foreach ($rows as $row) {
            $saved += $this->codeModel(
                trim((string) $row->medication_display),
                $snomedCategory,
                static function (string $conceptId) use ($row): void {
                    $row->updateAttributes([
                        'medication_code' => $conceptId,
                        'medication_code_system' => SnomedCodeSystem::URI,
                    ]);
                }
            );
        }

        return $saved;
    }

    /**
     * @param list<string> $allowedCategories
     */
    private function codeServiceRequests(
        int $encounterId,
        string $snomedCategory,
        array $allowedCategories
    ): int {
        if ($snomedCategory === '' || $allowedCategories === []) {
            return 0;
        }

        $rows = ServiceRequest::find()
            ->where([
                'encounter_id' => $encounterId,
                'deleted_at' => null,
                'category' => $allowedCategories,
            ])
            ->andWhere(['or', ['code' => null], ['code' => '']])
            ->all();

        $saved = 0;
        foreach ($rows as $row) {
            $saved += $this->codeModel(
                trim((string) $row->display),
                $snomedCategory,
                static function (string $conceptId) use ($row): void {
                    $row->updateAttributes([
                        'code' => $conceptId,
                        'code_system' => SnomedCodeSystem::URI,
                    ]);
                }
            );
        }

        return $saved;
    }

    private function codeModel(string $term, string $snomedCategory, callable $persist): int
    {
        if ($term === '') {
            return 0;
        }

        try {
            $coding = $this->codificador->buscarCodigoSnomed($term, $snomedCategory);
            $conceptId = trim((string) ($coding['conceptId'] ?? ''));
            if ($conceptId === '') {
                return 0;
            }

            $persist($conceptId);

            return 1;
        } catch (\Throwable $e) {
            Yii::warning(
                'No se pudo codificar actividad de tratamiento con SNOMED: ' . $e->getMessage(),
                __METHOD__
            );

            return 0;
        }
    }
}

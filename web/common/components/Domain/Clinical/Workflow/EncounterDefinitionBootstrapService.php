<?php

namespace common\components\Domain\Clinical\Workflow;

use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\Servicio;
use Yii;

/**
 * Asegura filas en encounter_definition (p. ej. tras migración FHIR sin backfill de datos).
 */
final class EncounterDefinitionBootstrapService
{
    public function ensureForServiceAndClass(int $serviceId, string $encounterClass): ?EncounterDefinition
    {
        if ($serviceId <= 0 || trim($encounterClass) === '') {
            return null;
        }

        $encounterClass = trim($encounterClass);
        $existing = EncounterDefinition::find()
            ->where(['service_id' => $serviceId, 'encounter_class' => $encounterClass])
            ->andWhere(['deleted_at' => null])
            ->one();
        if ($existing instanceof EncounterDefinition) {
            return $existing;
        }

        $servicio = Servicio::findOne($serviceId);
        $serviceName = $servicio !== null ? (string) $servicio->nombre : '';
        $template = EncounterDefinitionWorkflowCatalog::templateForServiceName($serviceName, $encounterClass);

        $model = new EncounterDefinition();
        $model->service_id = $serviceId;
        $model->encounter_class = $encounterClass;
        $model->workflow_json = EncounterDefinitionWorkflowCatalog::workflowJsonForTemplate($template);
        $model->created_at = date('Y-m-d H:i:s');

        if (!$model->save(false)) {
            Yii::error(
                'No se pudo crear encounter_definition para servicio '
                . $serviceId . ' / ' . $encounterClass,
                __METHOD__
            );

            return null;
        }

        Yii::info(
            'encounter_definition bootstrap: id=' . $model->id
            . ' service_id=' . $serviceId
            . ' class=' . $encounterClass
            . ' template=' . $template,
            'clinical'
        );

        return $model;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function resolveFromCaptureBody(array $body, ?int $subjectPersonaId): ?EncounterDefinition
    {
        $linkedEncounter = $this->findLinkedEncounter($body);
        if (
            $linkedEncounter !== null
            && (int) $linkedEncounter->service_id > 0
            && trim((string) $linkedEncounter->encounter_class) !== ''
        ) {
            return $this->ensureForServiceAndClass(
                (int) $linkedEncounter->service_id,
                (string) $linkedEncounter->encounter_class
            );
        }

        $idServicio = null;
        $encounterClass = null;

        if ($subjectPersonaId !== null && $subjectPersonaId > 0) {
            $paciente = (new \common\components\Domain\Clinical\Service\EncounterLifecycleService())
                ->findSubject($subjectPersonaId);
            if (
                $paciente !== null
                && !empty($body['parent'])
                && !empty($body['parent_id'])
            ) {
                $ctx = \common\components\Domain\Clinical\Service\EncounterCaptureContextService::validarPermisoAtencion(
                    $body['parent'],
                    $body['parent_id'],
                    $paciente
                );
                if (!empty($ctx['success'])) {
                    $idServicio = isset($ctx['idServicio']) ? (int) $ctx['idServicio'] : null;
                    $encounterClass = isset($ctx['encounterClass']) ? (string) $ctx['encounterClass'] : null;
                }
            }
        }

        if ($idServicio === null || $idServicio <= 0 || $encounterClass === null || $encounterClass === '') {
            [, $idServicio, $encounterClass] = ClinicalOperationalContextResolver::resolve($body);
        }

        if ($idServicio === null || $idServicio <= 0 || $encounterClass === null || $encounterClass === '') {
            return null;
        }

        return $this->ensureForServiceAndClass((int) $idServicio, $encounterClass);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function resolveLinkedEncounterFromBody(array $body): ?Encounter
    {
        return $this->findLinkedEncounter($body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function findLinkedEncounter(array $body): ?Encounter
    {
        $idConfiguracion = (int) ($body['id_configuracion'] ?? 0);
        $encounterId = (int) ($body['encounter_id'] ?? $body['id_consulta'] ?? 0);
        if ($encounterId > 0 && $idConfiguracion > 0 && $encounterId === $idConfiguracion) {
            $encounterId = 0;
        }

        $encounter = $encounterId > 0 ? Encounter::findOne($encounterId) : null;

        if ($encounter === null) {
            $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
            $parentId = (int) ($body['parent_id'] ?? 0);
            if ($parent === Encounter::PARENT_TURNO && $parentId > 0) {
                $encounter = Encounter::find()
                    ->where(['appointment_id' => $parentId, 'deleted_at' => null])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
            }
        }

        return $encounter instanceof Encounter ? $encounter : null;
    }
}

<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\components\Domain\Clinical\Service\Authorization\EncounterAccessService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Guía declarativa del chat de motivos de consulta (misma superficie que motivos-consulta/*).
 */
final class EncounterMotivosIntakeService
{
    private EncounterMotivosIntakeCatalogService $catalog;

    public function __construct(?EncounterMotivosIntakeCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new EncounterMotivosIntakeCatalogService();
    }

    public function blocksMotivosChat(Encounter $encounter): bool
    {
        return false;
    }

    public function isCompleted(Encounter $encounter): bool
    {
        return trim((string) ($encounter->motivos_intake_json ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function renderIntake(array $params): array
    {
        if (!$this->catalog->isEnabled()) {
            throw new NotFoundHttpException('Guía de motivos no habilitada.');
        }

        $encounter = $this->resolveEncounter($params);
        $this->assertPatientAccess($encounter, $params);

        $guide = $this->catalog->buildChatGuide($this->reservaTriageCodeForEncounter($encounter));
        if ($guide === null) {
            throw new NotFoundHttpException('Guía de motivos no disponible.');
        }

        return [
            'kind' => 'motivos_chat_guide',
            'encounter_id' => (int) $encounter->id,
            'chat_guide' => $guide,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function submitIntake(array $body): array
    {
        throw new BadRequestHttpException(
            'Las respuestas se envían por el chat de motivos (motivos-consulta/enviar).'
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveEncounter(array $params): Encounter
    {
        $encounterId = isset($params['encounter_id']) ? (int) $params['encounter_id'] : 0;
        $turnoId = isset($params['turno_id']) ? (int) $params['turno_id'] : 0;

        if ($encounterId > 0) {
            $encounter = Encounter::findOne(['id' => $encounterId, 'deleted_at' => null]);
            if ($encounter instanceof Encounter) {
                return $encounter;
            }
            throw new NotFoundHttpException('Encounter no encontrado.');
        }

        if ($turnoId > 0) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => $turnoId])->one();
            if (!$turno instanceof Turno) {
                throw new NotFoundHttpException('Turno no encontrado.');
            }
            $encounter = Encounter::findOne(['appointment_id' => (int) $turno->id_turnos]);
            if ($encounter instanceof Encounter) {
                return $encounter;
            }
            throw new NotFoundHttpException('Encounter no encontrado para el turno.');
        }

        throw new BadRequestHttpException('turno_id o encounter_id es obligatorio.');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assertPatientAccess(Encounter $encounter, array $params): void
    {
        (new PersonRepresentationSubjectService())->resolveAndAuthorize(
            $params,
            RepresentationPermission::CLINICAL_MOTIVOS
        );
        if (!EncounterAccessService::canAccess(
            $encounter,
            'Encounter.access',
            RepresentationPermission::CLINICAL_MOTIVOS
        )) {
            throw new ForbiddenHttpException('No tenés permiso para este encounter.');
        }
    }

    private function reservaTriageCodeForEncounter(Encounter $encounter): string
    {
        $turno = null;
        if ($encounter->appointment_id) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->appointment_id])->one();
        }
        if (!$turno instanceof Turno && $encounter->parent_type === Encounter::PARENT_TURNO && $encounter->parent_id) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->parent_id])->one();
        }

        return $turno instanceof Turno ? trim((string) ($turno->reserva_triage_code ?? '')) : '';
    }
}

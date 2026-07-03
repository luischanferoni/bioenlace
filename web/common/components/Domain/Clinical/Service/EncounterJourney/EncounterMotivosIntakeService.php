<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\components\Domain\Clinical\CareCohort\Presentation\CarePackAssistancePresenter;
use common\components\Domain\Clinical\Service\Authorization\EncounterAccessService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Formulario declarativo previo al chat de motivos de consulta.
 */
final class EncounterMotivosIntakeService
{
    private EncounterMotivosIntakeCatalogService $catalog;
    private CarePackAssistancePresenter $presenter;

    public function __construct(
        ?EncounterMotivosIntakeCatalogService $catalog = null,
        ?CarePackAssistancePresenter $presenter = null
    ) {
        $this->catalog = $catalog ?? new EncounterMotivosIntakeCatalogService();
        $this->presenter = $presenter ?? new CarePackAssistancePresenter();
    }

    public function blocksMotivosChat(Encounter $encounter): bool
    {
        if (!$this->catalog->isEnabled()) {
            return false;
        }
        if ($this->isCompleted($encounter)) {
            return false;
        }

        return true;
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
            throw new NotFoundHttpException('Preguntas previas no habilitadas.');
        }

        $encounter = $this->resolveEncounter($params);
        $this->assertPatientAccess($encounter, $params);

        if ($this->isCompleted($encounter)) {
            return $this->buildSubmittedUi((int) $encounter->id);
        }

        return $this->presenter->buildUiJson(
            $this->catalog->packContent(),
            (int) $encounter->id,
            0
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function submitIntake(array $body): array
    {
        if (!$this->catalog->isEnabled()) {
            throw new NotFoundHttpException('Preguntas previas no habilitadas.');
        }

        $encounter = $this->resolveEncounter($body);
        $this->assertPatientAccess($encounter, $body);

        if ($this->isCompleted($encounter)) {
            return [
                'kind' => 'ui_submit_result',
                'success' => true,
                'data' => [
                    'mensaje' => 'Las respuestas ya estaban registradas.',
                    'encounter_id' => (int) $encounter->id,
                ],
            ];
        }

        $answers = $this->extractAnswers($body);
        $error = $this->validateRequired($answers);
        if ($error !== null) {
            return [
                'kind' => 'ui_submit_result',
                'success' => false,
                'message' => $error,
                'errors' => ['_form' => $error],
            ];
        }

        $encounter->motivos_intake_json = json_encode($answers, JSON_UNESCAPED_UNICODE);
        if (!$encounter->save(false, ['motivos_intake_json'])) {
            return [
                'kind' => 'ui_submit_result',
                'success' => false,
                'message' => 'No se pudieron guardar las respuestas.',
            ];
        }

        (new PersonRepresentationSubjectService())->auditDelegatedAction(
            'motivos_intake',
            (int) $encounter->subject_persona_id,
            ['encounter_id' => (int) $encounter->id]
        );

        return [
            'kind' => 'ui_submit_result',
            'success' => true,
            'data' => [
                'mensaje' => 'Gracias. Ahora podés contarnos tus motivos en el chat.',
                'encounter_id' => (int) $encounter->id,
            ],
        ];
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
        $subjectId = (int) $encounter->subject_persona_id;
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
        unset($subjectId);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function extractAnswers(array $body): array
    {
        $answers = [];
        foreach ($this->catalog->questions() as $q) {
            if (!is_array($q)) {
                continue;
            }
            $id = trim((string) ($q['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            if (array_key_exists($id, $body)) {
                $answers[$id] = $body[$id];
            }
        }

        return $answers;
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function validateRequired(array $answers): ?string
    {
        foreach ($this->catalog->questions() as $q) {
            if (!is_array($q)) {
                continue;
            }
            if (empty($q['required'])) {
                continue;
            }
            $id = trim((string) ($q['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $val = $answers[$id] ?? null;
            if ($val === null || trim((string) $val) === '') {
                $label = trim((string) ($q['label'] ?? $id));

                return 'Completá: ' . $label;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubmittedUi(int $encounterId): array
    {
        return [
            'kind' => 'ui_json',
            'title' => $this->catalog->title(),
            'blocks' => [
                [
                    'kind' => 'message',
                    'id' => 'motivos-intake-done',
                    'title' => 'Listo',
                    'text' => 'Ya registramos tus respuestas. Podés continuar con el chat de motivos.',
                ],
            ],
            'fields' => [
                [
                    'name' => 'encounter_id',
                    'type' => 'hidden',
                    'value' => (string) $encounterId,
                ],
            ],
            'submit' => null,
        ];
    }
}

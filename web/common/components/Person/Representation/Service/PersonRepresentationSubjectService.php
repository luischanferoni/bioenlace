<?php

namespace common\components\Person\Representation\Service;

use common\components\Person\Representation\Enum\RepresentationPermission;
use common\models\Person\PersonRelatedAuditLog;
use common\models\Scheduling\Turno;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Resuelve el sujeto de atención (yo u otro con representación) y autoriza por permiso v1.
 */
final class PersonRepresentationSubjectService
{
    public const SESSION_KEY = 'subjectPersonaPaciente';

    private PersonRepresentationAccessService $accessService;

    public function __construct(?PersonRepresentationAccessService $accessService = null)
    {
        $this->accessService = $accessService ?? new PersonRepresentationAccessService();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function resolveSubjectPersonaId(array $params): int
    {
        $actor = (int) Yii::$app->user->getIdPersona();
        if ($actor <= 0) {
            throw new \InvalidArgumentException('Sesión sin persona.');
        }

        $fromRequest = (int) ($params['subject_persona_id'] ?? $params['id_persona_sujeto'] ?? 0);
        if ($fromRequest > 0) {
            return $fromRequest;
        }

        $fromSession = (int) (Yii::$app->session->get(self::SESSION_KEY) ?? 0);
        if ($fromSession > 0) {
            return $fromSession;
        }

        return $actor;
    }

    public function assertCanAct(int $subjectPersonaId, string $permission): void
    {
        $actor = (int) Yii::$app->user->getIdPersona();
        if ($actor <= 0) {
            throw new ForbiddenHttpException('Sesión sin persona.');
        }
        if ($subjectPersonaId <= 0) {
            throw new ForbiddenHttpException('Sujeto de atención inválido.');
        }
        if ($subjectPersonaId === $actor) {
            return;
        }
        if (!$this->accessService->canAct($actor, $subjectPersonaId, $permission)) {
            throw new ForbiddenHttpException('No tenés permiso para operar por este paciente.');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function resolveAndAuthorize(array $params, string $permission): int
    {
        $subject = $this->resolveSubjectPersonaId($params);
        $this->assertCanAct($subject, $permission);

        return $subject;
    }

    public function assertTurnoAccess(Turno $turno, string $permission): void
    {
        $this->assertCanAct((int) $turno->id_persona, $permission);
    }

    public function assertHasActiveRepresentation(int $subjectPersonaId): void
    {
        $actor = (int) Yii::$app->user->getIdPersona();
        if ($actor <= 0) {
            throw new ForbiddenHttpException('Sesión sin persona.');
        }
        if ($subjectPersonaId === $actor) {
            return;
        }
        foreach (RepresentationPermission::v1Defaults() as $permission) {
            if ($this->accessService->canAct($actor, $subjectPersonaId, $permission)) {
                return;
            }
        }

        throw new ForbiddenHttpException('No tenés representación activa sobre ese paciente.');
    }

    public function establecerSujetoEnSesion(?int $subjectPersonaId): void
    {
        if ($subjectPersonaId === null || $subjectPersonaId <= 0) {
            Yii::$app->session->remove(self::SESSION_KEY);

            return;
        }

        $this->assertHasActiveRepresentation($subjectPersonaId);
        Yii::$app->session->set(self::SESSION_KEY, $subjectPersonaId);
    }

    public function getSujetoEnSesion(): ?int
    {
        $id = (int) (Yii::$app->session->get(self::SESSION_KEY) ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function auditDelegatedAction(string $action, int $subjectPersonaId, array $payload = []): void
    {
        $actor = (int) Yii::$app->user->getIdPersona();
        if ($actor <= 0 || $actor === $subjectPersonaId) {
            return;
        }

        PersonRelatedAuditLog::record(
            $action,
            $actor,
            $subjectPersonaId,
            null,
            Yii::$app->user->id !== null ? (int) Yii::$app->user->id : null,
            $payload
        );

        (new PersonRepresentationDelegatedActionNotifier())->notifyIfEnabled(
            $action,
            $actor,
            $subjectPersonaId,
            $payload
        );
    }
}

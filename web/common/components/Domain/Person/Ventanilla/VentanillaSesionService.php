<?php

namespace common\components\Domain\Person\Ventanilla;

use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Service\PersonaIdentidadResolverService;
use common\models\Person\Persona;
use common\models\Person\VentanillaSesion;
use Yii;

final class VentanillaSesionService
{
    public const SESSION_SUBJECT_KEY = 'ventanillaSubjectPersonaId';

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function iniciar(array $body, int $idEfector): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Hace falta el efector de la sesión operativa.');
        }
        if (!empty($body['identidad_pendiente'])) {
            throw new \InvalidArgumentException('La ventanilla requiere identidad (DNI o Didit), no un NN.');
        }

        $staffUserId = (int) Yii::$app->user->id;
        $staffPersonaId = (int) Yii::$app->user->getIdPersona();
        if ($staffUserId <= 0 || $staffPersonaId <= 0) {
            throw new \InvalidArgumentException('Sesión de staff incompleta.');
        }

        $subjectId = (new PersonaIdentidadResolverService())->resolver($body);
        if ($subjectId === $staffPersonaId) {
            throw new \InvalidArgumentException('La ventanilla es para un paciente, no para tu propia persona.');
        }
        $persona = Persona::findOne($subjectId);
        if ($persona === null || trim((string) ($persona->documento ?? '')) === '') {
            throw new \InvalidArgumentException('La ventanilla requiere un paciente con documento (no un NN).');
        }

        $now = date('Y-m-d H:i:s');
        $this->cerrarAbiertasDeStaff($staffUserId, $now);

        $row = new VentanillaSesion();
        $row->staff_user_id = $staffUserId;
        $row->staff_persona_id = $staffPersonaId;
        $row->subject_persona_id = $subjectId;
        $row->id_efector = $idEfector;
        $row->identity_method = $this->identityMethod($body);
        $row->started_at = $now;
        $row->expires_at = date('Y-m-d H:i:s', time() + VentanillaSesionMetadata::ttlMinutes() * 60);
        $row->created_at = $now;
        if (!$row->save()) {
            throw new \RuntimeException(
                'No se pudo iniciar la ventanilla: ' . json_encode($row->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        $this->writePhpSession($subjectId);

        return $this->serialize($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function estado(): ?array
    {
        $row = $this->sesionAbiertaActual();
        if ($row === null) {
            $this->clearPhpSession();

            return null;
        }

        $this->writePhpSession((int) $row->subject_persona_id);

        return $this->serialize($row);
    }

    public function cerrar(): void
    {
        $staffUserId = (int) Yii::$app->user->id;
        if ($staffUserId > 0) {
            $this->cerrarAbiertasDeStaff($staffUserId, date('Y-m-d H:i:s'));
        }
        $this->clearPhpSession();
    }

    public function sujetoActivo(): ?int
    {
        $row = $this->sesionAbiertaActual();
        if ($row === null) {
            return null;
        }

        return (int) $row->subject_persona_id;
    }

    public function canAct(int $actorPersonaId, int $subjectPersonaId, string $permission): bool
    {
        if ($permission !== RepresentationPermission::SCHEDULING_TURNO) {
            return false;
        }
        if ($actorPersonaId <= 0 || $subjectPersonaId <= 0) {
            return false;
        }
        $row = $this->sesionAbiertaActual();
        if ($row === null) {
            return false;
        }
        if ((int) $row->staff_persona_id !== $actorPersonaId) {
            return false;
        }

        return (int) $row->subject_persona_id === $subjectPersonaId;
    }

    private function sesionAbiertaActual(): ?VentanillaSesion
    {
        $staffUserId = (int) Yii::$app->user->id;
        if ($staffUserId <= 0) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        /** @var VentanillaSesion|null $row */
        $row = VentanillaSesion::find()
            ->where(['staff_user_id' => $staffUserId])
            ->andWhere(['closed_at' => null])
            ->andWhere(['>', 'expires_at', $now])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row;
    }

    private function cerrarAbiertasDeStaff(int $staffUserId, string $now): void
    {
        VentanillaSesion::updateAll(
            ['closed_at' => $now],
            ['staff_user_id' => $staffUserId, 'closed_at' => null]
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function identityMethod(array $body): string
    {
        if (PersonaIdentidadResolverService::pareceIdentidadDidit($body)) {
            return VentanillaSesion::METHOD_DIDIT;
        }
        if (PersonaIdentidadResolverService::pareceIdentidadDni($body)) {
            return VentanillaSesion::METHOD_DNI_LECTOR;
        }

        return VentanillaSesion::METHOD_ID_PERSONA;
    }

    private function writePhpSession(int $subjectId): void
    {
        if (Yii::$app->has('session')) {
            Yii::$app->session->set(self::SESSION_SUBJECT_KEY, $subjectId);
        }
    }

    private function clearPhpSession(): void
    {
        if (Yii::$app->has('session')) {
            Yii::$app->session->remove(self::SESSION_SUBJECT_KEY);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(VentanillaSesion $row): array
    {
        $persona = Persona::findOne((int) $row->subject_persona_id);
        $expiresTs = strtotime((string) $row->expires_at) ?: time();
        $minutos = max(0, (int) ceil(($expiresTs - time()) / 60));

        return [
            'id' => (int) $row->id,
            'subject_persona_id' => (int) $row->subject_persona_id,
            'nombre_completo' => $persona
                ? $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : ('Persona #' . (int) $row->subject_persona_id),
            'documento' => $persona ? (string) ($persona->documento ?? '') : '',
            'identity_method' => (string) $row->identity_method,
            'id_efector' => (int) $row->id_efector,
            'started_at' => (string) $row->started_at,
            'expires_at' => (string) $row->expires_at,
            'minutos_restantes' => $minutos,
        ];
    }
}

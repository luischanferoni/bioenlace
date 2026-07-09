<?php

namespace common\components\Domain\Clinical\Workflow;

use common\models\Clinical\Encounter;
use Yii;

/**
 * PES, servicio y encounter_class desde userPerTabConfig, body o sesión (web/móvil).
 */
final class ClinicalOperationalContextResolver
{
    /**
     * @param array<string, mixed> $body
     * @return array{0: int, 1: int|null, 2: string}
     */
    public static function resolve(array $body): array
    {
        $userPerTabConfig = self::userPerTabConfigFromBody($body);

        $idPesTab = $userPerTabConfig['id_profesional_efector_servicio']
            ?? $userPerTabConfig['idProfesionalEfectorServicio']
            ?? $body['id_profesional_efector_servicio']
            ?? null;
        $idPes = (int) ($idPesTab ?: 0);
        if ($idPes <= 0) {
            $sessionPes = Yii::$app->user->getIdProfesionalEfectorServicio();
            if ($sessionPes !== null && $sessionPes !== '') {
                $idPes = (int) $sessionPes;
            }
        }

        $idServicioRaw = $userPerTabConfig['servicio_actual']
            ?? $userPerTabConfig['servicio_id']
            ?? $body['servicio_actual']
            ?? $body['servicio_id']
            ?? null;
        if ($idServicioRaw === null || $idServicioRaw === '') {
            $sessionSvc = Yii::$app->user->getServicioActual();
            $idServicio = ($sessionSvc !== null && $sessionSvc !== '') ? (int) $sessionSvc : null;
        } else {
            $idServicio = (int) $idServicioRaw;
        }

        if ($idServicio === null || $idServicio <= 0) {
            $fromParent = self::resolveServicioFromParent($body);
            if ($fromParent !== null && $fromParent > 0) {
                $idServicio = $fromParent;
            }
        }

        return [$idPes, $idServicio, self::resolveEncounterClass($body)];
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function resolveEncounterClass(array $body): string
    {
        $userPerTabConfig = self::userPerTabConfigFromBody($body);

        $fromBody = $body['encounter_class']
            ?? $userPerTabConfig['encounter_class']
            ?? $userPerTabConfig['encounterClass']
            ?? null;
        if (is_string($fromBody) && trim($fromBody) !== '') {
            return trim($fromBody);
        }

        $session = Yii::$app->user->getEncounterClass();
        if (is_string($session) && trim($session) !== '') {
            return trim($session);
        }

        $parent = strtoupper(trim((string) ($body['parent'] ?? '')));

        return match ($parent) {
            Encounter::PARENT_GUARDIA, Encounter::PARENT_GENERICO_EMER => Encounter::ENCOUNTER_CLASS_EMER,
            Encounter::PARENT_INTERNACION => Encounter::ENCOUNTER_CLASS_IMP,
            default => Encounter::ENCOUNTER_CLASS_AMB,
        };
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function userPerTabConfigFromBody(array $body): array
    {
        $userPerTabConfig = $body['userPerTabConfig'] ?? [];
        if (is_string($userPerTabConfig)) {
            $decoded = json_decode($userPerTabConfig, true);
            $userPerTabConfig = is_array($decoded) ? $decoded : [];
        }

        return is_array($userPerTabConfig) ? $userPerTabConfig : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function resolveServicioFromParent(array $body): ?int
    {
        $record = self::findParentRecord($body);
        if ($record === null) {
            return null;
        }

        foreach (['id_servicio_asignado', 'id_servicio', 'service_id'] as $attr) {
            if ($record->hasAttribute($attr) && (int) $record->$attr > 0) {
                return (int) $record->$attr;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function resolveSubjectPersonaIdFromParent(array $body): ?int
    {
        $record = self::findParentRecord($body);
        if ($record === null) {
            return null;
        }

        foreach (['id_persona', 'subject_persona_id'] as $attr) {
            if ($record->hasAttribute($attr) && (int) $record->$attr > 0) {
                return (int) $record->$attr;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     * @return \yii\db\ActiveRecord|null
     */
    private static function findParentRecord(array $body)
    {
        $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
        $parentId = (int) ($body['parent_id'] ?? 0);
        if ($parentId <= 0) {
            return null;
        }

        $modelClass = Encounter::PARENT_CLASSES[$parent] ?? null;
        if ($modelClass === null || !class_exists($modelClass)) {
            return null;
        }

        return $modelClass::findOne($parentId);
    }
}

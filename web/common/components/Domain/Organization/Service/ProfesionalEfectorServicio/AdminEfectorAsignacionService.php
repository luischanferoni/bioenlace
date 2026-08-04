<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use common\models\ServiciosEfector;
use Yii;

/**
 * Asignación del servicio de sistema AdminEfector (rol RBAC vía PES).
 *
 * Excepción al glosario «no admin en servicios»: una sola fila canónica con
 * {@see self::ITEM_NAME} tipificada como soporte; no es oferta clínica.
 */
final class AdminEfectorAsignacionService
{
    public const ITEM_NAME = 'AdminEfector';

    public static function findSistemaServicio(): ?Servicio
    {
        /** @var Servicio|null $servicio */
        $servicio = Servicio::find()->where(['item_name' => self::ITEM_NAME])->one();

        return $servicio;
    }

    public static function requireSistemaServicio(): Servicio
    {
        $servicio = self::findSistemaServicio();
        if ($servicio === null) {
            throw new \RuntimeException('Servicio AdminEfector no configurado en el sistema.');
        }

        return $servicio;
    }

    /**
     * Habilita el servicio en el efector (si falta) y crea PES AdminEfector idempotente.
     */
    public static function ensurePersonaEnEfector(
        int $idPersona,
        int $idEfector,
        ?int $actingUserId = null
    ): void {
        if ($idPersona <= 0 || $idEfector <= 0) {
            throw new \InvalidArgumentException('Persona o efector inválidos para AdminEfector.');
        }

        $servicio = self::requireSistemaServicio();
        $idServicio = (int) $servicio->id_servicio;
        $actingUserId = $actingUserId !== null && $actingUserId > 0
            ? $actingUserId
            : (int) (Yii::$app->has('user', true) ? (Yii::$app->user->id ?? 0) : 0);

        $exists = ServiciosEfector::findActive()
            ->where(['id_servicio' => $idServicio, 'id_efector' => $idEfector])
            ->exists();
        if (!$exists) {
            $now = date('Y-m-d H:i:s');
            Yii::$app->db->createCommand()->insert('{{%servicios_efector}}', [
                'id_servicio' => $idServicio,
                'id_efector' => $idEfector,
                'formas_atencion' => ServiciosEfector::DELEGAR_A_CADA_PROFESIONAL,
                'pase_previo' => 0,
                'created_by' => $actingUserId > 0 ? $actingUserId : null,
                'updated_by' => $actingUserId > 0 ? $actingUserId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
            $idPersona,
            $idEfector,
            $idServicio,
            $actingUserId > 0 ? $actingUserId : null
        );
    }

    /**
     * Quita PES AdminEfector activo de la persona en un efector.
     */
    public static function removePersonaEnEfector(int $idPersona, int $idEfector): void
    {
        if ($idPersona <= 0 || $idEfector <= 0) {
            return;
        }
        $servicio = self::findSistemaServicio();
        if ($servicio === null) {
            return;
        }
        $pesAdm = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio(
            $idPersona,
            $idEfector,
            (int) $servicio->id_servicio
        );
        if ($pesAdm !== null) {
            $pesAdm->delete();
        }
    }

    /**
     * Quita PES AdminEfector activos de la persona (todos los efectores).
     */
    public static function removeAllForPersona(int $idPersona): void
    {
        if ($idPersona <= 0) {
            return;
        }
        $servicio = self::findSistemaServicio();
        if ($servicio === null) {
            return;
        }
        $idServ = (int) $servicio->id_servicio;
        foreach (
            ProfesionalEfectorServicio::find()
                ->where([
                    'id_persona' => $idPersona,
                    'id_servicio' => $idServ,
                    'deleted_at' => null,
                ])
                ->all() as $pesAdm
        ) {
            $pesAdm->delete();
        }
    }
}

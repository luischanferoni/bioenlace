<?php

namespace common\models;

use yii\db\Expression;

/**
 * Repositorio para obtener datos puntuales del timeline del paciente.
 * En este caso: último motivo de consulta asociado a los turnos.
 */
class PersonaTimelineRepository
{
    /**
     * Obtiene el último `motivo_consulta` no vacío de la última consulta asociada
     * a los turnos de la persona.
     *
     * @param int $personaId
     * @param int|null $idEfector
     * @return string|null
     */
    public static function getUltimoMotivoConsultaTurno(int $personaId, ?int $idEfector = null): ?string
    {
        if ($personaId <= 0) {
            return null;
        }

        $motivoSubquery = '(SELECT NULLIF(TRIM(c.motivo_consulta), "") 
            FROM consultas c 
            WHERE c.id_turnos = turnos.id_turnos 
              AND c.deleted_at IS NULL 
            ORDER BY c.id_consulta DESC 
            LIMIT 1)';

        // Para evitar el caso: "el turno más reciente no tiene motivo", buscamos un turno
        // para el cual exista al menos una consulta con motivo no vacío.
        $existsExpr = new Expression('EXISTS (
            SELECT 1
            FROM consultas c
            WHERE c.id_turnos = turnos.id_turnos
              AND c.deleted_at IS NULL
              AND NULLIF(TRIM(c.motivo_consulta), "") IS NOT NULL
            LIMIT 1
        )');

        $query = (new \yii\db\Query())
            ->select([
                'motivo' => new Expression($motivoSubquery),
            ])
            // Uniones internas para mantener el mismo universo que en el armado previo del timeline.
            ->from('turnos')
            ->join('LEFT JOIN', 'rrhh_servicio', 'rrhh_servicio.id = turnos.id_rrhh_servicio_asignado')
            ->join('LEFT JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = turnos.id_servicio_asignado')
            ->join('JOIN', 'efectores', 'efectores.id_efector = turnos.id_efector')
            ->join(
                'JOIN',
                'servicios_efector as se',
                'se.id_servicio = turnos.id_servicio_asignado and se.id_efector = turnos.id_efector'
            )
            ->join('LEFT JOIN', 'servicios as pase_prev', 'pase_prev.id_servicio = se.pase_previo')
            ->where(['turnos.id_persona' => $personaId])
            ->andWhere('turnos.deleted_at IS NULL')
            ->andWhere($existsExpr)
            ->orderBy(['turnos.fecha' => SORT_DESC, 'turnos.hora' => SORT_DESC])
            ->limit(1);

        if (!empty($idEfector)) {
            $query->andWhere(['turnos.id_efector' => $idEfector]);
        }

        $row = $query->one();
        $motivo = $row['motivo'] ?? null;
        $motivo = is_string($motivo) ? trim($motivo) : null;

        return !empty($motivo) ? $motivo : null;
    }
}


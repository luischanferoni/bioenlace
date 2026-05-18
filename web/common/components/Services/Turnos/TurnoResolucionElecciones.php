<?php

namespace common\components\Services\Turnos;

use common\models\Turno;
use common\models\TurnoResolucion;
use yii\web\BadRequestHttpException;

/**
 * Opciones de resolución rápida (antes / después / cancelar) tras cambio de agenda.
 */
final class TurnoResolucionElecciones
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function opcionesSelectParaResolucion(TurnoResolucion $res): array
    {
        $out = [];
        $antes = $res->opcion_hora_antes !== null ? substr((string) $res->opcion_hora_antes, 0, 5) : null;
        $despues = $res->opcion_hora_despues !== null ? substr((string) $res->opcion_hora_despues, 0, 5) : null;
        if ($antes !== null && $antes !== '') {
            $out[] = ['value' => 'antes', 'label' => 'Mover al horario anterior (' . $antes . ')'];
        }
        if ($despues !== null && $despues !== '') {
            $out[] = ['value' => 'despues', 'label' => 'Mover al horario siguiente (' . $despues . ')'];
        }
        $out[] = ['value' => 'cancelar', 'label' => 'Cancelar este turno'];

        return $out;
    }

    public static function esEleccionValida(string $eleccion): bool
    {
        return in_array(strtolower(trim($eleccion)), ['antes', 'despues', 'cancelar'], true);
    }

    /**
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    public static function aplicarOpcionesEleccionEnDefinicionUiJson(array $def, TurnoResolucion $res): array
    {
        if (($def['kind'] ?? '') !== 'ui_definition' || ($def['ui_type'] ?? '') !== 'ui_json') {
            return $def;
        }
        $opciones = self::opcionesSelectParaResolucion($res);
        $blocks = isset($def['blocks']) && is_array($def['blocks']) ? $def['blocks'] : [];
        foreach ($blocks as $i => $b) {
            if (!is_array($b) || ($b['kind'] ?? '') !== 'fields') {
                continue;
            }
            $fields = isset($b['fields']) && is_array($b['fields']) ? $b['fields'] : [];
            foreach ($fields as $j => $f) {
                if (!is_array($f) || ($f['name'] ?? '') !== 'eleccion') {
                    continue;
                }
                $f['options'] = $opciones;
                $fields[$j] = $f;
                $b['fields'] = $fields;
                $blocks[$i] = $b;
                $def['blocks'] = $blocks;

                return $def;
            }
        }

        return $def;
    }

    public static function requireResolucionPendienteParaTurno(
        int $idTurno,
        ?int $idPersona = null,
        ?int $idEfector = null
    ): TurnoResolucion {
        if ($idTurno <= 0) {
            throw new BadRequestHttpException('id del turno requerido');
        }
        $query = TurnoResolucion::find()
            ->alias('r')
            ->innerJoin(['t' => Turno::tableName()], 't.id_turnos = r.id_turno')
            ->where([
                'r.id_turno' => $idTurno,
                'r.estado' => TurnoResolucion::ESTADO_PENDIENTE,
                't.estado' => Turno::ESTADO_EN_RESOLUCION,
            ]);
        if ($idPersona !== null && $idPersona > 0) {
            $query->andWhere(['t.id_persona' => $idPersona]);
        }
        if ($idEfector !== null && $idEfector > 0) {
            $query->andWhere(['t.id_efector' => $idEfector]);
        }
        /** @var TurnoResolucion|null $res */
        $res = $query->one();
        if ($res === null) {
            throw new BadRequestHttpException('No hay resolución pendiente para este turno.');
        }

        return $res;
    }
}

<?php

namespace common\components\Services\Turnos;

use common\models\TurnoAgendaConflicto;
use yii\web\BadRequestHttpException;

/**
 * Opciones de resolución cuando un cambio de agenda desalineó un turno pendiente.
 */
final class TurnoAgendaConflictoElecciones
{
  /**
   * @return list<array{value: string, label: string}>
   */
    public static function opcionesSelectParaConflicto(TurnoAgendaConflicto $conf): array
    {
        $out = [];
        $antes = $conf->opcion_hora_antes !== null ? substr((string) $conf->opcion_hora_antes, 0, 5) : null;
        $despues = $conf->opcion_hora_despues !== null ? substr((string) $conf->opcion_hora_despues, 0, 5) : null;
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
    public static function aplicarOpcionesEleccionEnDefinicionUiJson(array $def, TurnoAgendaConflicto $conf): array
    {
        if (($def['kind'] ?? '') !== 'ui_definition' || ($def['ui_type'] ?? '') !== 'ui_json') {
            return $def;
        }
        $opciones = self::opcionesSelectParaConflicto($conf);
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

    public static function requireConflictoPendienteParaTurno(
        int $idTurno,
        ?int $idPersona = null,
        ?int $idEfector = null
    ): TurnoAgendaConflicto {
        if ($idTurno <= 0) {
            throw new BadRequestHttpException('id del turno requerido');
        }
        $query = TurnoAgendaConflicto::find()
            ->alias('c')
            ->innerJoin(['t' => \common\models\Turno::tableName()], 't.id_turnos = c.id_turno')
            ->where([
                'c.id_turno' => $idTurno,
                'c.estado' => TurnoAgendaConflicto::ESTADO_PENDIENTE,
            ]);
        if ($idPersona !== null && $idPersona > 0) {
            $query->andWhere(['t.id_persona' => $idPersona]);
        }
        if ($idEfector !== null && $idEfector > 0) {
            $query->andWhere(['t.id_efector' => $idEfector]);
        }
        /** @var TurnoAgendaConflicto|null $conf */
        $conf = $query->one();
        if ($conf === null) {
            throw new BadRequestHttpException('No hay conflicto de agenda pendiente para este turno.');
        }

        return $conf;
    }
}

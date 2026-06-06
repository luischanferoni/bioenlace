<?php

namespace common\components\Core\Service\Notificaciones;

use common\components\Core\Service\ClientContextService;
use common\models\PersonaNotificacion;
use yii\db\Expression;

/**
 * Bandeja de alertas in-app por persona.
 */
final class PersonaNotificacionService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function registrar(int $idPersona, string $tipo, string $titulo, string $cuerpo, array $data = []): PersonaNotificacion
    {
        return PersonaNotificacion::crear($idPersona, $tipo, $titulo, $cuerpo, $data);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, no_leidas: int}
     */
    public static function listarParaPersona(int $idPersona, bool $soloNoLeidas = false, int $limit = 30, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $q = PersonaNotificacion::find()
            ->where(['id_persona' => $idPersona])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC]);

        if ($soloNoLeidas) {
            $q->andWhere(['leida_at' => null]);
        }

        $omitPaciente = ClientContextService::shouldOmitPacienteRole();
        if ($omitPaciente) {
            $q->andWhere(['not in', 'tipo', ClientContextService::pacienteNotificacionTipos()]);
        }

        $total = (int) (clone $q)->count('*');
        $rows = $q->limit($limit)->offset($offset)->all();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $row->toApiArray();
        }

        $noLeidasQ = PersonaNotificacion::find()
            ->where(['id_persona' => $idPersona, 'leida_at' => null]);
        if ($omitPaciente) {
            $noLeidasQ->andWhere(['not in', 'tipo', ClientContextService::pacienteNotificacionTipos()]);
        }
        $noLeidas = (int) $noLeidasQ->count('*');

        return [
            'items' => $items,
            'total' => $total,
            'no_leidas' => $noLeidas,
        ];
    }

    public static function marcarLeida(int $idPersona, int $idNotificacion): bool
    {
        if ($idNotificacion <= 0) {
            return false;
        }
        $row = PersonaNotificacion::findOne([
            'id' => $idNotificacion,
            'id_persona' => $idPersona,
        ]);
        if ($row === null) {
            return false;
        }
        if ($row->leida_at === null) {
            $row->leida_at = new Expression('NOW()');
            $row->save(false);
        }

        return true;
    }

    public static function marcarTodasLeidas(int $idPersona): int
    {
        return PersonaNotificacion::updateAll(
            ['leida_at' => new Expression('NOW()')],
            ['id_persona' => $idPersona, 'leida_at' => null]
        );
    }
}

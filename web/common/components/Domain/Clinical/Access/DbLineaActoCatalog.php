<?php

namespace common\components\Domain\Clinical\Access;

use common\models\Clinical\ActoClinico;
use common\models\Clinical\LineaActo;
use common\models\Servicio;
use yii\db\Query;

/**
 * Lectura del puente línea ↔ acto desde BD.
 */
final class DbLineaActoCatalog implements LineaActoCatalogInterface
{
    public function lineasForActo(string $code, string $system, ?int $efectorId): array
    {
        $acto = ActoClinico::findOne(['code' => $code, 'code_system' => $system]);
        if ($acto === null) {
            return [];
        }

        $q = (new Query())
            ->from(['la' => LineaActo::tableName()])
            ->innerJoin(['s' => Servicio::tableName()], 's.id_servicio = la.id_servicio')
            ->select(['s.id_servicio', 's.nombre', 'la.preferente', 'la.id_efector'])
            ->where(['la.id_acto' => (int) $acto->id]);
        Servicio::applyOfertaInstitucionalScope($q, 's');

        if ($efectorId !== null && $efectorId > 0) {
            $q->andWhere([
                'or',
                ['la.id_efector' => null],
                ['la.id_efector' => $efectorId],
            ]);
        } else {
            $q->andWhere(['la.id_efector' => null]);
        }

        $rows = $q->all();
        $byLinea = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_servicio'];
            $preferente = (bool) $row['preferente'];
            $scoped = $row['id_efector'] !== null;
            if (!isset($byLinea[$id]) || ($scoped && $preferente) || ($preferente && !$byLinea[$id]['preferente'])) {
                $byLinea[$id] = [
                    'id' => $id,
                    'label' => (string) $row['nombre'],
                    'preferente' => $preferente,
                ];
            }
        }

        return array_values($byLinea);
    }

    public function actosForLinea(int $lineaId, ?int $efectorId): array
    {
        if ($lineaId <= 0) {
            return [];
        }

        $q = (new Query())
            ->from(['la' => LineaActo::tableName()])
            ->innerJoin(['a' => ActoClinico::tableName()], 'a.id = la.id_acto')
            ->select(['a.code', 'a.code_system', 'a.display', 'la.preferente', 'la.id_efector'])
            ->where(['la.id_servicio' => $lineaId]);

        if ($efectorId !== null && $efectorId > 0) {
            $q->andWhere([
                'or',
                ['la.id_efector' => null],
                ['la.id_efector' => $efectorId],
            ]);
        } else {
            $q->andWhere(['la.id_efector' => null]);
        }

        $rows = $q->all();
        $byCode = [];
        foreach ($rows as $row) {
            $key = $row['code_system'] . '|' . $row['code'];
            $preferente = (bool) $row['preferente'];
            $scoped = $row['id_efector'] !== null;
            if (!isset($byCode[$key]) || ($scoped && $preferente) || ($preferente && !$byCode[$key]['preferente'])) {
                $byCode[$key] = [
                    'code' => (string) $row['code'],
                    'system' => (string) $row['code_system'],
                    'display' => (string) $row['display'],
                    'preferente' => $preferente,
                ];
            }
        }

        return array_values($byCode);
    }

    public function findActo(string $code, string $system): ?array
    {
        $acto = ActoClinico::findOne(['code' => $code, 'code_system' => $system]);
        if ($acto === null) {
            return null;
        }

        return [
            'code' => (string) $acto->code,
            'system' => (string) $acto->code_system,
            'display' => (string) $acto->display,
        ];
    }

    public function listActos(): array
    {
        $rows = ActoClinico::find()->orderBy(['display' => SORT_ASC])->all();
        $out = [];
        foreach ($rows as $acto) {
            $out[] = [
                'code' => (string) $acto->code,
                'system' => (string) $acto->code_system,
                'display' => (string) $acto->display,
            ];
        }

        return $out;
    }
}

<?php

namespace common\components\Domain\Organization\Service\ProfesionalCobertura;

use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\models\ProfesionalCobertura;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use yii\db\ActiveQuery;

/**
 * CRUD y serialización de coberturas EMER/IMP (roster entrada/salida).
 */
final class ProfesionalCoberturaService
{
    /**
     * @param array<string, mixed> $params
     */
    public static function queryListado(array $params): ActiveQuery
    {
        $q = ProfesionalCobertura::find()->alias('c')->andWhere(['c.deleted_at' => null]);

        if (!empty($params['id_efector'])) {
            $q->andWhere(['c.id_efector' => (int) $params['id_efector']]);
        }
        if (!empty($params['id_persona'])) {
            $q->andWhere(['c.id_persona' => (int) $params['id_persona']]);
        }
        if (!empty($params['encounter_class'])) {
            $q->andWhere(['c.encounter_class' => (string) $params['encounter_class']]);
        }
        if (!empty($params['id_servicio'])) {
            $q->andWhere(['c.id_servicio' => (int) $params['id_servicio']]);
        }
        if (!empty($params['desde'])) {
            $q->andWhere(['>=', 'c.fin', (string) $params['desde']]);
        }
        if (!empty($params['hasta'])) {
            $q->andWhere(['<=', 'c.inicio', (string) $params['hasta']]);
        }

        return $q->orderBy(['c.inicio' => SORT_ASC, 'c.id' => SORT_ASC]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool, model?:ProfesionalCobertura, errors?:array<string, mixed>, conflicts?:list<array<string, mixed>>}
     */
    public static function crear(array $data): array
    {
        $model = new ProfesionalCobertura();
        self::applyPayload($model, $data);
        $conflicts = self::detectConflicts($model);
        if ($conflicts !== []) {
            return [
                'ok' => false,
                'errors' => ['_conflicto' => ['Hay conflictos de cobertura o con agenda ambulatoria.']],
                'conflicts' => $conflicts,
            ];
        }
        if (!$model->validate()) {
            return ['ok' => false, 'errors' => $model->errors];
        }
        if (!$model->save(false)) {
            return ['ok' => false, 'errors' => $model->errors];
        }

        return ['ok' => true, 'model' => $model];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool, model?:ProfesionalCobertura, errors?:array<string, mixed>, conflicts?:list<array<string, mixed>>}
     */
    public static function actualizar(ProfesionalCobertura $model, array $data): array
    {
        self::applyPayload($model, $data, true);
        $conflicts = self::detectConflicts($model);
        if ($conflicts !== []) {
            return [
                'ok' => false,
                'errors' => ['_conflicto' => ['Hay conflictos de cobertura o con agenda ambulatoria.']],
                'conflicts' => $conflicts,
            ];
        }
        if (!$model->validate()) {
            return ['ok' => false, 'errors' => $model->errors];
        }
        if (!$model->save(false)) {
            return ['ok' => false, 'errors' => $model->errors];
        }

        return ['ok' => true, 'model' => $model];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function detectConflicts(ProfesionalCobertura $model): array
    {
        $out = [];
        if (AgendaByEncounterClassMetadata::coberturaOverlapSamePersonaEfector()) {
            $rows = ProfesionalCobertura::findSolapes(
                (int) $model->id_persona,
                (int) $model->id_efector,
                (string) $model->inicio,
                (string) $model->fin,
                $model->isNewRecord ? null : (int) $model->id
            );
            foreach ($rows as $row) {
                $out[] = array_merge(self::toApiArray($row), ['kind' => 'cobertura_overlap']);
            }
        }

        foreach (ProfesionalCoberturaActivaService::detectAmbSlotConflicts($model) as $amb) {
            $out[] = $amb;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function applyPayload(ProfesionalCobertura $model, array $data, bool $isUpdate = false): void
    {
        $allowed = [
            'id_persona',
            'id_efector',
            'id_servicio',
            'id_profesional_efector_servicio',
            'encounter_class',
            'inicio',
            'fin',
            'rol',
            'notas',
        ];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $val = $data[$key];
            if (in_array($key, ['id_persona', 'id_efector', 'id_servicio', 'id_profesional_efector_servicio'], true)) {
                if ($val === '' || $val === null) {
                    $model->$key = null;
                } else {
                    $model->$key = (int) $val;
                }
                continue;
            }
            $model->$key = is_string($val) ? trim($val) : $val;
        }

        if (!empty($model->id_profesional_efector_servicio) && (int) $model->id_profesional_efector_servicio > 0) {
            $pes = ProfesionalEfectorServicio::findOne([
                'id' => (int) $model->id_profesional_efector_servicio,
                'deleted_at' => null,
            ]);
            if ($pes !== null) {
                if (empty($model->id_persona)) {
                    $model->id_persona = (int) $pes->id_persona;
                }
                if (empty($model->id_efector)) {
                    $model->id_efector = (int) $pes->id_efector;
                }
                if ($model->id_servicio === null || $model->id_servicio === '') {
                    $model->id_servicio = (int) $pes->id_servicio;
                }
            }
        }

        if ($isUpdate) {
            // no-op: caller locks identity fields if needed
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function toApiArray(ProfesionalCobertura $model): array
    {
        $row = $model->toArray([
            'id',
            'id_persona',
            'id_efector',
            'id_servicio',
            'id_profesional_efector_servicio',
            'encounter_class',
            'inicio',
            'fin',
            'rol',
            'notas',
            'created_at',
            'updated_at',
        ]);

        $svc = $model->servicio;
        if ($svc instanceof Servicio) {
            $row['servicio'] = ['id' => (int) $svc->id_servicio, 'nombre' => (string) $svc->nombre];
        }

        return $row;
    }
}

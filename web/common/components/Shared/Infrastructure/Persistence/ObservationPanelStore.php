<?php

namespace common\components\Shared\Infrastructure\Persistence;

use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;

/**
 * Persistencia de paneles clínicos: un valor por observación, agrupado por panel.
 *
 * Compartido por las especialidades que registran paneles (control prenatal, sesión
 * de salud mental). Evita que cada servicio reimplemente el mismo par
 * observación + vínculo de panel.
 */
final class ObservationPanelStore
{
    public const TABLE = '{{%observation_panel_ext}}';

    public const KIND_OBSTETRICS = 'obstetrics';
    public const KIND_MENTAL_HEALTH = 'mental-health';

    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_TEXT = 'text';
    public const TYPE_DATE = 'date';

    /**
     * Escribe un panel completo.
     *
     * @param list<array{field: string, code: string, system: string, type: string, unit: ?string, value: string}> $rows
     * @return array{panelId: int, created: int}
     */
    public function write(string $panelKind, Encounter $encounter, array $rows, string $category): array
    {
        if ($rows === []) {
            return ['panelId' => 0, 'created' => 0];
        }

        $panelId = $this->nextPanelId($panelKind);
        $effective = date('Y-m-d H:i:s');
        $created = 0;

        foreach ($rows as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $observation = new Observation();
            $observation->encounter_id = $encounter->id;
            $observation->subject_persona_id = $encounter->subject_persona_id;
            $observation->status = 'final';
            $observation->category = $category;
            $observation->code = (string) $row['code'];
            $observation->code_system = (string) $row['system'] !== '' ? (string) $row['system'] : null;
            $observation->effective_datetime = $effective;

            if ($row['type'] === self::TYPE_NUMERIC && is_numeric($value)) {
                $observation->value_quantity = $value;
                $observation->value_unit = $row['unit'] ?? null;
            } else {
                $observation->value_string = $value;
            }

            if (!$observation->save()) {
                throw new \RuntimeException(
                    'Observation panel ' . $panelKind . ': ' . json_encode($observation->getErrors())
                );
            }

            Observation::getDb()->createCommand()->insert(self::TABLE, [
                'observation_id' => (int) $observation->id,
                'panel_kind' => $panelKind,
                'panel_id' => $panelId,
                'field_key' => (string) $row['field'],
            ])->execute();

            $created++;
        }

        return ['panelId' => $panelId, 'created' => $created];
    }

    /**
     * Observaciones de un panel del paciente, sin agrupar.
     *
     * @return list<array<string, mixed>>
     */
    public function rowsForPerson(int $personaId, string $panelKind, string $category): array
    {
        if (Observation::getDb()->schema->getTableSchema(self::TABLE, true) === null) {
            return [];
        }

        return Observation::find()
            ->alias('o')
            ->innerJoin(['e' => self::TABLE], 'e.observation_id = o.id')
            ->select([
                'o.id',
                'o.encounter_id',
                'o.code',
                'o.value_quantity',
                'o.value_string',
                'o.value_unit',
                'o.effective_datetime',
                'e.panel_id',
                'e.field_key',
            ])
            ->where([
                'o.subject_persona_id' => $personaId,
                'o.category' => $category,
                'o.deleted_at' => null,
                'e.panel_kind' => $panelKind,
            ])
            ->andWhere(['not', ['o.status' => 'entered-in-error']])
            ->orderBy(['o.id' => SORT_ASC])
            ->asArray()
            ->all();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function valueFromRow(array $row): string
    {
        $quantity = $row['value_quantity'] ?? null;
        if ($quantity !== null && $quantity !== '') {
            return rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.');
        }

        return trim((string) ($row['value_string'] ?? ''));
    }

    private function nextPanelId(string $panelKind): int
    {
        $max = (int) Observation::getDb()
            ->createCommand(
                'SELECT COALESCE(MAX(panel_id), 0) FROM ' . self::TABLE . ' WHERE panel_kind = :kind',
                [':kind' => $panelKind]
            )
            ->queryScalar();

        return $max + 1;
    }
}

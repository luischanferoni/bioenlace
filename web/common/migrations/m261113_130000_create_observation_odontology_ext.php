<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Estado dental como observación consultable.
 *
 * Crea la extensión 1:1 de `observation` que ubica cada estado en una pieza y sus
 * caras, y migra los estados que hoy viven como nota `odontology_state:` de una
 * condición.
 */
class m261113_130000_create_observation_odontology_ext extends Migration
{
    private const TABLE = '{{%observation_odontology_ext}}';
    private const LEGACY_SOURCE = 'legacy-odontology-state';
    private const NOTE_PREFIX = 'odontology_state:';
    private const SNOMED = 'http://snomed.info/sct';

    public function safeUp(): void
    {
        $this->createExtTable();
        $this->backfillFromConditionNotes();
    }

    public function safeDown(): void
    {
        // Borra sólo lo que esta migración creó: las observaciones migradas (cuyo
        // origen sigue intacto en la condición) y la tabla de extensión.
        $this->delete('{{%observation}}', ['source_system' => self::LEGACY_SOURCE]);

        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }

    private function createExtTable(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $observation = $this->db->schema->getTableSchema('{{%observation}}', true);
        $idColumn = ($observation !== null && isset($observation->columns['id']))
            ? $this->integer()->notNull()->comment('observation.id')
            : $this->integer()->notNull();

        $this->createTable(self::TABLE, [
            'observation_id' => $idColumn,
            'tooth_number' => $this->string(8)->null()->comment('Notación FDI'),
            'surfaces' => $this->string(32)->null()->comment('M,O,D,V,L,P'),
            'time_qualifier' => $this->string(16)->null()->comment('PASADA|PRESENTE|FUTURA'),
        ]);

        $this->addPrimaryKey('pk_observation_odontology_ext', self::TABLE, 'observation_id');

        if ($observation !== null) {
            $this->addForeignKey(
                'fk_observation_odonto_observation',
                self::TABLE,
                'observation_id',
                '{{%observation}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    /**
     * Copia cada condición con nota de estado dental a una observación + extensión.
     * Idempotente: la observación migrada conserva el id de su condición en
     * `source_system`/`external_id`, así una segunda corrida no duplica.
     */
    private function backfillFromConditionNotes(): void
    {
        $conditions = (new Query())
            ->select([
                'id',
                'encounter_id',
                'subject_persona_id',
                'code',
                'display',
                'recorded_date',
                'created_at',
                'note',
            ])
            ->from(['c' => '{{%clinical_condition}}'])
            ->where('c.note LIKE :prefix', [':prefix' => self::NOTE_PREFIX . '%'])
            ->andWhere(['c.deleted_at' => null])
            ->orderBy(['c.id' => SORT_ASC])
            ->all($this->db);

        foreach ($conditions as $condition) {
            $meta = $this->parseStateNote((string) $condition['note']);
            if ($meta === null) {
                continue;
            }

            $codigo = trim((string) ($meta['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }

            $externalId = (string) $condition['id'];
            if ($this->alreadyMigrated($externalId)) {
                continue;
            }

            $this->insert('{{%observation}}', [
                'encounter_id' => $condition['encounter_id'],
                'subject_persona_id' => $condition['subject_persona_id'],
                'status' => 'final',
                'category' => 'odontology',
                'code' => $codigo,
                'code_system' => self::SNOMED,
                'value_string' => $condition['display'],
                'value_json' => json_encode([
                    'pieza' => $meta['pieza'] ?? null,
                    'caras' => $meta['caras'] ?? null,
                    'tipo' => $meta['tipo'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'effective_datetime' => $condition['recorded_date'] ?: $condition['created_at'],
                'source_system' => self::LEGACY_SOURCE,
                'external_id' => $externalId,
                'created_at' => $condition['created_at'],
            ]);

            $this->insert(self::TABLE, [
                'observation_id' => (int) $this->db->getLastInsertID(),
                'tooth_number' => $this->trimToNull($meta['pieza'] ?? null, 8),
                'surfaces' => $this->trimToNull($meta['caras'] ?? null, 32),
                'time_qualifier' => null,
            ]);
        }
    }

    private function alreadyMigrated(string $externalId): bool
    {
        return (new Query())
            ->from('{{%observation}}')
            ->where(['source_system' => self::LEGACY_SOURCE, 'external_id' => $externalId])
            ->exists($this->db);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseStateNote(string $note): ?array
    {
        if (strpos($note, self::NOTE_PREFIX) !== 0) {
            return null;
        }
        $decoded = json_decode(substr($note, strlen(self::NOTE_PREFIX)), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function trimToNull($value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }
}

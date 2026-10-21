<?php

use yii\db\Expression;
use yii\db\Migration;

/**
 * Renames the diagnosis capture key in persisted workflows and captures.
 */
class m261021_280000_rename_encounter_diagnosis_model_key extends Migration
{
    private const KEY_RENAMES = [
        '"DiagnosticoConsulta"' => '"EncounterDiagnosis"',
    ];

    public function safeUp(): void
    {
        $this->replacePersistedKeys(self::KEY_RENAMES);
    }

    public function safeDown(): void
    {
        $this->replacePersistedKeys(array_flip(self::KEY_RENAMES));
    }

    /**
     * @param array<string, string> $renames
     */
    private function replacePersistedKeys(array $renames): void
    {
        $columnsByTable = [
            '{{%encounter_definition}}' => ['workflow_json'],
            '{{%encounter_capture}}' => ['datos_extraidos_json', 'analysis_response_json'],
            '{{%encounter_capture_analysis}}' => ['datos_extraidos_json'],
        ];

        foreach ($columnsByTable as $table => $columns) {
            $schema = $this->db->schema->getTableSchema($table, true);
            if ($schema === null) {
                continue;
            }

            foreach ($columns as $column) {
                if (!isset($schema->columns[$column])) {
                    continue;
                }

                foreach ($renames as $oldKey => $newKey) {
                    $this->update(
                        $table,
                        [$column => new Expression(
                            'REPLACE([[' . $column . ']], :oldKey, :newKey)',
                            [
                                ':oldKey' => $oldKey,
                                ':newKey' => $newKey,
                            ]
                        )],
                        ['like', $column, $oldKey]
                    );
                }
            }
        }
    }
}

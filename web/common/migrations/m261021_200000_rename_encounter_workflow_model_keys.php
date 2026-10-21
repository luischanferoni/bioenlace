<?php

use yii\db\Migration;

/**
 * Renames capture model keys in persisted encounter workflows.
 */
class m261021_200000_rename_encounter_workflow_model_keys extends Migration
{
    private const KEY_RENAMES = [
        '"ConsultaAtencionesEnfermeria"' => '"EncounterNursingCare"',
        '"ConsultaDerivaciones"' => '"EncounterReferralRequest"',
    ];

    public function safeUp(): void
    {
        $this->replaceWorkflowKeys(self::KEY_RENAMES);
    }

    public function safeDown(): void
    {
        $this->replaceWorkflowKeys(array_flip(self::KEY_RENAMES));
    }

    /**
     * @param array<string, string> $renames
     */
    private function replaceWorkflowKeys(array $renames): void
    {
        $table = '{{%encounter_definition}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        foreach ($renames as $oldKey => $newKey) {
            $this->update(
                $table,
                ['workflow_json' => new \yii\db\Expression(
                    'REPLACE([[workflow_json]], :oldKey, :newKey)',
                    [':oldKey' => $oldKey, ':newKey' => $newKey]
                )],
                ['like', 'workflow_json', $oldKey]
            );
        }
    }
}

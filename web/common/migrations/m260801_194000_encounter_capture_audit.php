<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\Clinical\EncounterCaptureAudit;
use yii\db\Migration;

/**
 * Trail de auditoría del pipeline de captura clínica.
 *
 * @see web/docs/plans/auditoria-captura-clinica/design.md
 */
class m260801_194000_encounter_capture_audit extends Migration
{
    private string $table = '{{%encounter_capture_audit}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema($this->table, true) !== null) {
            return;
        }

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),
            'capture_id' => $this->integer()->notNull(),
            'encounter_id' => $this->integer()->null(),
            'actor_user_id' => $this->integer()->null(),
            'event_type' => MigrationEnumColumn::mysqlEnum(
                EncounterCaptureAudit::eventTypeValues(),
                EncounterCaptureAudit::EVENT_UPLOADED,
                true,
                implode('|', EncounterCaptureAudit::eventTypeValues())
            ),
            'meta_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('ix_eca_capture_created', $this->table, ['capture_id', 'created_at']);
        $this->createIndex('ix_eca_event_created', $this->table, ['event_type', 'created_at']);
        $this->createIndex('ix_eca_encounter', $this->table, 'encounter_id');
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema($this->table, true) !== null) {
            $this->dropTable($this->table);
        }
    }
}

<?php

use yii\db\Migration;

/**
 * Informes de lab con vínculo a encounter ambiguo (agente E01).
 */
class m260704_130000_diagnostic_report_encounter_link_pending extends Migration
{
    public function safeUp()
    {
        $table = '{{%diagnostic_report_encounter_link_pending}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable($table, [
            'diagnostic_report_id' => $this->integer()->notNull(),
            'candidates_json' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ], $opts);

        $this->addPrimaryKey('pk_dr_encounter_link_pending', $table, 'diagnostic_report_id');
        $this->addForeignKey(
            'fk_dr_link_pending_report',
            $table,
            'diagnostic_report_id',
            '{{%diagnostic_report}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $table = '{{%diagnostic_report_encounter_link_pending}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $this->dropForeignKey('fk_dr_link_pending_report', $table);
        $this->dropTable($table);
    }
}

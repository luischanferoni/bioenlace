<?php

use yii\db\Migration;

/**
 * Preferencia del paciente: usar extracto de HC en el asistente conversacional.
 */
class m260818_120000_persona_asistente_preferencias extends Migration
{
    public function safeUp()
    {
        $table = '{{%persona_asistente_preferencias}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable($table, [
            'id_persona' => $this->integer()->notNull(),
            'usa_resumen_hc_en_asistente' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opts);

        $this->addPrimaryKey('pk_persona_asistente_preferencias', $table, 'id_persona');
        $this->addForeignKey(
            'fk_persona_asistente_pref_persona',
            $table,
            'id_persona',
            '{{%personas}}',
            'id_persona',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $table = '{{%persona_asistente_preferencias}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        $this->dropForeignKey('fk_persona_asistente_pref_persona', $table);
        $this->dropTable($table);
    }
}

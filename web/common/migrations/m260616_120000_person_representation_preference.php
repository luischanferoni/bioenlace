<?php

use yii\db\Migration;

/**
 * Preferencias de representación (notificación al paciente — decisión N9).
 */
class m260616_120000_person_representation_preference extends Migration
{
    public function safeUp()
    {
        $table = '{{%person_representation_pref}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id_persona' => $this->integer()->notNull(),
            'notify_on_representative_action' => $this->boolean()->notNull()->defaultValue(false),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->addPrimaryKey('pk_person_representation_pref', $table, 'id_persona');
        $this->addForeignKey(
            'fk_person_representation_pref_persona',
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
        $table = '{{%person_representation_pref}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        $this->dropForeignKey('fk_person_representation_pref_persona', $table);
        $this->dropTable($table);
    }
}

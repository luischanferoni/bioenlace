<?php

use yii\db\Migration;

/**
 * Preferencias estructuradas de agenda del paciente (agente A01 auto-reserva D2).
 */
class m260704_120000_persona_agenda_preferencias extends Migration
{
    public function safeUp()
    {
        $table = '{{%persona_agenda_preferencias}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable($table, [
            'id_persona' => $this->integer()->notNull(),
            'auto_reserva_resolucion' => $this->boolean()->notNull()->defaultValue(false),
            'franjas_json' => $this->text()->null(),
            'dias_semana_json' => $this->text()->null(),
            'tipo_atencion_preferido' => $this->string(20)->null(),
            'mismo_pes_prioritario' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opts);

        $this->addPrimaryKey('pk_persona_agenda_preferencias', $table, 'id_persona');
        $this->addForeignKey(
            'fk_persona_agenda_pref_persona',
            $table,
            'id_persona',
            '{{%personas}}',
            'id_persona',
            'CASCADE',
            'CASCADE'
        );

        $cfg = $this->db->schema->getTableSchema('{{%efector_turnos_config}}', true);
        if ($cfg !== null && !isset($cfg->columns['auto_reserva_resolucion_habilitada'])) {
            $this->addColumn(
                '{{%efector_turnos_config}}',
                'auto_reserva_resolucion_habilitada',
                $this->boolean()->notNull()->defaultValue(false)
            );
        }
    }

    public function safeDown()
    {
        $table = '{{%persona_agenda_preferencias}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            $this->dropForeignKey('fk_persona_agenda_pref_persona', $table);
            $this->dropTable($table);
        }

        $cfg = $this->db->schema->getTableSchema('{{%efector_turnos_config}}', true);
        if ($cfg !== null && isset($cfg->columns['auto_reserva_resolucion_habilitada'])) {
            $this->dropColumn('{{%efector_turnos_config}}', 'auto_reserva_resolucion_habilitada');
        }
    }
}

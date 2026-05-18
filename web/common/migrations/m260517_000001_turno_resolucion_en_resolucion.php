<?php

use yii\db\Migration;

/**
 * Reemplaza turno_agenda_conflicto por turno_resolucion y habilita Turno::ESTADO_EN_RESOLUCION.
 */
class m260517_000001_turno_resolucion_en_resolucion extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%turno_agenda_conflicto}}', true) !== null) {
            foreach (['fk_turno_agenda_conflicto_turno', 'fk_turno_agenda_conflicto_version'] as $fk) {
                try {
                    $this->dropForeignKey($fk, '{{%turno_agenda_conflicto}}');
                } catch (\Throwable $e) {
                }
            }
            $this->dropTable('{{%turno_agenda_conflicto}}');
        }

        $this->createTable('{{%turno_resolucion}}', [
            'id' => $this->primaryKey(),
            'id_turno' => $this->integer()->notNull(),
            'origen' => $this->string(32)->notNull(),
            'id_agenda_version' => $this->integer()->null(),
            'estado' => $this->string(24)->notNull()->defaultValue('pendiente'),
            'razon_codigo' => $this->string(64)->null(),
            'opcion_hora_antes' => $this->time()->null(),
            'opcion_hora_despues' => $this->time()->null(),
            'hora_elegida' => $this->time()->null(),
            'permitir_otro_efector' => $this->boolean()->notNull()->defaultValue(true),
            'permitir_otro_pes' => $this->boolean()->notNull()->defaultValue(true),
            'meta_json' => $this->json()->null(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('ix_turno_resolucion_turno', '{{%turno_resolucion}}', ['id_turno']);
        $this->createIndex('ix_turno_resolucion_turno_estado', '{{%turno_resolucion}}', ['id_turno', 'estado']);
        $this->createIndex('ix_turno_resolucion_estado', '{{%turno_resolucion}}', ['estado']);

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos !== null && isset($turnos->columns['id_turnos'])) {
            $this->addForeignKey(
                'fk_turno_resolucion_turno',
                '{{%turno_resolucion}}',
                'id_turno',
                '{{%turnos}}',
                'id_turnos',
                'CASCADE',
                'RESTRICT'
            );
        }

        $versionTable = $this->db->schema->getTableSchema('{{%profesional_efector_servicio_agenda_version}}', true);
        if ($versionTable !== null) {
            $this->addForeignKey(
                'fk_turno_resolucion_agenda_version',
                '{{%turno_resolucion}}',
                'id_agenda_version',
                '{{%profesional_efector_servicio_agenda_version}}',
                'id',
                'SET NULL',
                'RESTRICT'
            );
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%turno_resolucion}}');
    }
}

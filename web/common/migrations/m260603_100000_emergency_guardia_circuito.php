<?php

use yii\db\Migration;

/**
 * Urgencias: circuito operativo, triage estructurado y eventos de auditoría.
 */
class m260603_100000_emergency_guardia_circuito extends Migration
{
    public function safeUp()
    {
        $guardia = '{{%guardia}}';
        $schema = $this->db->schema->getTableSchema($guardia, true);
        if ($schema !== null) {
            if ($schema->getColumn('circuito_estado') === null) {
                $this->addColumn($guardia, 'circuito_estado', $this->string(32)->null()->after('estado'));
            }
            if ($schema->getColumn('prioridad_triage') === null) {
                $this->addColumn($guardia, 'prioridad_triage', $this->tinyInteger()->unsigned()->null()->after('circuito_estado'));
            }
            if ($schema->getColumn('ingreso_at') === null) {
                $this->addColumn($guardia, 'ingreso_at', $this->dateTime()->null()->after('prioridad_triage'));
            }
            $this->createIndex('ix_guardia_efector_circuito', $guardia, ['id_efector', 'circuito_estado', 'estado']);
        }

        $triage = '{{%guardia_triage}}';
        if ($this->db->schema->getTableSchema($triage, true) === null) {
            $this->createTable($triage, [
                'id' => $this->primaryKey(),
                'guardia_id' => $this->integer()->notNull(),
                'scale' => $this->string(32)->notNull()->defaultValue('manchester'),
                'level' => $this->tinyInteger()->unsigned()->notNull(),
                'reason_code' => $this->string(64)->null(),
                'reason_text' => $this->string(500)->notNull(),
                'vitals_json' => $this->text()->null(),
                'triaged_at' => $this->dateTime()->notNull(),
                'id_profesional_efector_servicio' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_guardia_triage_guardia', $triage, 'guardia_id', true);
            $this->addForeignKey(
                'fk_guardia_triage_guardia',
                $triage,
                'guardia_id',
                $guardia,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $events = '{{%guardia_circuito_event}}';
        if ($this->db->schema->getTableSchema($events, true) === null) {
            $this->createTable($events, [
                'id' => $this->primaryKey(),
                'guardia_id' => $this->integer()->notNull(),
                'tipo' => $this->string(32)->notNull(),
                'occurred_at' => $this->dateTime()->notNull(),
                'id_profesional_efector_servicio' => $this->integer()->null(),
                'payload_json' => $this->text()->null(),
                'created_by' => $this->integer()->null(),
            ]);
            $this->createIndex('ix_guardia_circuito_event_guardia', $events, ['guardia_id', 'occurred_at']);
            $this->addForeignKey(
                'fk_guardia_circuito_event_guardia',
                $events,
                'guardia_id',
                $guardia,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        // Backfill circuito para guardias activas legacy
        if ($schema !== null) {
            $this->db->createCommand(
                "UPDATE {$guardia} SET ingreso_at = COALESCE(ingreso_at, CONCAT(fecha, ' ', COALESCE(hora, '00:00:00'))) WHERE ingreso_at IS NULL"
            )->execute();
            $this->db->createCommand(
                "UPDATE {$guardia} SET circuito_estado = 'finalizado' WHERE estado = 'finalizada' AND (circuito_estado IS NULL OR circuito_estado = '')"
            )->execute();
            $this->db->createCommand(
                "UPDATE {$guardia} SET circuito_estado = 'atendido' WHERE estado = 'atendida' AND (circuito_estado IS NULL OR circuito_estado = '')"
            )->execute();
            $this->db->createCommand(
                "UPDATE {$guardia} SET circuito_estado = 'espera_triage' WHERE estado = 'pendiente' AND (circuito_estado IS NULL OR circuito_estado = '')"
            )->execute();
        }
    }

    public function safeDown()
    {
        $events = '{{%guardia_circuito_event}}';
        if ($this->db->schema->getTableSchema($events, true) !== null) {
            $this->dropTable($events);
        }
        $triage = '{{%guardia_triage}}';
        if ($this->db->schema->getTableSchema($triage, true) !== null) {
            $this->dropTable($triage);
        }
        $guardia = '{{%guardia}}';
        $schema = $this->db->schema->getTableSchema($guardia, true);
        if ($schema !== null) {
            if ($schema->getColumn('ingreso_at') !== null) {
                $this->dropColumn($guardia, 'ingreso_at');
            }
            if ($schema->getColumn('prioridad_triage') !== null) {
                $this->dropColumn($guardia, 'prioridad_triage');
            }
            if ($schema->getColumn('circuito_estado') !== null) {
                $this->dropColumn($guardia, 'circuito_estado');
            }
        }
    }
}

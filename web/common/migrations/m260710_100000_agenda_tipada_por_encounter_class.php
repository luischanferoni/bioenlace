<?php

use yii\db\Migration;

/**
 * Agenda tipada por encounter_class:
 * - AMB: columna en agenda/versión de cupos (única expuesta a pacientes).
 * - EMER/IMP: tabla profesional_cobertura (roster entrada/salida).
 */
class m260710_100000_agenda_tipada_por_encounter_class extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $this->addEncounterClassToAgendaTables();
        $this->createCoberturaTable();
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $cobertura = '{{%profesional_cobertura}}';
        if ($this->db->schema->getTableSchema($cobertura, true) !== null) {
            $this->dropTable($cobertura);
        }

        $this->dropEncounterClassColumn('{{%profesional_efector_servicio_agenda_version}}');
        $this->dropEncounterClassColumn('{{%profesional_efector_servicio_agenda}}');
    }

    private function addEncounterClassToAgendaTables(): void
    {
        foreach (['{{%profesional_efector_servicio_agenda}}', '{{%profesional_efector_servicio_agenda_version}}'] as $table) {
            $schema = $this->db->schema->getTableSchema($table, true);
            if ($schema === null || isset($schema->columns['encounter_class'])) {
                continue;
            }
            $this->addColumn(
                $table,
                'encounter_class',
                $this->string(10)->notNull()->defaultValue('AMB')->after('id_efector')
            );
            $this->createIndex(
                'idx_' . $this->db->schema->getRawTableName($table) . '_encounter_class',
                $table,
                ['encounter_class']
            );
            $this->update($table, ['encounter_class' => 'AMB']);
        }
    }

    private function dropEncounterClassColumn(string $table): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns['encounter_class'])) {
            return;
        }
        $raw = $this->db->schema->getRawTableName($table);
        $idx = 'idx_' . $raw . '_encounter_class';
        try {
            $this->dropIndex($idx, $table);
        } catch (\Throwable $e) {
            // índice puede no existir en entornos parciales
        }
        $this->dropColumn($table, 'encounter_class');
    }

    private function createCoberturaTable(): void
    {
        $table = '{{%profesional_cobertura}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey()->unsigned(),
            'id_persona' => $this->integer()->notNull(),
            'id_efector' => $this->integer()->notNull(),
            'id_servicio' => $this->integer()->null(),
            'id_profesional_efector_servicio' => $this->integer()->null(),
            'encounter_class' => $this->string(10)->notNull()->comment('EMER|IMP'),
            'inicio' => $this->dateTime()->notNull(),
            'fin' => $this->dateTime()->notNull(),
            'rol' => $this->string(64)->null(),
            'notas' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
            'deleted_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
            'deleted_by' => $this->integer()->null(),
        ]);

        $this->createIndex('idx_profesional_cobertura_persona_efector', $table, ['id_persona', 'id_efector']);
        $this->createIndex('idx_profesional_cobertura_efector_class_inicio', $table, ['id_efector', 'encounter_class', 'inicio']);
        $this->createIndex('idx_profesional_cobertura_intervalo', $table, ['id_persona', 'id_efector', 'inicio', 'fin']);
        $this->createIndex('idx_profesional_cobertura_deleted', $table, ['deleted_at']);
    }
}

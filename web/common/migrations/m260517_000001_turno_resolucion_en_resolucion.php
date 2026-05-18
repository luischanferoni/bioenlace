<?php

use yii\db\ColumnSchema;
use yii\db\Migration;
use yii\db\TableSchema;

/**
 * Reemplaza turno_agenda_conflicto por turno_resolucion y habilita Turno::ESTADO_EN_RESOLUCION.
 *
 * Idempotente: si falló tras crear turno_resolucion sin FK (errno 150), re-ejecutar alinea tipos y agrega FK.
 */
class m260517_000001_turno_resolucion_en_resolucion extends Migration
{
    public function safeUp()
    {
        $this->dropLegacyConflictoTable();

        $this->refreshTableSchema('{{%turnos}}');
        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        $idTurnoCol = $this->columnDefMatchingTurnosPk($turnos);

        $resolucion = $this->db->schema->getTableSchema('{{%turno_resolucion}}', true);
        if ($resolucion === null) {
            $this->createTable('{{%turno_resolucion}}', [
                'id' => $this->primaryKey(),
                'id_turno' => $idTurnoCol,
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
        } else {
            $this->alignColumnToTurnosPk('{{%turno_resolucion}}', 'id_turno', $turnos);
        }

        $this->refreshTableSchema('{{%turno_resolucion}}');

        if (!$this->indexExists('{{%turno_resolucion}}', 'ix_turno_resolucion_turno')) {
            $this->createIndex('ix_turno_resolucion_turno', '{{%turno_resolucion}}', ['id_turno']);
        }
        if (!$this->indexExists('{{%turno_resolucion}}', 'ix_turno_resolucion_turno_estado')) {
            $this->createIndex('ix_turno_resolucion_turno_estado', '{{%turno_resolucion}}', ['id_turno', 'estado']);
        }
        if (!$this->indexExists('{{%turno_resolucion}}', 'ix_turno_resolucion_estado')) {
            $this->createIndex('ix_turno_resolucion_estado', '{{%turno_resolucion}}', ['estado']);
        }

        $this->ensureTableInnoDb('{{%turnos}}');
        $this->ensureTableInnoDb('{{%turno_resolucion}}');

        if ($turnos !== null && isset($turnos->columns['id_turnos'])) {
            $this->addForeignKeyIfMissing(
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
            $this->addForeignKeyIfMissing(
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
        $this->dropForeignKeyIfExists('fk_turno_resolucion_agenda_version', '{{%turno_resolucion}}');
        $this->dropForeignKeyIfExists('fk_turno_resolucion_turno', '{{%turno_resolucion}}');
        if ($this->db->schema->getTableSchema('{{%turno_resolucion}}', true) !== null) {
            $this->dropTable('{{%turno_resolucion}}');
        }
    }

    private function dropLegacyConflictoTable(): void
    {
        if ($this->db->schema->getTableSchema('{{%turno_agenda_conflicto}}', true) === null) {
            return;
        }
        foreach (['fk_turno_agenda_conflicto_turno', 'fk_turno_agenda_conflicto_version'] as $fk) {
            $this->dropForeignKeyIfExists($fk, '{{%turno_agenda_conflicto}}');
        }
        $this->dropTable('{{%turno_agenda_conflicto}}');
    }

    /**
     * FK a turnos.id_turnos: mismo tipo (signed/unsigned, int/bigint) que la PK legacy.
     *
     * @return \yii\db\ColumnSchemaBuilder
     */
    private function columnDefMatchingTurnosPk(?TableSchema $turnos)
    {
        if ($turnos === null || !isset($turnos->columns['id_turnos'])) {
            return $this->integer()->notNull();
        }

        return $this->columnDefFromSchemaColumn($turnos->columns['id_turnos'])->notNull();
    }

    /**
     * @return \yii\db\ColumnSchemaBuilder
     */
    private function columnDefFromSchemaColumn(ColumnSchema $col)
    {
        switch ($col->type) {
            case 'bigint':
                $def = $this->bigInteger();
                break;
            case 'smallint':
                $def = $this->smallInteger();
                break;
            default:
                $def = $this->integer();
        }
        if ($col->unsigned) {
            $def->unsigned();
        }

        return $def;
    }

    private function alignColumnToTurnosPk(string $table, string $column, ?TableSchema $turnos): void
    {
        if ($turnos === null || !isset($turnos->columns['id_turnos'])) {
            return;
        }
        $local = $this->db->schema->getTableSchema($table, true);
        if ($local === null || !isset($local->columns[$column])) {
            return;
        }
        $current = $local->columns[$column];
        $target = $turnos->columns['id_turnos'];
        if ($current->type === $target->type && (bool) $current->unsigned === (bool) $target->unsigned) {
            return;
        }
        $this->alterColumn($table, $column, $this->columnDefFromSchemaColumn($target)->notNull());
        $this->refreshTableSchema($table);
    }

    private function ensureTableInnoDb(string $table): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }
        $raw = $this->db->schema->getRawTableName($table);
        $engine = $this->db->createCommand(
            'SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            [':t' => $raw]
        )->queryScalar();
        if ($engine !== false && strtoupper((string) $engine) !== 'INNODB') {
            $this->execute('ALTER TABLE ' . $this->db->quoteTableName($table) . ' ENGINE=InnoDB');
            $this->refreshTableSchema($table);
        }
    }

    private function addForeignKeyIfMissing(
        string $name,
        string $table,
        string $columns,
        string $refTable,
        string $refColumns,
        ?string $delete = null,
        ?string $update = null
    ): void {
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }
        $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $cnt = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND CONSTRAINT_NAME = :n
               AND CONSTRAINT_TYPE = :type',
            [':t' => $raw, ':n' => $name, ':type' => 'FOREIGN KEY']
        )->queryScalar();

        return $cnt > 0;
    }

    private function indexExists(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $cnt = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND INDEX_NAME = :n',
            [':t' => $raw, ':n' => $name]
        )->queryScalar();

        return $cnt > 0;
    }

    private function dropForeignKeyIfExists(string $name, string $table): void
    {
        if ($this->foreignKeyExists($table, $name)) {
            $this->dropForeignKey($name, $table);
        }
    }

    private function refreshTableSchema(string $table): void
    {
        $this->db->schema->refreshTableSchema($table);
    }
}

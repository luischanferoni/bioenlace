<?php

use yii\db\Migration;

/**
 * Unificación por capas: Asistente/Chat/Interacción.
 *
 * Sin retrocompatibilidad:
 * - chat_dialogo          -> asistente_conversacion
 * - chat_mensaje          -> asistente_interaccion
 * - consulta_chat_messages -> interaccion_chat_clinico
 * - consulta_motivos_messages -> interaccion_motivos_consulta
 *
 * Además renombra columnas clave para alinear el modelo mental:
 * - asistente_interaccion.dialogo_id -> conversacion_id
 * - asistente_interaccion.content -> texto
 * - asistente_interaccion.timestamp -> created_at
 * - asistente_conversacion.estado_json -> contexto_json (si existe)
 * - asistente_conversacion.fecha_inicio -> created_at (si existe)
 * - asistente_conversacion.fecha_ultima_interaccion -> updated_at (si existe)
 */
class m260331_000001_renombrar_tablas_chat_a_asistente_interaccion extends Migration
{
    public function safeUp()
    {
        $this->renameIfExists('{{%chat_dialogo}}', '{{%asistente_conversacion}}');
        $this->renameIfExists('{{%chat_mensaje}}', '{{%asistente_interaccion}}');

        $this->renameIfExists('{{%consulta_chat_messages}}', '{{%interaccion_chat_clinico}}');
        $this->renameIfExists('{{%consulta_motivos_messages}}', '{{%interaccion_motivos_consulta}}');

        $conv = $this->db->schema->getTableSchema('{{%asistente_conversacion}}', true);
        if ($conv) {
            $this->renameColumnIfExists($conv, '{{%asistente_conversacion}}', 'estado_json', 'contexto_json');
            $this->renameColumnIfExists($conv, '{{%asistente_conversacion}}', 'fecha_inicio', 'created_at');
            $this->renameColumnIfExists($conv, '{{%asistente_conversacion}}', 'fecha_ultima_interaccion', 'updated_at');
        }

        $inter = $this->db->schema->getTableSchema('{{%asistente_interaccion}}', true);
        if ($inter) {
            $this->renameColumnIfExists($inter, '{{%asistente_interaccion}}', 'dialogo_id', 'conversacion_id');
            $this->renameColumnIfExists($inter, '{{%asistente_interaccion}}', 'content', 'texto');
            $this->renameColumnIfExists($inter, '{{%asistente_interaccion}}', 'timestamp', 'created_at');
        }

        $clin = $this->db->schema->getTableSchema('{{%interaccion_chat_clinico}}', true);
        if ($clin) {
            $this->renameColumnIfExists($clin, '{{%interaccion_chat_clinico}}', 'content', 'texto');
        }

        $mot = $this->db->schema->getTableSchema('{{%interaccion_motivos_consulta}}', true);
        if ($mot) {
            $this->renameColumnIfExists($mot, '{{%interaccion_motivos_consulta}}', 'content', 'texto');
        }
    }

    public function safeDown()
    {
        // Revertir columnas primero (si existen)
        $inter = $this->db->schema->getTableSchema('{{%asistente_interaccion}}', true);
        if ($inter) {
            $this->renameColumnIfExists($inter, '{{%asistente_interaccion}}', 'conversacion_id', 'dialogo_id');
            $this->renameColumnIfExists($inter, '{{%asistente_interaccion}}', 'texto', 'content');
            $this->renameColumnIfExists($inter, '{{%asistente_interaccion}}', 'created_at', 'timestamp');
        }

        $conv = $this->db->schema->getTableSchema('{{%asistente_conversacion}}', true);
        if ($conv) {
            $this->renameColumnIfExists($conv, '{{%asistente_conversacion}}', 'contexto_json', 'estado_json');
            $this->renameColumnIfExists($conv, '{{%asistente_conversacion}}', 'created_at', 'fecha_inicio');
            $this->renameColumnIfExists($conv, '{{%asistente_conversacion}}', 'updated_at', 'fecha_ultima_interaccion');
        }

        $clin = $this->db->schema->getTableSchema('{{%interaccion_chat_clinico}}', true);
        if ($clin) {
            $this->renameColumnIfExists($clin, '{{%interaccion_chat_clinico}}', 'texto', 'content');
        }

        $mot = $this->db->schema->getTableSchema('{{%interaccion_motivos_consulta}}', true);
        if ($mot) {
            $this->renameColumnIfExists($mot, '{{%interaccion_motivos_consulta}}', 'texto', 'content');
        }

        // Revertir tablas
        $this->renameIfExists('{{%asistente_conversacion}}', '{{%chat_dialogo}}');
        $this->renameIfExists('{{%asistente_interaccion}}', '{{%chat_mensaje}}');
        $this->renameIfExists('{{%interaccion_chat_clinico}}', '{{%consulta_chat_messages}}');
        $this->renameIfExists('{{%interaccion_motivos_consulta}}', '{{%consulta_motivos_messages}}');
    }

    private function renameIfExists(string $from, string $to): void
    {
        $fromSchema = $this->db->schema->getTableSchema($from, true);
        $toSchema = $this->db->schema->getTableSchema($to, true);
        if ($fromSchema !== null && $toSchema === null) {
            $this->renameTable($from, $to);
        }
    }

    private function renameColumnIfExists($schema, string $table, string $from, string $to): void
    {
        if (isset($schema->columns[$from]) && !isset($schema->columns[$to])) {
            // MariaDB: si existe un CHECK que referencia la columna vieja (p.ej. json_valid(`estado_json`)),
            // el ALTER TABLE ... CHANGE fallará porque Yii recrea el CHECK con el nombre anterior.
            $dropped = $this->dropCheckConstraintsReferencingColumn($table, $from);

            $this->renameColumn($table, $from, $to);

            // Re-crear CHECK json_valid para la columna renombrada si antes existía.
            // (Solo para los CHECK que detectamos con json_valid())
            if (!empty($dropped)) {
                foreach ($dropped as $chk) {
                    if (($chk['is_json_valid'] ?? false) === true) {
                        $this->addJsonValidCheckIfMissing($table, $to);
                        break;
                    }
                }
            }
        }
    }

    /**
     * @return array<int, array{constraint_name:string, check_clause:string, is_json_valid:bool}>
     */
    private function dropCheckConstraintsReferencingColumn(string $table, string $column): array
    {
        // Solo aplica a MySQL/MariaDB (Yii usa driverName = mysql).
        if ($this->db->driverName !== 'mysql') {
            return [];
        }

        $rawTable = $this->db->schema->getRawTableName($table);
        $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if (!$dbName) {
            return [];
        }

        $rows = $this->db->createCommand(
            "SELECT tc.CONSTRAINT_NAME AS constraint_name, cc.CHECK_CLAUSE AS check_clause
             FROM information_schema.TABLE_CONSTRAINTS tc
             JOIN information_schema.CHECK_CONSTRAINTS cc
               ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
              AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
             WHERE tc.CONSTRAINT_SCHEMA = :db
               AND tc.TABLE_NAME = :tbl
               AND tc.CONSTRAINT_TYPE = 'CHECK'"
        , [
            ':db' => $dbName,
            ':tbl' => $rawTable,
        ])->queryAll();

        if (empty($rows)) {
            return [];
        }

        $dropped = [];
        foreach ($rows as $row) {
            $name = (string)($row['constraint_name'] ?? '');
            $clause = (string)($row['check_clause'] ?? '');
            if ($name === '' || $clause === '') {
                continue;
            }

            // Detectar referencia a la columna (con o sin backticks).
            $needle1 = '`' . $column . '`';
            $needle2 = $column;
            if (stripos($clause, $needle1) === false && stripos($clause, $needle2) === false) {
                continue;
            }

            $isJsonValid = stripos($clause, 'json_valid') !== false;

            // MariaDB: DROP CHECK `constraint_name`
            $this->execute("ALTER TABLE `{$rawTable}` DROP CHECK `{$name}`");

            $dropped[] = [
                'constraint_name' => $name,
                'check_clause' => $clause,
                'is_json_valid' => $isJsonValid,
            ];
        }

        return $dropped;
    }

    private function addJsonValidCheckIfMissing(string $table, string $column): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        $rawTable = $this->db->schema->getRawTableName($table);
        $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if (!$dbName) {
            return;
        }

        $existing = $this->db->createCommand(
            "SELECT cc.CHECK_CLAUSE AS check_clause
             FROM information_schema.TABLE_CONSTRAINTS tc
             JOIN information_schema.CHECK_CONSTRAINTS cc
               ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
              AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
             WHERE tc.CONSTRAINT_SCHEMA = :db
               AND tc.TABLE_NAME = :tbl
               AND tc.CONSTRAINT_TYPE = 'CHECK'"
        , [
            ':db' => $dbName,
            ':tbl' => $rawTable,
        ])->queryColumn();

        $needle = 'json_valid(`' . $column . '`)';
        foreach ($existing as $clause) {
            if (is_string($clause) && stripos($clause, $needle) !== false) {
                return; // ya existe
            }
        }

        $constraint = 'chk_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $rawTable . '_' . $column . '_json');
        $this->execute("ALTER TABLE `{$rawTable}` ADD CONSTRAINT `{$constraint}` CHECK (json_valid(`{$column}`))");
    }
}


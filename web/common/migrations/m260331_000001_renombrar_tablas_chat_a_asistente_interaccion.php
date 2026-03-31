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
            // Caso especial MariaDB: columnas LONGTEXT con CHECK json_valid() inline.
            // En MariaDB/Yii, renameColumn puede regenerar el CHECK apuntando al nombre viejo y fallar.
            if ($this->db->driverName === 'mysql') {
                $rawTable = $this->db->schema->getRawTableName($table);
                if ($rawTable === 'asistente_conversacion') {
                    if ($from === 'estado_json' && $to === 'contexto_json') {
                        $this->execute(
                            "ALTER TABLE `{$rawTable}` " .
                            "CHANGE `estado_json` `contexto_json` longtext " .
                            "CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL " .
                            "CHECK (json_valid(`contexto_json`))"
                        );
                        return;
                    }
                    if ($from === 'contexto_json' && $to === 'estado_json') {
                        $this->execute(
                            "ALTER TABLE `{$rawTable}` " .
                            "CHANGE `contexto_json` `estado_json` longtext " .
                            "CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL " .
                            "CHECK (json_valid(`estado_json`))"
                        );
                        return;
                    }
                }
            }

            // Default: renombrado simple
            $this->renameColumn($table, $from, $to);
        }
    }
}


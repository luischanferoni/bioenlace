<?php

use yii\db\Migration;

/**
 * Separa tipología de solicitud del medio en interaccion_chat_clinico.
 *
 * - Agrega solicitud_categoria (renovacion_medicacion|ajuste_medicacion|consulta_evolucion).
 * - Backfill desde message_type legacy solicitud_*.
 * - Normaliza esos message_type a texto.
 */
class m260722_160000_interaccion_chat_solicitud_categoria extends Migration
{
    private const TABLE = '{{%interaccion_chat_clinico}}';

    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['solicitud_categoria'])) {
            $this->addColumn(
                self::TABLE,
                'solicitud_categoria',
                $this->string(40)->null()->comment(
                    'renovacion_medicacion|ajuste_medicacion|consulta_evolucion|NULL'
                )
            );
            $this->createIndex(
                'idx_interaccion_chat_clinico_solicitud_categoria',
                self::TABLE,
                'solicitud_categoria'
            );
        }

        $table = $this->db->schema->getRawTableName(self::TABLE);
        $this->execute(
            "UPDATE `{$table}` SET solicitud_categoria = 'renovacion_medicacion',"
            . " message_type = 'texto'"
            . " WHERE message_type = 'solicitud_renovacion'"
        );
        $this->execute(
            "UPDATE `{$table}` SET solicitud_categoria = 'ajuste_medicacion',"
            . " message_type = 'texto'"
            . " WHERE message_type = 'solicitud_ajuste'"
        );
        $this->execute(
            "UPDATE `{$table}` SET solicitud_categoria = 'consulta_evolucion',"
            . " message_type = 'texto'"
            . " WHERE message_type = 'solicitud_consulta'"
        );
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        if ($schema === null || !isset($schema->columns['solicitud_categoria'])) {
            return;
        }

        $table = $this->db->schema->getRawTableName(self::TABLE);
        $this->execute(
            "UPDATE `{$table}` SET message_type = 'solicitud_renovacion'"
            . " WHERE solicitud_categoria = 'renovacion_medicacion'"
            . " AND message_type = 'texto'"
        );
        $this->execute(
            "UPDATE `{$table}` SET message_type = 'solicitud_ajuste'"
            . " WHERE solicitud_categoria = 'ajuste_medicacion'"
            . " AND message_type = 'texto'"
        );
        $this->execute(
            "UPDATE `{$table}` SET message_type = 'solicitud_consulta'"
            . " WHERE solicitud_categoria = 'consulta_evolucion'"
            . " AND message_type = 'texto'"
        );

        $this->dropIndex('idx_interaccion_chat_clinico_solicitud_categoria', self::TABLE);
        $this->dropColumn(self::TABLE, 'solicitud_categoria');
    }
}

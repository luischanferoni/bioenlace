<?php

use yii\db\Migration;

/**
 * Crea `interaccion_chat_clinico` si falta.
 *
 * Histórico: el rename m260331 solo renombra `consulta_chat_messages` cuando existe;
 * esa tabla nunca tuvo migración de create, así que en varios entornos quedó ausente
 * y el home/bandeja async falla al consultar ConsultaChatMessage.
 */
class m260717_100000_create_interaccion_chat_clinico extends Migration
{
    private const TABLE = '{{%interaccion_chat_clinico}}';
    private const LEGACY = '{{%consulta_chat_messages}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $legacy = $this->db->schema->getTableSchema(self::LEGACY, true);
        if ($legacy !== null) {
            $this->renameTable(self::LEGACY, self::TABLE);
            $this->ensureCurrentColumns();

            return;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->notNull()->comment('encounter.id'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('Usuario que envía'),
            'user_name' => $this->string(100)->notNull(),
            'user_role' => $this->string(20)->notNull()->comment('medico|paciente|enfermeria|administrador'),
            'texto' => $this->text()->notNull()->comment('Texto o ruta relativa del archivo'),
            'message_type' => $this->string(20)->notNull()->defaultValue('texto')->comment('texto|imagen|audio|video|documento'),
            'is_read' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_interaccion_chat_clinico_encounter_id', self::TABLE, 'encounter_id');
        $this->createIndex('idx_interaccion_chat_clinico_unread', self::TABLE, ['encounter_id', 'is_read']);

        if ($this->db->schema->getTableSchema('{{%encounter}}', true) !== null) {
            try {
                $this->addForeignKey(
                    'fk_interaccion_chat_clinico_encounter',
                    self::TABLE,
                    'encounter_id',
                    '{{%encounter}}',
                    'id',
                    'CASCADE',
                    'CASCADE'
                );
            } catch (\Throwable $e) {
                // Tipos/collation pueden impedir FK en entornos legacy; el índice alcanza.
            }
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        try {
            $this->dropForeignKey('fk_interaccion_chat_clinico_encounter', self::TABLE);
        } catch (\Throwable $e) {
            // FK puede no existir
        }

        $this->dropTable(self::TABLE);
    }

    private function ensureCurrentColumns(): void
    {
        $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        if ($schema === null) {
            return;
        }

        if (isset($schema->columns['consulta_id']) && !isset($schema->columns['encounter_id'])) {
            $this->renameColumn(self::TABLE, 'consulta_id', 'encounter_id');
            $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        }

        if (isset($schema->columns['content']) && !isset($schema->columns['texto'])) {
            $this->renameColumn(self::TABLE, 'content', 'texto');
            $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        }

        if (!isset($schema->columns['user_role'])) {
            $this->addColumn(
                self::TABLE,
                'user_role',
                $this->string(20)->notNull()->defaultValue('paciente')->comment('medico|paciente|enfermeria|administrador')
            );
        }

        if (!isset($schema->columns['is_read'])) {
            $this->addColumn(self::TABLE, 'is_read', $this->boolean()->notNull()->defaultValue(false));
        }

        if (!isset($schema->columns['updated_at'])) {
            $this->addColumn(self::TABLE, 'updated_at', $this->dateTime()->null());
            $this->execute(
                'UPDATE ' . $this->db->schema->getRawTableName(self::TABLE) .
                ' SET updated_at = created_at WHERE updated_at IS NULL'
            );
            $this->alterColumn(self::TABLE, 'updated_at', $this->dateTime()->notNull());
        }
    }
}

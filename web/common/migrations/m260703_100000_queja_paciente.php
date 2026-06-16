<?php

use yii\db\Migration;
use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\QuejaPaciente;

/**
 * Quejas enviadas por pacientes desde la app (solo lectura superadmin en admin).
 */
class m260703_100000_queja_paciente extends Migration
{
    public function safeUp()
    {
        $table = '{{%queja_paciente}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'id_persona' => $this->integer()->notNull(),
            'categoria' => MigrationEnumColumn::mysqlEnum(
                QuejaPaciente::categoriaValues(),
                QuejaPaciente::CATEGORIA_OTRO,
                true,
                implode('|', QuejaPaciente::categoriaValues())
            ),
            'descripcion' => $this->text()->notNull(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('ix_queja_paciente_persona_created', $table, ['id_persona', 'created_at']);
        $this->addForeignKey(
            'fk_queja_paciente_persona',
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
        $table = '{{%queja_paciente}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $this->dropForeignKey('fk_queja_paciente_persona', $table);
        $this->dropTable($table);
    }
}

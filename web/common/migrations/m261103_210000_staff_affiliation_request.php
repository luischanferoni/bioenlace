<?php

use yii\db\Migration;

/**
 * Solicitud de incorporación de personal de salud a un centro activo.
 *
 * Solo registra la intención declarada al crear la cuenta personal gratuita;
 * todavía no dispara aprobaciones ni asignaciones.
 */
class m261103_210000_staff_affiliation_request extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%staff_affiliation_request}}', true) !== null) {
            return;
        }

        $this->createTable('{{%staff_affiliation_request}}', [
            'id' => $this->primaryKey()->unsigned(),
            'id_persona' => $this->integer()->notNull(),
            'id_user' => $this->integer()->null(),
            'id_efector' => $this->integer()->notNull(),
            'sector' => $this->string(12)->notNull(),
            'nombre_efector' => $this->string(255)->notNull(),
            'codigo_sisa' => $this->string(15)->null(),
            'localidad' => $this->string(100)->null(),
            'departamento' => $this->string(40)->null(),
            'provincia' => $this->string(20)->null(),
            'id_provincia' => $this->integer()->null(),
            'status' => $this->string(16)->notNull()->defaultValue('PENDING'),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'deleted_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex(
            'uq_staff_affiliation_request_persona_efector',
            '{{%staff_affiliation_request}}',
            ['id_persona', 'id_efector'],
            true
        );
        $this->createIndex(
            'idx_staff_affiliation_request_status',
            '{{%staff_affiliation_request}}',
            ['status', 'deleted_at']
        );
        $this->createIndex(
            'idx_staff_affiliation_request_efector',
            '{{%staff_affiliation_request}}',
            ['id_efector', 'deleted_at']
        );
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%staff_affiliation_request}}', true) !== null) {
            $this->dropTable('{{%staff_affiliation_request}}');
        }
    }
}

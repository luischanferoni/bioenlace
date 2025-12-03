<?php

use yii\db\Migration;

/**
 * Agregar columna origen a abreviaturas_medicas
 */
class m240101_000003_add_origen_to_abreviaturas_medicas extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Verificar si la tabla existe
        if ($this->db->schema->getTableSchema('{{%abreviaturas_medicas}}') !== null) {
            // Verificar si la columna ya existe
            if ($this->db->schema->getTableSchema('{{%abreviaturas_medicas}}')->getColumn('origen') === null) {
                $this->addColumn('{{%abreviaturas_medicas}}', 'origen', $this->string(10)->defaultValue('USUARIO')->comment('Origen de la abreviatura: LLM o USUARIO'));
                
                // Agregar índice para la nueva columna
                $this->createIndex('idx_abreviaturas_medicas_origen', '{{%abreviaturas_medicas}}', 'origen');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Verificar si la tabla existe
        if ($this->db->schema->getTableSchema('{{%abreviaturas_medicas}}') !== null) {
            // Verificar si la columna existe
            if ($this->db->schema->getTableSchema('{{%abreviaturas_medicas}}')->getColumn('origen') !== null) {
                // Eliminar índice
                $this->dropIndex('idx_abreviaturas_medicas_origen', '{{%abreviaturas_medicas}}');
                
                // Eliminar columna
                $this->dropColumn('{{%abreviaturas_medicas}}', 'origen');
            }
        }
    }
}

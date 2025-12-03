<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%abreviaturas_rrhh}}`.
 */
class m240101_000004_create_abreviaturas_rrhh_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%abreviaturas_rrhh}}', [
            'id' => $this->primaryKey(),
            'abreviatura_id' => $this->integer()->notNull()->comment('ID de la abreviatura médica'),
            'id_rr_hh' => $this->integer()->notNull()->comment('ID del médico (RRHH)'),
            'frecuencia_uso' => $this->integer()->defaultValue(1)->comment('Frecuencia de uso por este médico'),
            'fecha_primer_uso' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->comment('Fecha del primer uso'),
            'fecha_ultimo_uso' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')->comment('Fecha del último uso'),
            'activo' => $this->tinyInteger(1)->defaultValue(1)->comment('Si la relación está activa'),
        ]);

        // Índices para optimizar consultas
        $this->createIndex('idx_abreviaturas_rrhh_abreviatura', '{{%abreviaturas_rrhh}}', 'abreviatura_id');
        $this->createIndex('idx_abreviaturas_rrhh_medico', '{{%abreviaturas_rrhh}}', 'id_rr_hh');
        $this->createIndex('idx_abreviaturas_rrhh_frecuencia', '{{%abreviaturas_rrhh}}', 'frecuencia_uso');
        $this->createIndex('idx_abreviaturas_rrhh_activo', '{{%abreviaturas_rrhh}}', 'activo');
        
        // Índice compuesto para consultas frecuentes
        $this->createIndex('idx_abreviaturas_rrhh_compuesto', '{{%abreviaturas_rrhh}}', ['abreviatura_id', 'id_rr_hh', 'activo']);

        // Claves foráneas
        $this->addForeignKey(
            'fk_abreviaturas_rrhh_abreviatura',
            '{{%abreviaturas_rrhh}}',
            'abreviatura_id',
            '{{%abreviaturas_medicas}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Nota: No agregamos FK a RRHH ya que puede no existir la tabla o tener otro nombre
        // Se puede agregar después si es necesario
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_abreviaturas_rrhh_abreviatura', '{{%abreviaturas_rrhh}}');
        $this->dropTable('{{%abreviaturas_rrhh}}');
    }
}

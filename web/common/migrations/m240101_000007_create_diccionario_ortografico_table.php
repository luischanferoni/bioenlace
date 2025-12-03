<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%diccionario_ortografico}}`.
 */
class m240101_000007_create_diccionario_ortografico_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%diccionario_ortografico}}', [
            'id' => $this->primaryKey(),
            'termino' => $this->string(150)->notNull()->comment('Palabra o patrón a corregir'),
            'correccion' => $this->string(150)->null()->comment('Corrección sugerida (si aplica)'),
            'tipo' => $this->string(20)->notNull()->defaultValue('termino')->comment('termino|error|stopword'),
            'categoria' => $this->string(100)->null()->comment('Categoría semántica'),
            'especialidad' => $this->string(100)->null()->comment('Especialidad médica'),
            'frecuencia' => $this->integer()->notNull()->defaultValue(0)->comment('Frecuencia de uso'),
            'peso' => $this->decimal(5,2)->notNull()->defaultValue(1.00)->comment('Peso para scoring'),
            'metadata' => $this->json()->null()->comment('Información adicional'),
            'activo' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->append('ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_diccionario_ortografico_termino', '{{%diccionario_ortografico}}', 'termino');
        $this->createIndex('idx_diccionario_ortografico_tipo', '{{%diccionario_ortografico}}', 'tipo');
        $this->createIndex('idx_diccionario_ortografico_especialidad', '{{%diccionario_ortografico}}', 'especialidad');
        $this->createIndex('idx_diccionario_ortografico_activo', '{{%diccionario_ortografico}}', 'activo');
        $this->createIndex('idx_diccionario_ortografico_compuesto', '{{%diccionario_ortografico}}', ['termino', 'tipo', 'especialidad'], true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%diccionario_ortografico}}');
    }
}



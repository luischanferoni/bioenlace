<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%terminos_contexto_medico}}`.
 */
class m240101_000006_create_terminos_contexto_medico_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%terminos_contexto_medico}}', [
            'id' => $this->primaryKey(),
            'termino' => $this->string(150)->notNull()->comment('Término o bigrama normalizado'),
            'tipo' => $this->string(20)->notNull()->defaultValue('palabra')->comment('palabra|bigram|regex'),
            'categoria' => $this->string(100)->null()->comment('Categoría clínica asociada'),
            'especialidad' => $this->string(100)->null()->comment('Especialidad médica opcional'),
            'peso' => $this->decimal(5,2)->notNull()->defaultValue(1.00)->comment('Peso para cálculo de score'),
            'frecuencia_uso' => $this->integer()->notNull()->defaultValue(0)->comment('Frecuencia observada'),
            'fuente' => $this->string(50)->null()->comment('Origen del término (manual, IA, importación, etc.)'),
            'metadata' => $this->json()->null()->comment('Información adicional'),
            'activo' => $this->boolean()->notNull()->defaultValue(true)->comment('Disponible para análisis'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->append('ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_terminos_contexto_medico_termino', '{{%terminos_contexto_medico}}', 'termino');
        $this->createIndex('idx_terminos_contexto_medico_tipo', '{{%terminos_contexto_medico}}', 'tipo');
        $this->createIndex('idx_terminos_contexto_medico_especialidad', '{{%terminos_contexto_medico}}', 'especialidad');
        $this->createIndex('idx_terminos_contexto_medico_activo', '{{%terminos_contexto_medico}}', 'activo');
        $this->createIndex('idx_terminos_contexto_medico_compuesto', '{{%terminos_contexto_medico}}', ['termino', 'tipo', 'especialidad'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%terminos_contexto_medico}}');
    }
}



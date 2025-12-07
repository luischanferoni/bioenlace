<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%snomed_deferred_jobs}}`.
 */
class m240101_000011_create_snomed_deferred_jobs_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%snomed_deferred_jobs}}', [
            'id' => $this->primaryKey(),
            'consulta_id' => $this->integer()->null()->comment('ID de la consulta (puede ser null si aún no se guardó)'),
            'datos_extraidos' => $this->text()->notNull()->comment('Datos extraídos por IA en formato JSON'),
            'categorias' => $this->text()->notNull()->comment('Categorías de configuración en formato JSON'),
            'status' => $this->string(20)->notNull()->defaultValue('pending')->comment('Estado: pending, processing, completed, error'),
            'resultado' => $this->text()->null()->comment('Resultado del procesamiento SNOMED en formato JSON'),
            'processed_at' => $this->timestamp()->null()->comment('Fecha y hora de procesamiento'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->append('ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_snomed_deferred_jobs_consulta_id', '{{%snomed_deferred_jobs}}', 'consulta_id');
        $this->createIndex('idx_snomed_deferred_jobs_status', '{{%snomed_deferred_jobs}}', 'status');
        $this->createIndex('idx_snomed_deferred_jobs_created_at', '{{%snomed_deferred_jobs}}', 'created_at');
        
        // Índice compuesto para búsquedas frecuentes
        $this->createIndex('idx_snomed_deferred_jobs_status_created', '{{%snomed_deferred_jobs}}', ['status', 'created_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%snomed_deferred_jobs}}');
    }
}
<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%respuestas_predefinidas}}`.
 */
class m240101_000010_create_respuestas_predefinidas_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%respuestas_predefinidas}}', [
            'id' => $this->primaryKey(),
            'texto_original' => $this->text()->notNull()->comment('Texto original de la consulta'),
            'texto_hash' => $this->string(32)->notNull()->comment('MD5 del texto para búsqueda rápida'),
            'respuesta_json' => $this->json()->notNull()->comment('Respuesta de IA en formato JSON'),
            'categoria' => $this->string(100)->null()->comment('Categoría de la consulta'),
            'servicio' => $this->string(100)->null()->comment('Servicio médico asociado'),
            'similitud_promedio' => $this->decimal(5,3)->notNull()->defaultValue(0.000)->comment('Similitud promedio con consultas similares'),
            'usos' => $this->integer()->notNull()->defaultValue(0)->comment('Cantidad de veces que se ha reutilizado'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->append('ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_respuestas_predefinidas_texto_hash', '{{%respuestas_predefinidas}}', 'texto_hash');
        $this->createIndex('idx_respuestas_predefinidas_categoria', '{{%respuestas_predefinidas}}', 'categoria');
        $this->createIndex('idx_respuestas_predefinidas_servicio', '{{%respuestas_predefinidas}}', 'servicio');
        $this->createIndex('idx_respuestas_predefinidas_usos', '{{%respuestas_predefinidas}}', 'usos');
        $this->createIndex('idx_respuestas_predefinidas_servicio_hash', '{{%respuestas_predefinidas}}', ['servicio', 'texto_hash']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%respuestas_predefinidas}}');
    }
}
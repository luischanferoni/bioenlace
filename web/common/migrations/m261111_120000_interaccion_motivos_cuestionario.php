<?php

use yii\db\Migration;

/**
 * Respuestas del cuestionario pre-turno de motivos de consulta.
 *
 * Una fila por pregunta respondida (incluido el cierre libre, cuya pregunta es `cierre_libre`).
 */
class m261111_120000_interaccion_motivos_cuestionario extends Migration
{
    private const TABLE = '{{%interaccion_motivos_cuestionario}}';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull()->comment('ID de la consulta'),
            'question_id' => $this->string(64)->notNull()->comment('Pregunta del catálogo declarativo'),
            'option_id' => $this->string(64)->null()->comment('Opción elegida; null en el cierre libre'),
            'texto_libre' => $this->string(500)->null()->comment('Respuesta de la opción "Otra" o del cierre'),
            'answered_by_user_id' => $this->integer()->unsigned()->null()->comment('Usuario (paciente) que respondió'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->null(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('uq_motivos_cuestionario', self::TABLE, ['encounter_id', 'question_id'], true);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $this->dropTable(self::TABLE);
    }
}

<?php

use yii\db\Migration;

/**
 * Agrega origen a consultas_motivos para diferenciar motivos cargados por el médico
 * de los cargados por el paciente (codificación desde consulta_motivos_messages).
 * Valores: 'medico' | 'paciente'. Por defecto 'medico' para registros existentes.
 *
 * Si safeUp() falla, se hace rollback (dropColumn) para que la próxima ejecución no falle.
 */
class m250306_000004_add_origen_to_consultas_motivos extends Migration
{
    private $tableName = '{{%consultas_motivos}}';

    public function safeUp()
    {
        try {
            $this->addColumn(
                $this->tableName,
                'origen',
                $this->string(20)->notNull()->defaultValue('medico')->comment('medico|paciente')
            );
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function rollback()
    {
        try {
            $this->dropColumn($this->tableName, 'origen');
        } catch (\Throwable $e) {
            // La columna puede no existir si falló antes de crearla
        }
    }

    public function safeDown()
    {
        $this->rollback();
    }
}

<?php

use yii\db\Migration;

/**
 * Agrega origen a consultas_motivos para diferenciar motivos cargados por el médico
 * de los cargados por el paciente (codificación desde consulta_motivos_messages).
 * Valores: 'medico' | 'paciente'. Por defecto 'medico' para registros existentes.
 */
class m250306_000004_add_origen_to_consultas_motivos extends Migration
{
    public function safeUp()
    {
        $this->addColumn(
            '{{%consultas_motivos}}',
            'origen',
            $this->string(20)->notNull()->defaultValue('medico')->comment('medico|paciente')
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%consultas_motivos}}', 'origen');
    }
}

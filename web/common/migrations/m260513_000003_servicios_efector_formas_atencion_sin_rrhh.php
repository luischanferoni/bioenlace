<?php

use yii\db\Migration;

/**
 * Renombra valores legacy en servicios_efector.formas_atencion (sin subcadena RRHH).
 */
class m260513_000003_servicios_efector_formas_atencion_sin_rrhh extends Migration
{
    public function safeUp()
    {
        $map = [
            'DELEGAR_A_CADA_RRHH' => 'DELEGAR_A_CADA_PROFESIONAL',
            'DERIVACION_DELEGAR_A_CADA_RRHH' => 'DERIVACION_DELEGAR_A_CADA_PROFESIONAL',
        ];

        foreach ($map as $from => $to) {
            $this->update('{{%servicios_efector}}', ['formas_atencion' => $to], ['formas_atencion' => $from]);
        }
    }

    public function safeDown()
    {
        $map = [
            'DELEGAR_A_CADA_PROFESIONAL' => 'DELEGAR_A_CADA_RRHH',
            'DERIVACION_DELEGAR_A_CADA_PROFESIONAL' => 'DERIVACION_DELEGAR_A_CADA_RRHH',
        ];

        foreach ($map as $from => $to) {
            $this->update('{{%servicios_efector}}', ['formas_atencion' => $to], ['formas_atencion' => $from]);
        }
    }
}

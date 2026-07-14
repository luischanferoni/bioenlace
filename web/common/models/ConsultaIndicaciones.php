<?php

namespace common\models;

/**
 * Tipología de extracción/prompt para indicaciones clínicas (no es práctica realizada).
 * Persistencia: ServiceRequest / care plan (mismo canal que prácticas).
 */
class ConsultaIndicaciones extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt()
    {
        return [
            'Indicacion',
            'Plazo dias',
        ];
    }
}

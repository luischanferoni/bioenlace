<?php

namespace common\models\Clinical;

/**
 * Tipología de captura — suministro/administración. Persistencia MedicationAdministration.
 */
final class ConsultaSuministroMedicamento extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return ['Fecha', 'Hora'];
    }
}

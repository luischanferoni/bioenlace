<?php

namespace common\models\Clinical;

/**
 * Capture model for medication administration, persisted as MedicationAdministration.
 */
final class EncounterMedicationAdministration extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return ['Fecha', 'Hora'];
    }
}

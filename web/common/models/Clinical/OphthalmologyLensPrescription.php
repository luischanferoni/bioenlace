<?php

namespace common\models\Clinical;

/**
 * Capture model for lens prescriptions, persisted as VisionPrescription.
 */
final class OphthalmologyLensPrescription extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return [];
    }
}

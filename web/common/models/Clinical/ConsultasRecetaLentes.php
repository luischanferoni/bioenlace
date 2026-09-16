<?php

namespace common\models\Clinical;

/**
 * Tipología de captura — receta de lentes. Persistencia VisionPrescription. Sin tabla legacy.
 */
final class ConsultasRecetaLentes extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return [];
    }
}

<?php

namespace common\models\Clinical;

/**
 * Tipología de captura — prácticas oftalmológicas. Sin tabla legacy.
 */
final class ConsultaPracticasOftalmologia extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return [];
    }
}

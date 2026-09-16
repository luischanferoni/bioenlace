<?php

namespace common\models\Clinical;

/**
 * Tipología de captura — motivos de consulta (legacy name `ConsultaMotivos`).
 * Persistencia: `encounter.reason_text` / coding. Sin tabla `consultas_motivos`.
 */
final class ConsultaMotivos extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return [];
    }
}

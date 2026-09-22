<?php

namespace common\components\Domain\Clinical\Prescription\Application\Agents;

use common\components\Platform\Agent\AgentRunRecorder;
use common\models\Clinical\ElectronicPrescription;
use Yii;

/**
 * Agente E03 v1: bloquea emisión si la receta no cumple integridad RDI + política.
 * Gates hard en modelos/servicio; YAML solo umbrales ({@see ValidatePrescriptionRdiPreSubmit}).
 */
final class PrescriptionRdiPreSubmitValidationAgent
{
    public const AGENT_ID = ValidatePrescriptionRdiPreSubmit::AGENT_ID;

    public const TRIGGER_TYPE = 'electronic_prescription_issue';

    private ValidatePrescriptionRdiPreSubmit $validation;

    public function __construct(?ValidatePrescriptionRdiPreSubmit $validation = null)
    {
        $this->validation = $validation ?? new ValidatePrescriptionRdiPreSubmit();
    }

    public function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['autonomous_agent_prescription_rdi_validation_enabled'] ?? true);
    }

    public function assertCanIssue(ElectronicPrescription $rx): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $errors = $this->validation->validate($rx);
        if ($errors === []) {
            AgentRunRecorder::record(
                self::AGENT_ID,
                self::TRIGGER_TYPE,
                'allowed',
                (int) $rx->id,
                (int) $rx->encounter_id,
                (int) $rx->subject_persona_id,
                null,
                ['item_count' => count($rx->items)]
            );

            return;
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'blocked',
            (int) $rx->id,
            (int) $rx->encounter_id,
            (int) $rx->subject_persona_id,
            'validation_failed',
            ['errors' => $errors]
        );

        throw new \InvalidArgumentException(implode(' ', $errors));
    }
}

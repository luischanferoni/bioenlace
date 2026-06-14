<?php

namespace common\components\Domain\Clinical\Assistant;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Platform\Assistant\Chat\Conversational\ConversationalChannelProviderInterface;
use Yii;

/**
 * Contexto clínico del paciente para respuestas conversacionales del asistente.
 */
final class ClinicalConversationalChannelProvider implements ConversationalChannelProviderInterface
{
    /**
     * @param list<string> $parts
     */
    public static function appendPatientContext(int $idPersona, array &$parts): void
    {
        if ($idPersona <= 0) {
            return;
        }

        try {
            $patientBlock = (new PatientAiContextBuilder())->build(
                $idPersona,
                PatientAiContextBuilder::PROFILE_CONVERSATIONAL
            );
            if ($patientBlock !== '') {
                $parts[] = '';
                $parts[] = $patientBlock;
            }
        } catch (\Throwable $e) {
            Yii::warning('ClinicalConversationalChannelProvider: ' . $e->getMessage(), 'asistente');
        }
    }
}

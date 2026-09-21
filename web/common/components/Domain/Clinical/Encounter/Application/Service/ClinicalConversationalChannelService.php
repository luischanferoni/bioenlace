<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Platform\Assistant\Chat\Conversational\ConversationalChannelProviderInterface;
use Yii;

/**
 * Contexto clínico del paciente para respuestas conversacionales del asistente.
 */
final class ClinicalConversationalChannelService implements ConversationalChannelProviderInterface
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
            $patientBlock = (new PatientAiContextService())->build(
                $idPersona,
                PatientAiContextService::PROFILE_CONVERSATIONAL
            );
            if ($patientBlock !== '') {
                $parts[] = '';
                $parts[] = $patientBlock;
            }
        } catch (\Throwable $e) {
            Yii::warning('ClinicalConversationalChannelService: ' . $e->getMessage(), 'asistente');
        }
    }
}

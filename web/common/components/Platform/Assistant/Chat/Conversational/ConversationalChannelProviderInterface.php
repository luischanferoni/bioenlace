<?php

namespace common\components\Platform\Assistant\Chat\Conversational;

/**
 * Enriquecimiento de dominio para el canal conversacional (contexto paciente, etc.).
 */
interface ConversationalChannelProviderInterface
{
    /**
     * @param list<string> $parts
     */
    public static function appendPatientContext(int $idPersona, array &$parts): void;
}

<?php

namespace common\components\Domain\Person\Assistant;

/**
 * Enriquece el draft del asistente para lookup de recursos provinciales (ministerio de salud, etc.).
 */
final class PacienteRecursoProvincialFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options ignorado
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $content = trim((string) ($body['content'] ?? ''));
        if ($content !== '' && trim((string) ($draft['q'] ?? '')) === '') {
            $draft['q'] = $content;
        }
        $body['draft'] = $draft;
    }
}

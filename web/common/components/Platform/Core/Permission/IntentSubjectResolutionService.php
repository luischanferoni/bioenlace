<?php

namespace common\components\Platform\Core\Permission;

/**
 * Aplica subject_resolution del YAML del intent al draft del asistente.
 */
final class IntentSubjectResolutionService
{
    public function applyToBody(string $intentId, array &$body): void
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return;
        }

        $cfg = (new IntentRequestContextService())->subjectResolutionConfig($intentId);
        if ($cfg === null) {
            return;
        }

        $handlerId = trim((string) ($cfg['handler'] ?? ''));
        if ($handlerId === '') {
            return;
        }

        IntentSubjectResolutionRegistry::apply($handlerId, $intentId, $body);
    }
}

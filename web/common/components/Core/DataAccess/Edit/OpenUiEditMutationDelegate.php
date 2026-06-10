<?php

namespace common\components\Core\DataAccess\Edit;

/**
 * Delega aspectos open_ui a una ui_action del catálogo.
 */
final class OpenUiEditMutationDelegate
{
    /**
     * @param array<string, mixed> $aspectDef
     * @param array<string, int|string> $subjectContext
     * @return array{aspect_id: string, action_id: string, params: array<string, string>}
     */
    public function buildAction(string $aspectId, array $aspectDef, array $subjectContext): array
    {
        $uiAction = trim((string) ($aspectDef['ui_action'] ?? ''));
        if ($uiAction === '') {
            throw new \InvalidArgumentException('El aspecto open_ui no define ui_action.');
        }

        $requires = $aspectDef['requires_params'] ?? [];
        if (!is_array($requires)) {
            $requires = [];
        }

        $params = [];
        foreach ($requires as $paramName) {
            $key = trim((string) $paramName);
            if ($key === '') {
                continue;
            }
            if (array_key_exists($key, $subjectContext)) {
                $params[$key] = (string) $subjectContext[$key];
            }
        }

        return [
            'aspect_id' => $aspectId,
            'action_id' => $uiAction,
            'params' => $params,
        ];
    }
}

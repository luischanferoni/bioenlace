<?php

namespace common\components\Core\DataAccess;

/**
 * Resuelve edit_surface_id desde NL (keywords en attribute_groups_v1.yaml).
 */
final class DataAccessEditDiscoveryService
{
    public const CHANNEL_EDITAR = 'editar';

    private AttributeGroupCatalog $catalog;
    private EditSurfaceAuthorizationService $authorization;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $authorization = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->authorization = $authorization ?? new EditSurfaceAuthorizationService($this->catalog);
    }

    public static function channelForIntentId(string $intentId): ?string
    {
        return trim($intentId) === 'data-access.editar' ? self::CHANNEL_EDITAR : null;
    }

    /**
     * @param list<array<string, mixed>> $extractions
     */
    public function resolveSurfaceId(string $content, array $extractions, ?PermissionContext $ctx = null): ?string
    {
        $ctx = $ctx ?? PermissionContext::fromCurrentUser();
        $contentLower = mb_strtolower(trim($content), 'UTF-8');
        $bestId = null;
        $bestScore = 0;

        foreach ($this->catalog->listEditSurfacesForDisplay() as $surfaceId => $def) {
            if (!is_string($surfaceId) || !is_array($def)) {
                continue;
            }
            if (!$this->authorization->userCanAccessEditSurface($ctx, $surfaceId)) {
                continue;
            }
            $score = $this->scoreSurface($surfaceId, $def, $contentLower, $extractions);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $surfaceId;
            }
        }

        return $bestScore > 0 ? $bestId : null;
    }

    /**
     * @return list<string>
     */
    public function assistantKeywordsForUser(int $userId): array
    {
        $ctx = new PermissionContext($userId, $this->roleNamesForUser($userId));
        if (!$this->authorization->userHasAnyEditableSurface($ctx)) {
            return [];
        }

        $out = [
            'editar',
            'modificar',
            'actualizar',
            'cambiar',
        ];
        foreach ($this->catalog->listEditSurfacesForDisplay() as $surfaceId => $def) {
            if (!is_string($surfaceId) || !is_array($def)) {
                continue;
            }
            if (!$this->authorization->userCanAccessEditSurface($ctx, $surfaceId)) {
                continue;
            }
            foreach ($this->assistantKeywords($def) as $kw) {
                $out[] = $kw;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $surfaceDef
     * @param list<array<string, mixed>> $extractions
     */
    private function scoreSurface(string $surfaceId, array $surfaceDef, string $contentLower, array $extractions): int
    {
        $score = 0;
        $surfaceIdLower = mb_strtolower($surfaceId, 'UTF-8');
        if ($contentLower !== '' && mb_stripos($contentLower, $surfaceIdLower) !== false) {
            $score += 15;
        }

        $label = mb_strtolower(trim((string) ($surfaceDef['label'] ?? '')), 'UTF-8');
        if ($label !== '' && $contentLower !== '' && mb_stripos($contentLower, $label) !== false) {
            $score += 12;
        }

        foreach ($this->assistantKeywords($surfaceDef) as $kw) {
            $kwLower = mb_strtolower(trim($kw), 'UTF-8');
            if ($kwLower === '' || $contentLower === '') {
                continue;
            }
            if (mb_stripos($contentLower, $kwLower) !== false) {
                $score += 10 + min(5, (int) (mb_strlen($kwLower) / 4));
            }
        }

        foreach ($extractions as $ex) {
            if (!is_array($ex)) {
                continue;
            }
            $span = mb_strtolower(trim((string) ($ex['span'] ?? '')), 'UTF-8');
            if ($span === '') {
                continue;
            }
            foreach ($this->assistantKeywords($surfaceDef) as $kw) {
                if (mb_stripos($span, mb_strtolower($kw, 'UTF-8')) !== false) {
                    $score += 6;
                }
            }
        }

        if ($contentLower !== '' && preg_match('/\b(editar|modificar|actualizar|cambiar)\b/u', $contentLower)) {
            $score += 3;
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $surfaceDef
     * @return list<string>
     */
    private function assistantKeywords(array $surfaceDef): array
    {
        $assistant = $surfaceDef['assistant'] ?? [];
        if (!is_array($assistant)) {
            return [];
        }
        $keywords = $assistant['keywords'] ?? [];

        return is_array($keywords) ? array_values(array_filter(array_map('strval', $keywords))) : [];
    }

    /**
     * @return list<string>
     */
    private function roleNamesForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $assigned = \Yii::$app->authManager->getRolesByUser($userId);

        return is_array($assigned) ? array_keys($assigned) : [];
    }
}

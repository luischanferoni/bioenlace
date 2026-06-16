<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Core\Permission\IntentEditSurfaceIndex;

/**
 * Resuelve edit_surface_id desde NL (keywords en data-access-config).
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
    /**
     * @param array<string, mixed> $params
     */
    public function resolveSurfaceId(
        string $content,
        array $extractions,
        ?PermissionContext $ctx = null,
        array $params = []
    ): ?string {
        $ctx = $ctx ?? PermissionContext::fromCurrentUser();
        $contentLower = mb_strtolower(trim($content), 'UTF-8');
        $bestId = null;
        $bestScore = 0;

        foreach ($this->catalog->listEditSurfacesForDisplay() as $surfaceId => $def) {
            if (!is_string($surfaceId) || !is_array($def)) {
                continue;
            }
            if (IntentEditSurfaceIndex::isSurfaceMigrated($surfaceId)) {
                continue;
            }
            if (!$this->authorization->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
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
     * Resuelve aspectos editables mencionados en NL (solo si el match es inequívoco).
     *
     * @param list<array<string, mixed>> $extractions
     * @return list<string>
     */
    /**
     * @param array<string, mixed> $params
     */
    public function resolveAspectIds(
        string $content,
        string $surfaceId,
        array $extractions,
        ?PermissionContext $ctx = null,
        array $params = []
    ): array {
        $surfaceId = trim($surfaceId);
        if ($surfaceId === '') {
            return [];
        }

        $ctx = $ctx ?? PermissionContext::fromCurrentUser();
        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            return [];
        }

        $contentLower = mb_strtolower(trim($content), 'UTF-8');
        $scores = [];

        $aspects = $surface['aspects'] ?? [];
        if (!is_array($aspects)) {
            return [];
        }

        foreach ($aspects as $aspectId => $def) {
            if (!is_string($aspectId) || !is_array($def)) {
                continue;
            }
            if (!$this->authorization->userCanAccessAspect($ctx, $surfaceId, $aspectId, $params)) {
                continue;
            }
            $score = $this->scoreAspect($aspectId, $def, $contentLower, $extractions);
            if ($score > 0) {
                $scores[$aspectId] = $score;
            }
        }

        if ($scores === []) {
            return [];
        }

        arsort($scores);
        $topScore = (int) reset($scores);
        if ($topScore <= 0) {
            return [];
        }

        $topIds = [];
        foreach ($scores as $aspectId => $score) {
            if ((int) $score === $topScore) {
                $topIds[] = $aspectId;
            }
        }

        return count($topIds) === 1 ? $topIds : [];
    }

    /**
     * @return list<string>
     */
    public function assistantKeywordsForUser(int $userId): array
    {
        $ctx = new PermissionContext($userId, $this->roleNamesForUser($userId));
        if (!$this->authorization->userHasAnyWriteGrantForEdit($ctx)) {
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
            if (IntentEditSurfaceIndex::isSurfaceMigrated($surfaceId)) {
                continue;
            }
            if ($this->authorization->listAspectIdsWithWriteGrant($ctx, $surfaceId) === []) {
                continue;
            }
            foreach ($this->assistantKeywords($def) as $kw) {
                $out[] = $kw;
            }
            foreach ($this->assistantAspectKeywordsByGrant($surfaceId, $def, $ctx) as $kw) {
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

        if ($surfaceId === 'ProfesionalEfectorServicio'
            && preg_match('/\b(profesional|medico|m[eé]dico|doctor|personal)\b/u', $contentLower)) {
            $score += 18;
        }

        if ($surfaceId === 'ProfesionalEfectorServicioAgenda'
            && preg_match('/\b(agenda|horario|horarios|grilla|turnos)\b/u', $contentLower)) {
            $score += 20;
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $surfaceDef
     * @param list<array<string, mixed>> $extractions
     */
    private function scoreAspect(
        string $aspectId,
        array $aspectDef,
        string $contentLower,
        array $extractions
    ): int {
        $score = 0;
        $aspectIdLower = mb_strtolower($aspectId, 'UTF-8');
        if ($contentLower !== '' && mb_stripos($contentLower, $aspectIdLower) !== false) {
            $score += 12;
        }

        $label = mb_strtolower(trim((string) ($aspectDef['label'] ?? '')), 'UTF-8');
        if ($label !== '' && $contentLower !== '' && mb_stripos($contentLower, $label) !== false) {
            $score += 14;
        }

        foreach ($this->aspectAssistantKeywords($aspectDef) as $kw) {
            $kwLower = mb_strtolower(trim($kw), 'UTF-8');
            if ($kwLower === '' || $contentLower === '') {
                continue;
            }
            if (mb_stripos($contentLower, $kwLower) !== false) {
                $score += 10 + min(6, (int) (mb_strlen($kwLower) / 3));
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
            foreach ($this->aspectAssistantKeywords($aspectDef) as $kw) {
                if (mb_stripos($span, mb_strtolower($kw, 'UTF-8')) !== false) {
                    $score += 7;
                }
            }
        }

        return $score;
    }

    /**
     * Keywords de aspectos con grant write (sin scope de sesión; solo descubrimiento NL).
     *
     * @param array<string, mixed> $surfaceDef
     * @return list<string>
     */
    private function assistantAspectKeywordsByGrant(string $surfaceId, array $surfaceDef, PermissionContext $ctx): array
    {
        $aspects = $surfaceDef['aspects'] ?? [];
        if (!is_array($aspects)) {
            return [];
        }

        $out = [];
        foreach ($this->authorization->listAspectIdsWithWriteGrant($ctx, $surfaceId) as $aspectId) {
            $def = $aspects[$aspectId] ?? null;
            if (!is_array($def)) {
                continue;
            }
            foreach ($this->aspectAssistantKeywords($def) as $kw) {
                $out[] = $kw;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @return list<string>
     */
    private function aspectAssistantKeywords(array $aspectDef): array
    {
        return CatalogDefinitionHelper::keywords($aspectDef);
    }

    /**
     * @param array<string, mixed> $surfaceDef
     * @return list<string>
     */
    private function assistantKeywords(array $surfaceDef): array
    {
        return CatalogDefinitionHelper::keywords($surfaceDef);
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

<?php

namespace common\components\Core\DataAccess;

use common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext;
use common\components\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Core\DataAccess\Edit\EditSparseAspectIds;
use common\components\Organization\Service\Efectores\OrganizationEfectorAccess;

/**
 * Enriquece draft para intent genérico data-access.editar.
 *
 * @param array<string, mixed> $body
 */
final class DataAccessEditFlowDraftHydrator
{
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $ctx = PermissionContext::fromCurrentUser();
        $auth = new EditSurfaceAuthorizationService();
        if (!$auth->userHasAnyEditableSurface($ctx)) {
            throw new \yii\web\ForbiddenHttpException('No tenés permiso para modificar datos.');
        }

        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $content = trim((string) ($body['content'] ?? ''));

        $catalog = new AttributeGroupCatalog();
        $discovery = new DataAccessEditDiscoveryService();

        if (!isset($draft['id_efector']) || trim((string) $draft['id_efector']) === '') {
            $fromDraft = isset($draft['id_efector']) ? (int) $draft['id_efector'] : 0;
            $idEfector = OrganizationEfectorAccess::resolveIdEfector($fromDraft > 0 ? $fromDraft : null);
            if ($idEfector > 0) {
                $draft['id_efector'] = (string) $idEfector;
            }
        }

        $scopeParams = self::scopeParamsFromDraft($draft);

        if (trim((string) ($draft['surface_id'] ?? $draft['edit_surface_id'] ?? '')) === '') {
            $surfaceId = $discovery->resolveSurfaceId(
                $content,
                ChatPreprocessContext::extractions(),
                $ctx,
                $scopeParams
            );
            if ($surfaceId === null) {
                $surfaceId = self::surfaceIdFromClassificationRules($content, $auth, $ctx, $scopeParams);
            }
            if ($surfaceId === null) {
                $surfaceId = self::soleEditableSurfaceId($catalog, $auth, $ctx, $scopeParams);
            }
            if ($surfaceId !== null) {
                $draft['surface_id'] = $surfaceId;
            }
        }

        $surfaceId = trim((string) ($draft['surface_id'] ?? $draft['edit_surface_id'] ?? ''));
        self::applySubjectPrefillFromSurface($draft, $catalog->getEditSurface($surfaceId), $content);

        $aspectIds = [];
        if ($surfaceId !== '' && trim((string) ($draft['aspect_ids'] ?? '')) === '') {
            $aspectIds = $discovery->resolveAspectIds(
                $content,
                $surfaceId,
                ChatPreprocessContext::extractions(),
                $ctx,
                $scopeParams
            );
            if ($aspectIds !== []) {
                $draft['aspect_ids'] = implode(',', $aspectIds);
            }
        } elseif ($surfaceId !== '') {
            $aspectIds = EditSparseAspectIds::parse((string) ($draft['aspect_ids'] ?? ''));
        }

        if (trim((string) ($draft['edit_step'] ?? '')) === '') {
            $draft['edit_step'] = self::resolveEditStep(
                $surfaceId,
                $aspectIds,
                $catalog->getEditSurface($surfaceId),
                $draft
            );
        }

        $body['draft'] = $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    private static function scopeParamsFromDraft(array $draft): array
    {
        $params = [];
        $idEfector = (int) ($draft['id_efector'] ?? 0);
        if ($idEfector > 0) {
            $params['id_efector'] = $idEfector;
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function surfaceIdFromClassificationRules(
        string $content,
        EditSurfaceAuthorizationService $auth,
        PermissionContext $ctx,
        array $params
    ): ?string {
        if (IntentClassificationRulesService::ruleMatches('staff_agenda_config_edit', $content)
            || IntentClassificationRulesService::ruleMatches('own_agenda_config_edit', $content)) {
            $candidate = 'agenda_profesional_en_efector';
            if ($auth->userCanAccessEditSurface($ctx, $candidate, $params)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function soleEditableSurfaceId(
        AttributeGroupCatalog $catalog,
        EditSurfaceAuthorizationService $auth,
        PermissionContext $ctx,
        array $params
    ): ?string {
        $ids = [];
        foreach ($catalog->listEditSurfacesForDisplay() as $surfaceId => $def) {
            if (!is_string($surfaceId) || !is_array($def)) {
                continue;
            }
            if ($auth->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
                $ids[] = $surfaceId;
            }
        }

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * @param array<string, mixed>|null $surface
     * @param array<string, mixed> $draft
     * @return string
     */
    private static function resolveEditStep(string $surfaceId, array $aspectIds, ?array $surface, array $draft): string
    {
        if ($surfaceId === '') {
            return 'surfaces';
        }

        if (self::surfaceNeedsSubjectPick($surface, $draft)) {
            return 'subjects';
        }

        if ($aspectIds !== []) {
            return 'form';
        }

        return 'aspects';
    }

    /**
     * @param array<string, mixed>|null $surface
     * @param array<string, mixed> $draft
     */
    private static function surfaceNeedsSubjectPick(?array $surface, array $draft): bool
    {
        $resolver = is_array($surface) ? ($surface['subject_resolver'] ?? []) : [];
        $metricId = is_array($resolver) ? trim((string) ($resolver['metric_id'] ?? '')) : '';
        if ($metricId === '') {
            return false;
        }

        return trim((string) ($draft['id_persona'] ?? '')) === ''
            && trim((string) ($draft['id_profesional_efector_servicio'] ?? '')) === '';
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed>|null $surface
     */
    private static function applySubjectPrefillFromSurface(array &$draft, ?array $surface, string $content): void
    {
        if ($surface === null || trim((string) ($draft['id_persona'] ?? '')) !== '') {
            return;
        }
        $resolver = $surface['subject_resolver'] ?? [];
        if (!is_array($resolver)) {
            return;
        }
        $ruleId = trim((string) ($resolver['prefill_session_id_persona_when_rule'] ?? ''));
        if ($ruleId === '' || !IntentClassificationRulesService::ruleMatches($ruleId, $content)) {
            return;
        }
        $idPersona = (int) \Yii::$app->user->getIdPersona();
        if ($idPersona > 0) {
            $draft['id_persona'] = (string) $idPersona;
        }
    }
}

<?php

namespace common\components\Core\DataAccess;

use common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext;
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

        if (trim((string) ($draft['surface_id'] ?? $draft['edit_surface_id'] ?? '')) === '') {
            $discovery = new DataAccessEditDiscoveryService();
            $surfaceId = $discovery->resolveSurfaceId($content, ChatPreprocessContext::extractions(), $ctx);
            if ($surfaceId !== null) {
                $draft['surface_id'] = $surfaceId;
            }
        }

        if (!isset($draft['id_efector']) || trim((string) $draft['id_efector']) === '') {
            $fromDraft = isset($draft['id_efector']) ? (int) $draft['id_efector'] : 0;
            $idEfector = OrganizationEfectorAccess::resolveIdEfector($fromDraft > 0 ? $fromDraft : null);
            if ($idEfector > 0) {
                $draft['id_efector'] = (string) $idEfector;
            }
        }

        $body['draft'] = $draft;
    }
}

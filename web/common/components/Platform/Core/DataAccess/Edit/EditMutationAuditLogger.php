<?php

namespace common\components\Platform\Core\DataAccess\Edit;

use common\components\Platform\Core\DataAccess\PermissionContext;
use Yii;

/**
 * Auditoría de mutaciones staff (edición dispersa).
 */
final class EditMutationAuditLogger
{
    /**
     * @param list<string> $aspectIds
     * @param array<string, int|string> $subjectContext
     * @param list<array{field: string, label: string, before: string, after: string}> $appliedChanges
     */
    public static function logApplied(
        string $surfaceId,
        array $aspectIds,
        array $subjectContext,
        array $appliedChanges,
        PermissionContext $ctx
    ): void {
        Yii::info([
            'event' => 'data_access_edit_applied',
            'surface_id' => $surfaceId,
            'aspect_ids' => $aspectIds,
            'user_id' => $ctx->userId,
            'roles' => $ctx->roleNames,
            'subject' => $subjectContext,
            'changes' => $appliedChanges,
        ], 'data-access');
    }

    /**
     * @param list<string> $aspectIds
     * @param array<string, int|string> $subjectContext
     */
    public static function logDenied(
        string $surfaceId,
        array $aspectIds,
        array $subjectContext,
        PermissionContext $ctx,
        string $reason
    ): void {
        Yii::warning([
            'event' => 'data_access_edit_denied',
            'surface_id' => $surfaceId,
            'aspect_ids' => $aspectIds,
            'user_id' => $ctx->userId,
            'roles' => $ctx->roleNames,
            'subject' => $subjectContext,
            'reason' => $reason,
        ], 'data-access');
    }
}

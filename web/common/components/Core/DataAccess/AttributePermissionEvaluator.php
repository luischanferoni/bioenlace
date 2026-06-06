<?php

namespace common\components\Core\DataAccess;

use common\components\Core\DataAccess\Grant\CompositeRoleGrantSource;
use Yii;

/**
 * Evalúa si un rol del usuario puede usar un grupo de atributos en una operación.
 * Grants efectivos: BD activa → YAML.
 */
final class AttributePermissionEvaluator
{
    /** @var CompositeRoleGrantSource */
    private $grantSource;

    public function __construct(?CompositeRoleGrantSource $grantSource = null)
    {
        $this->grantSource = $grantSource ?? new CompositeRoleGrantSource();
    }

    public function can(PermissionContext $ctx, string $entityGroupKey, string $operation): bool
    {
        if (!QueryOperation::isValid($operation)) {
            return false;
        }

        if (Yii::$app->user->isSuperadmin) {
            return true;
        }

        foreach ($ctx->roleNames as $role) {
            $grant = $this->grantSource->getGrant($role, $entityGroupKey);
            if ($grant === null) {
                continue;
            }
            $ops = $grant['operations'] ?? [];
            if (!is_array($ops)) {
                continue;
            }
            if (in_array($operation, $ops, true)) {
                return true;
            }
        }

        return false;
    }

    public function scopeCheckerFor(PermissionContext $ctx, string $entityGroupKey): ?string
    {
        if (Yii::$app->user->isSuperadmin) {
            return null;
        }

        foreach ($ctx->roleNames as $role) {
            $grant = $this->grantSource->getGrant($role, $entityGroupKey);
            if ($grant === null) {
                continue;
            }
            $checker = trim((string) ($grant['scope_checker'] ?? ''));

            return $checker !== '' ? $checker : null;
        }

        return null;
    }
}

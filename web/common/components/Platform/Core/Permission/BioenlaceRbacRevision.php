<?php

namespace common\components\Platform\Core\Permission;

use Yii;

/**
 * Revisión global de RBAC: invalida caché de sesión y de rutas tras cambios en roles/permisos.
 */
final class BioenlaceRbacRevision
{
    public const SESSION_KEY = '__bioenlace_rbac_revision';

    private const CACHE_KEY = '__bioenlace_rbac_revision';

    public static function resetForTests(): void
    {
        if (!Yii::$app->has('cache') || Yii::$app->cache === null) {
            return;
        }

        Yii::$app->cache->delete(self::CACHE_KEY);
    }

    public static function current(): int
    {
        if (!Yii::$app->has('cache') || Yii::$app->cache === null) {
            return 0;
        }

        return (int) Yii::$app->cache->get(self::CACHE_KEY, 0);
    }

    public static function bump(): void
    {
        if (!Yii::$app->has('cache') || Yii::$app->cache === null) {
            return;
        }

        Yii::$app->cache->set(self::CACHE_KEY, self::current() + 1, 0);
    }
}

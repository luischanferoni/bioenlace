<?php

namespace common\components\Actions;

use Yii;

/**
 * Configuración central del caché del catálogo de acciones API (descubrimiento + RBAC).
 *
 * {@see Yii::$app->params} clave <code>apiActionCatalog</code> → <code>useCache</code> (bool).
 * En construcción, poner <code>useCache => false</code> en params-local para ver controladores
 * y permisos nuevos sin esperar TTL ni invalidar claves a mano.
 */
final class ActionCatalogSettings
{
    public const PARAM_KEY = 'apiActionCatalog';

    /**
     * @param bool $callerWantsCache Si el llamador pasa false (p. ej. consola con discoverAllActions(false)), se respeta y no se usa caché.
     */
    public static function shouldUseCache(bool $callerWantsCache = true): bool
    {
        if (!$callerWantsCache) {
            return false;
        }
        if (!isset(Yii::$app) || Yii::$app === null) {
            return true;
        }
        $cfg = Yii::$app->params[self::PARAM_KEY] ?? [];

        return (bool) ($cfg['useCache'] ?? true);
    }
}

<?php

namespace backend\components;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\models\Person\Persona;
use frontend\components\BaseUserConfig;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Configuración de usuario web backend (identidad, login, permisos en sesión).
 */
class UserConfig extends BaseUserConfig
{
    /**
     * @inheritdoc
     */
    public $enableAutoLogin = true;

    /**
     * @inheritdoc
     */
    public $cookieLifetime = 2592000;

    /**
     * @inheritdoc
     */
    public $loginUrl = ['/auth/login'];

    public const IDENTITY_ID_KEY = 'mainIdentityId';

    public const ADMIN_PERMISSION = 'admin';

    /**
     * No guardar como returnUrl rutas de estáticos ni auth (p. ej. /admin/js/ajax-wrapper.js).
     *
     * @param mixed $url
     */
    public function setReturnUrl($url): void
    {
        if (!self::isValidReturnUrl((string) $url)) {
            return;
        }

        parent::setReturnUrl($url);
    }

    public static function isValidReturnUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }

        if (preg_match('#/(js|css|assets|images)/#i', $path)) {
            return false;
        }

        if (preg_match('#\.(js|css|map|png|jpe?g|gif|webp|ico|woff2?|ttf|svg)$#i', $path)) {
            return false;
        }

        if (preg_match('#/auth/(login|logout)(/|$)#i', $path)) {
            return false;
        }

        return true;
    }

    public function getIsImpersonated()
    {
        return !is_null(Yii::$app->session->get(self::IDENTITY_ID_KEY));
    }

    public function setMainIdentityId($userId)
    {
        Yii::$app->session->set(self::IDENTITY_ID_KEY, $userId);
    }

    public function getMainIdentityId()
    {
        $mainIdentityId = Yii::$app->session->get(self::IDENTITY_ID_KEY);

        return !empty($mainIdentityId) ? $mainIdentityId : $this->getId();
    }

    /**
     * @inheritdoc
     */
    protected function afterLogin($identity, $cookieBased, $duration)
    {
        if (!$identity->superadmin && !\common\models\User::hasRole('_x_efector_AdminSisse')) {
            throw new ForbiddenHttpException();
        }

        $persona = Persona::findOne(['id_user' => $identity->id]);
        if ($persona) {
            $session = Yii::$app->session;
            $session->set('idPersona', $persona->id_persona);
            $session->set('apellidoUsuario', $persona->apellido);
            $session->set('nombreUsuario', $persona->nombre);
        }

        parent::afterLogin($identity, $cookieBased, $duration);
        BioenlaceAccessChecker::refreshForIdentity($identity);
    }
}

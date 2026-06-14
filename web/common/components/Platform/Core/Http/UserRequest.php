<?php

namespace common\components\Platform\Core\Http;

use Yii;
use yii\web\BadRequestHttpException;

/**
 * Resuelve parámetros de contexto operativo desde POST o sesión de usuario.
 */
final class UserRequest
{
    /**
     * Devuelve el valor del parámetro enviado por POST (clave $postKey).
     * Si la petición es POST y falta el parámetro lanza BadRequestHttpException.
     * En peticiones no-POST devuelve el valor desde Yii::$app->user mediante el getter correspondiente.
     *
     * @param string $key idEfector, servicio_actual, encounterClass, id_profesional_efector_servicio, nombreEfector
     * @param string|null $postKey clave concreta en POST si difiere de la key
     * @return mixed
     * @throws BadRequestHttpException
     */
    public static function requireUserParam(string $key, ?string $postKey = null)
    {
        $postKey = $postKey ?: $key;
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            if (isset($post[$postKey])) {
                return $post[$postKey];
            }
            throw new BadRequestHttpException('Parámetro requerido: ' . $postKey);
        }

        switch ($key) {
            case 'idEfector':
                return Yii::$app->user->getIdEfector();
            case 'servicio_actual':
                return Yii::$app->user->getServicioActual();
            case 'encounterClass':
                return Yii::$app->user->getEncounterClass();
            case 'id_profesional_efector_servicio':
                return Yii::$app->user->getIdProfesionalEfectorServicio();
            case 'nombreEfector':
                return Yii::$app->user->getNombreEfector();
            default:
                return null;
        }
    }
}

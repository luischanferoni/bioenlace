<?php
namespace frontend\components;

use Yii;
use yii\web\BadRequestHttpException;

class UserRequest
{
    /**
     * Devuelve el valor del parámetro enviado por POST (clave $postKey).
     * Si la petición es POST y falta el parámetro lanza BadRequestHttpException.
     * En peticiones no-POST devuelve el valor desde Yii::$app->user mediante el getter correspondiente.
     * @param string $key clave simbólica: idEfector, servicio_actual, encounterClass, idRecursoHumano, id_rrhh_servicio, nombreEfector
     * @param string|null $postKey clave concreta en POST si difiere de la key
     * @return mixed
     * @throws BadRequestHttpException
     */
    public static function requireUserParam($key, $postKey = null)
    {
        $postKey = $postKey ?: $key;
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            if (isset($post[$postKey])) {
                return $post[$postKey];
            }
            throw new BadRequestHttpException('Parámetro requerido: ' . $postKey);
        }

        // fallback: devolver desde user getters en peticiones no-POST
        switch ($key) {
            case 'idEfector':
                return Yii::$app->user->getIdEfector();
            case 'servicio_actual':
                return Yii::$app->user->getServicioActual();
            case 'encounterClass':
                return Yii::$app->user->getEncounterClass();
            case 'idRecursoHumano':
                return Yii::$app->user->getIdRecursoHumano();
            case 'id_rrhh_servicio':
                return Yii::$app->user->getIdRrhhServicio();
            case 'nombreEfector':
                return Yii::$app->user->getNombreEfector();
            default:
                return null;
        }
    }
}



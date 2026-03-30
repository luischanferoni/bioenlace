<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;
use common\models\ConsultaChatMessage;
use common\models\Consulta;
use common\models\Persona;
use common\models\RrhhEfector;

/**
 * Chat médico–paciente por consulta.
 */
class ConsultaChatController extends Controller
{
    public $enableCsrfValidation = false;

    const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio', 'video', 'documento'];

    public function behaviors()
    {
        return [
        ];
    }

    public $freeAccessActions = ['messages', 'send', 'upload', 'status'];

    protected function canAccessConsulta(Consulta $consulta)
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return false;
        }
        $persona = Persona::findOne(['id_user' => $userId]);
        if (!$persona) {
            return false;
        }
        if ((int) $consulta->id_persona === (int) $persona->id_persona) {
            return true;
        }
        $rrhhEfector = RrhhEfector::find()->where(['id_rr_hh' => $consulta->id_rr_hh])->one();
        return $rrhhEfector && (int) $rrhhEfector->id_persona === (int) $persona->id_persona;
    }

    /**
     * Obtiene la consulta y verifica que el usuario tenga acceso. Retorna [consulta, null] o [null, array error].
     */
    protected function requireConsultaAccess($consulta_id)
    {
        $consulta = Consulta::findOne($consulta_id);
        if (!$consulta) {
            Yii::$app->response->statusCode = 404;
            return [null, ['success' => false, 'message' => 'Consulta no encontrada', 'data' => null]];
        }
        if (!$this->canAccessConsulta($consulta)) {
            Yii::$app->response->statusCode = 403;
            return [null, ['success' => false, 'message' => 'No tiene permiso para acceder a esta consulta', 'data' => null]];
        }
        return [$consulta, null];
    }
}

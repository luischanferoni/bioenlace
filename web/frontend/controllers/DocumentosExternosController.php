<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;
use yii\base\Exception;

use frontend\filters\SisseActionFilter;
use frontend\components\SisseHtmlHelpers;
use frontend\components\UserRequest;

use common\models\DocumentosExternos;
use common\models\Adjunto;

class DocumentosExternosController extends Controller
{
    use \frontend\controllers\traits\AdjuntoTrait;

    public function behaviors()
    {
        //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionCreate()
    {
        $modelDocumentoExterno = new DocumentosExternos;
        $modelDocumentoExterno->fecha = date("d/m/Y");

        if (Yii::$app->request->post()) {

            if (!$modelDocumentoExterno->load(Yii::$app->request->post())) {
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return [
                    'success' => false,
                    'msg' => 'Hubo un error al intentar cargar el documento.',                
                ];     
            }

            // Obtener id_efector e id_rrhh_servicio obligatorios vía POST (si falta, UserRequest lanzará BadRequest)
            $modelDocumentoExterno->id_efector = UserRequest::requireUserParam('idEfector');
            $modelDocumentoExterno->id_rrhh_servicio = UserRequest::requireUserParam('id_rrhh_servicio');

            // Obtener id_persona desde el POST (puede venir en DocumentosExternos[id_persona] o en id_persona)
            $post = Yii::$app->request->post();
            if (isset($post['DocumentosExternos']) && isset($post['DocumentosExternos']['id_persona'])) {
                $modelDocumentoExterno->id_persona = $post['DocumentosExternos']['id_persona'];
            } elseif (isset($post['id_persona'])) {
                $modelDocumentoExterno->id_persona = $post['id_persona'];
            } else {
                // Parámetro requerido faltante
                throw new \yii\web\BadRequestHttpException('Parámetro requerido: id_persona');
            }

            if (!$modelDocumentoExterno->save()) {   
                return $this->renderAjax('../documentos-externos/_form', [            
                    'modelDocumentoExterno' => $modelDocumentoExterno,
                ]);
            }

            $array_archivos = UploadedFile::getInstancesByName("DocumentosExternos[archivos_adjuntos]");

            if (!empty($array_archivos)) {

                if (!empty($this->subirArchivos($array_archivos, 'DocumentosExternos', $modelDocumentoExterno->id))) {

                    return $this->renderAjax('../documentos-externos/_form', [                        
                        'modelDocumentoExterno' => $modelDocumentoExterno,
                    ]);
                }
            }

            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return [
                'success' => true,
                'msg' => 'El documento externo fue cargado exitosamente.',                
            ];
        }

        return $this->renderAjax('../documentos-externos/_form', [            
            'modelDocumentoExterno' => $modelDocumentoExterno,
        ]);
    }

    public function actionEliminarAdjunto($id)
    {
        $this->eliminarArchivo($id);
    }
}

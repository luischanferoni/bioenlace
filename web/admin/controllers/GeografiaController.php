<?php

namespace admin\controllers;

use common\components\Domain\Organization\Service\GeografiaDepdropService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * DepDrops geográficos para formularios admin (sin depender de rutas API del frontend).
 */
class GeografiaController extends Controller
{
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'departamentos-depdrop' => ['post'],
                    'localidades-depdrop' => ['post'],
                    'localidades-por-provincia-depdrop' => ['post'],
                    'barrios-depdrop' => ['post'],
                ],
            ],
        ];
    }

    /**
     * POST /admin/geografia/departamentos-depdrop
     */
    public function actionDepartamentosDepdrop(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return GeografiaDepdropService::departamentosResponse(Yii::$app->request->post());
    }

    /**
     * POST /admin/geografia/localidades-depdrop
     */
    public function actionLocalidadesDepdrop(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return GeografiaDepdropService::localidadesResponse(Yii::$app->request->post());
    }

    /**
     * POST /admin/geografia/localidades-por-provincia-depdrop
     */
    public function actionLocalidadesPorProvinciaDepdrop(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return GeografiaDepdropService::localidadesPorProvinciaResponse(Yii::$app->request->post());
    }

    /**
     * POST /admin/geografia/barrios-depdrop
     */
    public function actionBarriosDepdrop(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return GeografiaDepdropService::barriosResponse(Yii::$app->request->post());
    }
}

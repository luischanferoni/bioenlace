<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Services\Actions\CommonActionsService;

/**
 * Metadatos de acciones API para clientes (atajos, descubrimiento ligero).
 *
 * GET /api/v1/acciones/comunes — acciones permitidas al usuario, recortadas para inicio.
 */
class AccionesController extends BaseController
{
    /**
     * Lista acciones comunes (permiso RBAC ya aplicado en {@see CommonActionsService} vía ActionMappingService).
     *
     * Query opcional: limit (1–50, default 12).
     */
    public function actionComunes()
    {
        // Autenticación: se valida en la capa de auth del módulo API (filters/behaviors).
        // Los controllers asumen usuario autenticado; no re-chequear aquí.
        $userId = (int) Yii::$app->user->id;

        try {
            $limit = (int) Yii::$app->request->get('limit', CommonActionsService::DEFAULT_LIMIT);
            $payload = CommonActionsService::getFormattedForUser($userId, $limit);

            return [
                'success' => true,
                'actions' => $payload['actions'] ?? [],
                'categories' => $payload['categories'] ?? [],
            ];
        } catch (\Throwable $e) {
            Yii::error('actionComunes: ' . $e->getMessage(), __METHOD__);

            return [
                'success' => false,
                'error' => 'Error al cargar acciones comunes',
                'actions' => [],
            ];
        }
    }
}

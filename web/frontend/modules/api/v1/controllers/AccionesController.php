<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Platform\Core\Service\Actions\CommonActionsService;
use common\components\Platform\Core\Http\Contract\ApiException;

/**
 * Metadatos de acciones API para clientes (atajos, descubrimiento ligero).
 *
 * GET /api/v1/acciones/comunes — acciones permitidas al usuario, recortadas para inicio.
 */
class AccionesController extends BaseController
{
    /**
     * Lista acciones comunes (atajos): intents con grant explícito vía {@see CommonActionsService}.
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
            $appClient = trim((string) Yii::$app->request->headers->get('X-App-Client', ''));
            $payload = CommonActionsService::getFormattedForUser(
                $userId,
                $limit,
                $appClient !== '' ? $appClient : null
            );

            return $this->ok([
                'actions' => $payload['actions'] ?? [],
                'categories' => $payload['categories'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            throw new ApiException('internal_error', [], [], $e);
        }
    }
}

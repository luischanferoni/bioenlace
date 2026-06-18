<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Domain\Scheduling\Service\ServicioTeleconsultaPoliticaService;
use common\components\Domain\Scheduling\Service\ServicioTeleconsultaPoliticaUiPresenter;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\UiScreenService;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Política de teleconsulta por servicio (AdminEfector).
 *
 * RBAC ApiGhost: /api/servicio-teleconsulta/&lt;action&gt;
 */
class ServicioTeleconsultaPoliticaController extends BaseController
{
    /**
     * GET|POST /api/v1/servicio-teleconsulta/configurar
     *
     * @action_name Configurar teleconsulta por servicio (AdminEfector)
     * @entity ServicioTeleconsultaPolitica
     * @tags views, ui, staff, teleconsulta
     */
    public function actionConfigurar(): array
    {
        $req = Yii::$app->request;
        $domain = new ServicioTeleconsultaPoliticaService();
        $presenter = new ServicioTeleconsultaPoliticaUiPresenter();

        try {
            $merged = array_merge($req->get(), $req->isPost ? $req->post() : []);
            $idEfector = $domain->resolveIdEfector($merged);

            if ($req->isPost) {
                $result = $domain->guardar($idEfector, $merged);
                $values = $presenter->valoresDesdeServicio(
                    $merged,
                    (int) ($result['data']['id_servicio'] ?? 0)
                );
                $ui = UiScreenService::renderUiDefinition(
                    'servicio-teleconsulta',
                    'configurar',
                    $req->get(),
                    $values
                );
                $ui = $presenter->apply($ui, $values, $idEfector);
                $ui['success'] = true;
                $ui['message'] = (string) ($result['message'] ?? '');
                $ui['data'] = array_merge($ui['data'] ?? [], $result['data'] ?? []);

                return $ui;
            }

            $params = $req->get();
            $idServicio = (int) ($params['id_servicio'] ?? 0);
            $params = $presenter->valoresDesdeServicio($params, $idServicio);
            $ui = UiScreenService::renderUiDefinition('servicio-teleconsulta', 'configurar', $params, $params);
            $ui = $presenter->apply($ui, $params, $idEfector);
            $ui['success'] = true;

            return $ui;
        } catch (DomainOperationForbiddenException $e) {
            Yii::$app->response->statusCode = 403;
            throw new BadRequestHttpException($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $values = array_merge($req->get(), $req->isPost ? $req->post() : []);
            $ui = UiScreenService::renderUiDefinition(
                'servicio-teleconsulta',
                'configurar',
                $req->get(),
                $values
            );
            try {
                $idEfector = $domain->resolveIdEfector($values);
                $ui = $presenter->apply($ui, $values, $idEfector);
            } catch (\Throwable $ignored) {
            }
            $ui['success'] = false;
            $ui['errors'] = ['_error' => [$e->getMessage()]];
            $ui['values'] = $values;
            $ui['action_id'] = 'servicio-teleconsulta.configurar';

            return $ui;
        }
    }
}

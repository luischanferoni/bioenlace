<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Service\CareProtocolAdminService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * ABM protocolos de cuidado (solo superadmin).
 *
 * GET    /api/v1/clinical/care-protocol/listar-admin
 * GET    /api/v1/clinical/care-protocol/ver/<id>
 * POST   /api/v1/clinical/care-protocol/crear
 * PUT|PATCH /api/v1/clinical/care-protocol/actualizar/<id>
 * POST   /api/v1/clinical/care-protocol/desactivar/<id>
 * POST   /api/v1/clinical/care-protocol/activar/<id>
 */
class CareProtocolController extends BaseController
{
    private CareProtocolAdminService $admin;

    public function init(): void
    {
        parent::init();
        $this->admin = new CareProtocolAdminService();
    }

    public function actionListarAdmin(): array
    {
        try {
            $this->requireSuperadmin();
            $req = Yii::$app->request;
            $incluir = filter_var($req->get('incluir_deshabilitados', '1'), FILTER_VALIDATE_BOOLEAN);
            $idProvincia = $req->get('id_provincia');
            $idProvincia = $idProvincia !== null && $idProvincia !== '' ? (int) $idProvincia : null;
            $protocolos = $this->admin->listar($incluir, $idProvincia);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(['protocolos' => $protocolos], 'Protocolos de cuidado (administración)');
    }

    public function actionVer(int $id): array
    {
        try {
            $this->requireSuperadmin();
            $protocolo = $this->admin->obtener($id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(['protocolo' => $protocolo], 'Protocolo de cuidado');
    }

    public function actionCrear(): array
    {
        try {
            $this->requireSuperadmin();
            $protocolo = $this->admin->crear($this->body());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(['protocolo' => $protocolo], 'Protocolo creado', 201);
    }

    public function actionActualizar(int $id): array
    {
        try {
            $this->requireSuperadmin();
            $protocolo = $this->admin->actualizar($id, $this->body());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(['protocolo' => $protocolo], 'Protocolo actualizado');
    }

    public function actionDesactivar(int $id): array
    {
        return $this->toggleEnabled($id, false);
    }

    public function actionActivar(int $id): array
    {
        return $this->toggleEnabled($id, true);
    }

    private function toggleEnabled(int $id, bool $enabled): array
    {
        try {
            $this->requireSuperadmin();
            if ($enabled) {
                $this->admin->activar($id);
            } else {
                $this->admin->desactivar($id);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(null, $enabled ? 'Protocolo activado' : 'Protocolo desactivado');
    }

    private function requireSuperadmin(): void
    {
        if (!(bool) (Yii::$app->user->isSuperadmin ?? false)) {
            throw new ForbiddenHttpException('Solo superadmin puede administrar protocolos de cuidado.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $req = Yii::$app->request;
        $body = $req->getBodyParams();
        if ($body === [] || $body === null) {
            $body = $req->post();
        }

        return is_array($body) ? $body : [];
    }
}

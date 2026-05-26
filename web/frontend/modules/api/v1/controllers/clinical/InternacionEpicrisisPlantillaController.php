<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Inpatient\InternacionEpicrisisPlantillaAdminService;
use common\components\Inpatient\InternacionEfectorAccess;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * ABM plantillas de epicrisis (staff).
 *
 * GET    /api/v1/clinical/internacion-epicrisis-plantilla/listar-admin
 * GET    /api/v1/clinical/internacion-epicrisis-plantilla/ver/<id>
 * POST   /api/v1/clinical/internacion-epicrisis-plantilla/crear
 * PUT|PATCH /api/v1/clinical/internacion-epicrisis-plantilla/actualizar/<id>
 * POST   /api/v1/clinical/internacion-epicrisis-plantilla/desactivar/<id>
 * POST   /api/v1/clinical/internacion-epicrisis-plantilla/activar/<id>
 */
class InternacionEpicrisisPlantillaController extends BaseController
{
    private InternacionEpicrisisPlantillaAdminService $admin;

    public function init(): void
    {
        parent::init();
        $this->admin = new InternacionEpicrisisPlantillaAdminService();
    }

    public function actionListarAdmin(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? 0) ?: null
            );
            $incluirInactivas = filter_var(
                $req->get('incluir_inactivas', '1'),
                FILTER_VALIDATE_BOOLEAN
            );
            $plantillas = $this->admin->listarAdmin($idEfector, $incluirInactivas);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success([
            'plantillas' => $plantillas,
            'placeholders' => InternacionEpicrisisPlantillaAdminService::PLACEHOLDERS,
        ], 'Plantillas de epicrisis (administración)');
    }

    public function actionVer(int $id): array
    {
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->get('id_efector') ?? 0) ?: null
            );
            $plantilla = $this->admin->obtener($id, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success(['plantilla' => $plantilla], 'Plantilla de epicrisis');
    }

    public function actionCrear(): array
    {
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($this->body()['id_efector'] ?? 0) ?: null
            );
            $plantilla = $this->admin->crear(
                $this->body(),
                $idEfector,
                (bool) (Yii::$app->user->isSuperadmin ?? false)
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success(['plantilla' => $plantilla], 'Plantilla creada', 201);
    }

    public function actionActualizar(int $id): array
    {
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($this->body()['id_efector'] ?? 0) ?: null
            );
            $plantilla = $this->admin->actualizar(
                $id,
                $this->body(),
                $idEfector,
                (bool) (Yii::$app->user->isSuperadmin ?? false)
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success(['plantilla' => $plantilla], 'Plantilla actualizada');
    }

    public function actionDesactivar(int $id): array
    {
        return $this->toggleActivo($id, false);
    }

    public function actionActivar(int $id): array
    {
        return $this->toggleActivo($id, true);
    }

    private function toggleActivo(int $id, bool $activo): array
    {
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($this->body()['id_efector'] ?? Yii::$app->request->get('id_efector') ?? 0) ?: null
            );
            if ($activo) {
                $this->admin->activar($id, $idEfector, (bool) (Yii::$app->user->isSuperadmin ?? false));
            } else {
                $this->admin->desactivar($id, $idEfector, (bool) (Yii::$app->user->isSuperadmin ?? false));
            }
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        $msg = $activo ? 'Plantilla activada' : 'Plantilla desactivada';

        return $this->success(null, $msg);
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

<?php

namespace frontend\controllers;

use common\components\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Organization\Service\Authorization\EfectorAccessService;
use common\components\Clinical\Inpatient\Service\InternacionEpicrisisPlantillaAdminService;
use common\models\ServiciosEfector;
use frontend\filters\SisseActionFilter;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * ABM web de plantillas de epicrisis para el efector en sesión.
 *
 * @no_intent_catalog
 */
class InternacionEpicrisisPlantillaController extends Controller
{
    private InternacionEpicrisisPlantillaAdminService $admin;

    public function init(): void
    {
        parent::init();
        $this->admin = new InternacionEpicrisisPlantillaAdminService();
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => SisseActionFilter::class,
                'only' => ['index', 'create', 'update', 'toggle-activo'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_PACIENTE],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'toggle-activo' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $idEfector = $this->requireIdEfector();
        $incluirInactivas = (bool) Yii::$app->request->get('incluir_inactivas', true);
        $plantillas = $this->admin->listarAdmin($idEfector, $incluirInactivas);

        return $this->render('index', [
            'plantillas' => $plantillas,
            'idEfector' => $idEfector,
            'incluirInactivas' => $incluirInactivas,
            'placeholders' => InternacionEpicrisisPlantillaAdminService::PLACEHOLDERS,
        ]);
    }

    public function actionCreate()
    {
        $idEfector = $this->requireIdEfector();
        $model = [
            'nombre' => '',
            'cuerpo' => '',
            'id_servicio' => '',
            'orden' => 0,
            'activo' => true,
        ];

        if (Yii::$app->request->isPost) {
            try {
                $this->admin->crear(
                    Yii::$app->request->post(),
                    $idEfector,
                    (bool) (Yii::$app->user->isSuperadmin ?? false)
                );
                Yii::$app->session->setFlash('success', 'Plantilla creada correctamente.');

                return $this->redirect(['index']);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                $model = array_merge($model, Yii::$app->request->post());
            }
        }

        return $this->render('create', [
            'model' => $model,
            'servicios' => $this->serviciosOptions($idEfector),
            'placeholders' => InternacionEpicrisisPlantillaAdminService::PLACEHOLDERS,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $idEfector = $this->requireIdEfector();
        try {
            $plantilla = $this->admin->obtener($id, $idEfector);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        if (!(bool) ($plantilla['editable'] ?? false)) {
            throw new NotFoundHttpException('No puede editar esta plantilla.');
        }

        if (Yii::$app->request->isPost) {
            try {
                $this->admin->actualizar(
                    $id,
                    Yii::$app->request->post(),
                    $idEfector,
                    (bool) (Yii::$app->user->isSuperadmin ?? false)
                );
                Yii::$app->session->setFlash('success', 'Plantilla actualizada.');

                return $this->redirect(['index']);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                $plantilla = array_merge($plantilla, Yii::$app->request->post());
            }
        }

        return $this->render('update', [
            'plantilla' => $plantilla,
            'servicios' => $this->serviciosOptions($idEfector),
            'placeholders' => InternacionEpicrisisPlantillaAdminService::PLACEHOLDERS,
        ]);
    }

    public function actionToggleActivo(int $id)
    {
        $idEfector = $this->requireIdEfector();
        $activar = (int) Yii::$app->request->post('activar', 0) === 1;
        $isSuperadmin = (bool) (Yii::$app->user->isSuperadmin ?? false);

        try {
            if ($activar) {
                $this->admin->activar($id, $idEfector, $isSuperadmin);
                Yii::$app->session->setFlash('success', 'Plantilla activada.');
            } else {
                $this->admin->desactivar($id, $idEfector, $isSuperadmin);
                Yii::$app->session->setFlash('success', 'Plantilla desactivada.');
            }
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    private function requireIdEfector(): int
    {
        try {
            return EfectorAccessService::assertAndResolveIdEfector('InternacionEpicrisisPlantilla.admin', []);
        } catch (DomainOperationForbiddenException $e) {
            throw new NotFoundHttpException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.');
        }
    }

    /**
     * @return array<int|string, string>
     */
    private function serviciosOptions(int $idEfector): array
    {
        $rows = ServiciosEfector::serviciosPorEfector($idEfector);
        $options = ['' => '— Todos los servicios —'];
        foreach ($rows as $se) {
            $servicio = $se->servicio ?? null;
            $label = $servicio
                ? (string) ($servicio->descripcion ?? $servicio->nombre ?? $se->id_servicio)
                : (string) $se->id_servicio;
            $options[(int) $se->id_servicio] = $label;
        }

        return $options;
    }
}

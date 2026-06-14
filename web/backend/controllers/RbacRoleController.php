<?php

namespace backend\controllers;

use common\components\Core\Permission\RbacRoleAdminService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * CRUD de roles RBAC e asignación de intents al rol.
 */
class RbacRoleController extends Controller
{
    private const VIEW_PREFIX = '@backend/views/rbac-role';

    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceBackendAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $service = new RbacRoleAdminService();

        return $this->render(self::VIEW_PREFIX . '/index', [
            'roles' => $service->listAll(),
        ]);
    }

    public function actionCreate()
    {
        $service = new RbacRoleAdminService();
        $model = ['name' => '', 'description' => ''];

        if (Yii::$app->request->isPost) {
            $model['name'] = trim((string) Yii::$app->request->post('name', ''));
            $model['description'] = trim((string) Yii::$app->request->post('description', ''));
            try {
                $service->create($model['name'], $model['description']);
                Yii::$app->session->setFlash('success', 'Rol creado.');

                return $this->redirect(['update', 'name' => $model['name']]);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            } catch (\Throwable $e) {
                Yii::$app->session->setFlash('error', 'No se pudo crear el rol.');
            }
        }

        return $this->render(self::VIEW_PREFIX . '/create', ['model' => $model]);
    }

    public function actionUpdate(string $name)
    {
        $service = new RbacRoleAdminService();
        $role = $service->find($name);
        if ($role === null) {
            throw new NotFoundHttpException('Rol no encontrado.');
        }

        $intents = $service->intentPermissionsForRole($name);

        if (Yii::$app->request->isPost) {
            try {
                $service->updateDescription(
                    $name,
                    trim((string) Yii::$app->request->post('description', ''))
                );
                $selected = Yii::$app->request->post('permissions', []);
                if (!is_array($selected)) {
                    $selected = [];
                }
                $service->saveIntentPermissionsForRole($name, $selected);
                Yii::$app->session->setFlash('success', 'Rol actualizado.');

                return $this->redirect(['update', 'name' => $name]);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            } catch (\Throwable $e) {
                Yii::$app->session->setFlash('error', 'No se pudo actualizar el rol.');
            }
            $intents = $service->intentPermissionsForRole($name);
        }

        return $this->render(self::VIEW_PREFIX . '/update', [
            'role' => $role,
            'intents' => $intents,
        ]);
    }

    public function actionDelete(string $name)
    {
        $service = new RbacRoleAdminService();
        try {
            $service->delete($name);
            Yii::$app->session->setFlash('success', 'Rol eliminado.');
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', 'No se pudo eliminar el rol.');
        }

        return $this->redirect(['index']);
    }
}

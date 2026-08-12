<?php

namespace admin\controllers;

use common\components\Platform\Assistant\Catalog\AssistantShortcutGroupRegistry;
use common\models\Platform\AssistantShortcutGroup;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Admin: títulos y orden de grupos de atajos (modo RBAC).
 *
 * Los intents visibles los define rol ↔ intent en {@see PermissionCatalogController}.
 */
class ShortcutGroupController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'reseed-yaml' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index', [
            'rows' => AssistantShortcutGroupRegistry::allOrdered(),
        ]);
    }

    /**
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new AssistantShortcutGroup();
        $model->sort_order = $this->suggestNextSortOrder();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            AssistantShortcutGroupRegistry::invalidateCache();
            Yii::$app->session->setFlash('success', 'Grupo «' . $model->group_id . '» creado.');

            return $this->redirect(['index']);
        }

        return $this->render('form', [
            'model' => $model,
            'isNew' => true,
        ]);
    }

    /**
     * @return string|\yii\web\Response
     */
    public function actionUpdate(string $group_id)
    {
        $model = $this->findModel($group_id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            AssistantShortcutGroupRegistry::invalidateCache();
            Yii::$app->session->setFlash('success', 'Grupo actualizado.');

            return $this->redirect(['index']);
        }

        return $this->render('form', [
            'model' => $model,
            'isNew' => false,
        ]);
    }

    public function actionDelete(string $group_id)
    {
        $model = $this->findModel($group_id);
        $model->delete();
        AssistantShortcutGroupRegistry::invalidateCache();
        Yii::$app->session->setFlash('success', 'Grupo «' . Html::encode($group_id) . '» eliminado.');

        return $this->redirect(['index']);
    }

    public function actionReseedYaml()
    {
        try {
            $result = AssistantShortcutGroupRegistry::reseedFromYamlFallback();
            Yii::$app->session->setFlash(
                'success',
                sprintf(
                    'Importado desde YAML: %d nuevos, %d actualizados, %d omitidos.',
                    $result['inserted'],
                    $result['updated'],
                    $result['skipped']
                )
            );
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    private function findModel(string $groupId): AssistantShortcutGroup
    {
        $groupId = trim($groupId);
        $model = AssistantShortcutGroup::findOne(['group_id' => $groupId]);
        if ($model === null) {
            throw new NotFoundHttpException('Grupo no encontrado.');
        }

        return $model;
    }

    private function suggestNextSortOrder(): int
    {
        $max = AssistantShortcutGroup::find()->max('sort_order');

        return max(0, (int) $max) + 10;
    }
}

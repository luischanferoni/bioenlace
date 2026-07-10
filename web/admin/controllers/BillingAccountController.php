<?php

namespace admin\controllers;

use common\components\Domain\Organization\Service\Billing\BillingAccountService;
use common\components\Domain\Organization\Service\Entitlement\EfectorEncounterEntitlementService;
use common\models\BillingAccount;
use common\models\BillingAccountEncounterEntitlement;
use common\models\BillingAccountEfector;
use common\models\busquedas\BillingAccountBusqueda;
use common\models\Clinical\EncounterDefinition;
use common\models\Efector;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Admin: cuentas de licencia (Ministerio / Red / Efector) y pool de max_pes.
 */
class BillingAccountController extends Controller
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
                    'delete' => ['POST'],
                    'detach-efector' => ['POST'],
                    'deactivate-entitlement' => ['POST'],
                    'update-membership-role' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new BillingAccountBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        $summary = EfectorEncounterEntitlementService::contractSummaryForAccount((int) $model->id);
        $members = BillingAccountEfector::find()
            ->where(['id_billing_account' => $model->id, 'deleted_at' => null])
            ->with('efector')
            ->all();

        return $this->render('view', [
            'model' => $model,
            'summary' => $summary,
            'members' => $members,
            'sellableClasses' => ['AMB', 'EMER', 'IMP'],
            'classLabels' => EncounterDefinition::ENCOUNTER_CLASS,
        ]);
    }

    public function actionCreate()
    {
        $model = new BillingAccount();
        $model->tipo = BillingAccount::TIPO_EFECTOR;
        $model->activo = 1;

        if (Yii::$app->request->isPost) {
            try {
                $model = BillingAccountService::createAccount(Yii::$app->request->post('BillingAccount', []));
                Yii::$app->session->setFlash('success', 'Cuenta creada.');

                return $this->redirect(['view', 'id' => $model->id]);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                $model->load(Yii::$app->request->post());
            }
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            try {
                BillingAccountService::updateAccount($model, Yii::$app->request->post('BillingAccount', []));
                Yii::$app->session->setFlash('success', 'Cuenta actualizada.');

                return $this->redirect(['view', 'id' => $model->id]);
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                $model->load(Yii::$app->request->post());
            }
        }

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Cuenta eliminada.');

        return $this->redirect(['index']);
    }

    public function actionAttachEfector($id)
    {
        $model = $this->findModel($id);
        $idEfector = (int) Yii::$app->request->post('id_efector', 0);
        $rol = (string) Yii::$app->request->post('rol_membresia', BillingAccountEfector::ROL_POOL);
        try {
            BillingAccountService::attachEfector((int) $model->id, $idEfector, $rol);
            Yii::$app->session->setFlash('success', 'Efector asociado.');
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionUpdateMembershipRole($id, $id_efector)
    {
        $model = $this->findModel($id);
        $rol = (string) Yii::$app->request->post('rol_membresia', BillingAccountEfector::ROL_POOL);
        try {
            BillingAccountService::updateMembershipRole((int) $model->id, (int) $id_efector, $rol);
            Yii::$app->session->setFlash('success', 'Rol actualizado.');
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionDetachEfector($id, $id_efector)
    {
        $model = $this->findModel($id);
        BillingAccountService::detachEfector((int) $model->id, (int) $id_efector);
        Yii::$app->session->setFlash('success', 'Efector desasociado.');

        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionSaveEntitlement($id)
    {
        $model = $this->findModel($id);
        $post = Yii::$app->request->post();
        $class = (string) ($post['encounter_class'] ?? '');
        try {
            BillingAccountService::upsertEntitlement((int) $model->id, $class, [
                'max_pes' => $post['max_pes'] ?? null,
                'dictado_incluido' => !empty($post['dictado_incluido']),
                'videollamada_permitida' => !empty($post['videollamada_permitida']),
                'activo' => 1,
            ]);
            EfectorEncounterEntitlementService::syncPendingDowngradeForAccount((int) $model->id);
            Yii::$app->session->setFlash('success', 'Clase actualizada.');
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionDeactivateEntitlement($id, $encounter_class)
    {
        $model = $this->findModel($id);
        BillingAccountService::deactivateEntitlement((int) $model->id, (string) $encounter_class);
        Yii::$app->session->setFlash('success', 'Clase quitada del contrato.');

        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * Autocomplete efectores (JSON). Con rol=POOL excluye quienes ya tienen pool.
     */
    public function actionEfectorOptions($q = '', $rol = 'POOL')
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $q = trim((string) $q);
        $rol = strtoupper(trim((string) $rol));
        if ($rol !== BillingAccountEfector::ROL_AFILIADO) {
            $rol = BillingAccountEfector::ROL_POOL;
        }

        $query = Efector::find()
            ->select(['id_efector', 'nombre'])
            ->where(['estado' => 'ACTIVO'])
            ->orderBy(['nombre' => SORT_ASC])
            ->limit(30);

        if ($rol === BillingAccountEfector::ROL_POOL) {
            $withPool = BillingAccountEfector::find()
                ->select(['id_efector'])
                ->where([
                    'deleted_at' => null,
                    'rol_membresia' => BillingAccountEfector::ROL_POOL,
                ])
                ->column();
            if ($withPool !== []) {
                $query->andWhere(['not in', 'id_efector', $withPool]);
            }
        }

        if ($q !== '') {
            $query->andWhere(['like', 'nombre', $q]);
        }

        $out = [];
        foreach ($query->asArray()->all() as $row) {
            $out[] = [
                'id' => (int) $row['id_efector'],
                'text' => $row['nombre'] . ' (#' . $row['id_efector'] . ')',
            ];
        }

        return ['results' => $out];
    }

    protected function findModel($id): BillingAccount
    {
        $model = BillingAccount::findOne(['id' => (int) $id, 'deleted_at' => null]);
        if ($model === null) {
            throw new NotFoundHttpException('Cuenta no encontrada.');
        }

        return $model;
    }
}

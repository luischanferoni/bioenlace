<?php

namespace admin\controllers;

use common\components\Domain\Organization\Service\Billing\BillingMembershipSwitchService;
use common\components\Domain\Organization\Service\Billing\MinistrySignupRequestService;
use common\models\BillingAccount;
use common\models\BillingSignupRequest;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Cola de solicitudes de alta comercial (ministerio / cobertura pool).
 */
class BillingSignupRequestController extends Controller
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
                    'approve' => ['POST'],
                    'reject' => ['POST'],
                    'approve-pool-move' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => BillingSignupRequest::find()
                ->where(['deleted_at' => null])
                ->orderBy(['status' => SORT_ASC, 'id' => SORT_DESC]),
            'pagination' => ['pageSize' => 30],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
            'ministerios' => BillingAccount::find()
                ->where(['tipo' => BillingAccount::TIPO_MINISTERIO, 'deleted_at' => null, 'activo' => 1])
                ->orderBy(['nombre' => SORT_ASC])
                ->all(),
        ]);
    }

    public function actionApprove($id)
    {
        $idAccount = (int) Yii::$app->request->post('id_billing_account', 0);
        try {
            MinistrySignupRequestService::approve(
                (int) $id,
                (int) Yii::$app->user->id,
                $idAccount > 0 ? $idAccount : null
            );
            Yii::$app->session->setFlash('success', 'Solicitud aprobada.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionReject($id)
    {
        try {
            MinistrySignupRequestService::reject(
                (int) $id,
                (int) Yii::$app->user->id,
                Yii::$app->request->post('notas')
            );
            Yii::$app->session->setFlash('success', 'Solicitud rechazada.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionApprovePoolMove($id)
    {
        $model = $this->findModel($id);
        if ($model->tipo !== BillingSignupRequest::TIPO_EFECTOR
            || (int) $model->id_efector <= 0
            || (int) $model->id_billing_account_ministerio <= 0
        ) {
            Yii::$app->session->setFlash('error', 'La solicitud no es un movimiento de pool válido.');

            return $this->redirect(['view', 'id' => $id]);
        }

        try {
            BillingMembershipSwitchService::approvePoolMoveToMinisterio(
                (int) $model->id_efector,
                (int) $model->id_billing_account_ministerio
            );
            $model->status = BillingSignupRequest::STATUS_APPROVED;
            $model->reviewed_by = (int) Yii::$app->user->id;
            $model->reviewed_at = date('Y-m-d H:i:s');
            $model->save(false);
            Yii::$app->session->setFlash('success', 'Pool movido al ministerio.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    private function findModel($id): BillingSignupRequest
    {
        $model = BillingSignupRequest::findOne(['id' => (int) $id, 'deleted_at' => null]);
        if ($model === null) {
            throw new NotFoundHttpException('Solicitud no encontrada.');
        }

        return $model;
    }
}

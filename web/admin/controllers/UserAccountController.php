<?php

namespace admin\controllers;

use common\components\Platform\Core\Auth\StaffAccountInvitationService;
use common\models\search\UserSearch;
use common\models\User;
use common\components\Platform\Ui\Grid\GridAdminActionsTrait;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * CRUD de cuentas (`user`) en admin. Reemplaza webvimark user-management/user.
 */
class UserAccountController extends Controller
{
    use GridAdminActionsTrait;

    private const VIEW_PREFIX = '@admin/views/user-management/user';

    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'bulk-activate' => ['POST'],
                    'bulk-deactivate' => ['POST'],
                    'bulk-delete' => ['POST'],
                    'grid-page-size' => ['POST'],
                    'invitation-send-email' => ['POST'],
                    'invitation-generate-code' => ['POST'],
                    'invitation-revoke' => ['POST'],
                ],
            ],
        ];
    }

    protected function gridModelClass(): string
    {
        return User::class;
    }

    public function actionIndex()
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render(self::VIEW_PREFIX . '/index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render(self::VIEW_PREFIX . '/view', [
            'model' => $this->findUser((int) $id),
        ]);
    }

    public function actionCreate()
    {
        $model = new User(['scenario' => User::SCENARIO_INVITE]);

        if ($model->load(Yii::$app->request->post())
            && StaffAccountInvitationService::saveInvitedUser($model, $this->actorUserId())
        ) {
            return $this->redirect(['invitation', 'id' => $model->id]);
        }

        return $this->render(self::VIEW_PREFIX . '/create', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findUser((int) $id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render(self::VIEW_PREFIX . '/update', ['model' => $model]);
    }

    public function actionChangePassword($id)
    {
        $model = $this->findUser((int) $id);
        $model->scenario = 'changePassword';

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render(self::VIEW_PREFIX . '/changePassword', ['model' => $model]);
    }

    /**
     * Alta de usuario vinculado a persona en sesión (flujo RRHH / personas).
     */
    public function actionCrear()
    {
        $model = new User(['scenario' => User::SCENARIO_INVITE]);

        $personaRaw = Yii::$app->session->get('persona');
        $persona = is_string($personaRaw) ? @unserialize($personaRaw) : null;
        if ($persona !== null && isset($persona->nombre, $persona->apellido)) {
            $model->username = strtolower($persona->nombre . '' . $persona->apellido);
        }

        if (Yii::$app->request->isPost
            && $model->load(Yii::$app->request->post())
            && StaffAccountInvitationService::saveInvitedUser($model, $this->actorUserId())
            && $persona !== null
        ) {
            $persona->scenario = 'scenarioactualizaruser';
            $persona->id_user = $model->id;
            $persona->save();
            Yii::$app->session->set('persona', serialize($persona));

            return $this->redirect(['invitation', 'id' => $model->id]);
        }

        return $this->render(self::VIEW_PREFIX . '/create', ['model' => $model]);
    }

    public function actionInvitation($id, $continue = null)
    {
        $model = $this->findUser((int) $id);

        return $this->render(self::VIEW_PREFIX . '/invitation', [
            'model' => $model,
            'logs' => \common\models\UserAccountInvitationLog::listForUser((int) $model->id),
            'continueUrl' => $continue,
        ]);
    }

    public function actionInvitationSendEmail($id)
    {
        $model = $this->findUser((int) $id);

        try {
            if (StaffAccountInvitationService::sendEmailInvitation($model, $this->actorUserId())) {
                Yii::$app->session->setFlash('invitation_email_status', 'Se envió la invitación por e-mail.');
            } else {
                Yii::$app->session->setFlash('invitation_email_status', 'No se pudo enviar el e-mail. Verificá la configuración del mailer.');
            }
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('invitation_email_status', $e->getMessage());
        }

        return $this->redirect(['invitation', 'id' => $model->id]);
    }

    public function actionInvitationGenerateCode($id)
    {
        $model = $this->findUser((int) $id);

        try {
            $plain = StaffAccountInvitationService::generateActivationCode($model, $this->actorUserId());
            Yii::$app->session->setFlash('activation_code_plain', $plain);
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('invitation_email_status', $e->getMessage());
        }

        return $this->redirect(['invitation', 'id' => $model->id]);
    }

    public function actionInvitationRevoke($id)
    {
        $model = $this->findUser((int) $id);

        try {
            StaffAccountInvitationService::revokeInvitation($model, $this->actorUserId());
            Yii::$app->session->setFlash('invitation_email_status', 'Invitación revocada.');
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('invitation_email_status', $e->getMessage());
        }

        return $this->redirect(['invitation', 'id' => $model->id]);
    }

    private function actorUserId(): ?int
    {
        if (Yii::$app->user->isGuest) {
            return null;
        }

        return (int) Yii::$app->user->id;
    }

    public function actionImpersonate($id)
    {
        $dir = Yii::getAlias('@frontend') . '/runtime/impersonation';
        FileHelper::createDirectory($dir);
        file_put_contents($dir . '/a.txt', (string) (int) $id, LOCK_EX);

        $url = Yii::$app->urlManager->createAbsoluteUrl(['site/impersonate']);
        $url = str_replace('/admin/', '/', $url);

        return $this->redirect($url);
    }

    protected function findUser(int $id): User
    {
        $model = User::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        return $model;
    }
}

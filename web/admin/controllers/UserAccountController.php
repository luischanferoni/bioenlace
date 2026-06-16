<?php

namespace admin\controllers;

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
        $model = new User(['scenario' => 'newUser']);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
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
        $model = new User(['scenario' => 'newUser']);

        $personaRaw = Yii::$app->session->get('persona');
        $persona = is_string($personaRaw) ? @unserialize($personaRaw) : null;
        if ($persona !== null && isset($persona->nombre, $persona->apellido)) {
            $model->username = strtolower($persona->nombre . '' . $persona->apellido);
        }

        if (Yii::$app->request->isPost
            && $model->load(Yii::$app->request->post())
            && $model->save()
            && $persona !== null
        ) {
            $persona->scenario = 'scenarioactualizaruser';
            $persona->id_user = $model->id;
            $persona->save();
            Yii::$app->session->set('persona', serialize($persona));

            return $this->redirect(['/user-management/user/view', 'id' => $model->id]);
        }

        return $this->render(self::VIEW_PREFIX . '/create', ['model' => $model]);
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

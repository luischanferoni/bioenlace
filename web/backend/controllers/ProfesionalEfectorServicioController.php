<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\Response;

use common\models\busquedas\ProfesionalEfectorServicioBusqueda;
use common\models\ProfesionalEfectorServicio;
use common\models\Persona;
use common\models\Servicio;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService;

/**
 * Gestión backend de filas PES (`profesional_efector_servicio`): listados, admin efector, live search.
 */
class ProfesionalEfectorServicioController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Listado PES.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ProfesionalEfectorServicioBusqueda();

        if (!Yii::$app->user->getIdEfector()) {
            $searchModel->scenario = ProfesionalEfectorServicioBusqueda::EFECTOR_SEARCH;
        } else {
            $searchModel->id_efector = (int) Yii::$app->user->getIdEfector();
        }
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id_rr_hh, $id_efector)
    {
        throw new NotFoundHttpException('Vista descontinuada: use el listado PES (índice).');
    }

    /**
     * Administra a la persona como administrador de Efector
     * Permite asignarle y quitarle la administrador de multiples Efectores
     * @return mixed
     */

    public function actionCreateAdminEfectorConRrhh($id)
    {
        $persona = Persona::find()->where(['id_user' => $id])->one();

        if (!$persona) {
            throw new NotFoundHttpException('Este usuario no posee una persona asociada');
        }
        $error = false;
        // Este es el servicio que le otorga el rol de AdminEfector
        // TODO: que el string AdminEfector venga de una constante
        $admin_efector_servicio = Servicio::find()->where(['item_name' => 'AdminEfector'])->one();
        if ($admin_efector_servicio === null) {
            throw new NotFoundHttpException('Servicio AdminEfector no configurado.');
        }
        $idServAdmin = (int) $admin_efector_servicio->id_servicio;

        $pesAdminRows = ProfesionalEfectorServicio::find()
            ->select(['id_efector'])
            ->where([
                'id_persona' => $persona->id_persona,
                'id_servicio' => $idServAdmin,
                'deleted_at' => null,
            ])
            ->asArray()
            ->all();
        $persona_efectores = ArrayHelper::getColumn($pesAdminRows, 'id_efector');
        //var_dump($persona_efectores);die;
        if (Yii::$app->request->post()) {

            $transaction = \Yii::$app->db->beginTransaction();
            try {
                $post_efectores = Yii::$app->request->post('efectores') ? Yii::$app->request->post('efectores') : [];
                $id_efectores_a_crear = array_diff($post_efectores, $persona_efectores);

                foreach ($id_efectores_a_crear as $id_efector_a_crear) {
                    try {
                        ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
                            (int) $persona->id_persona,
                            (int) $id_efector_a_crear,
                            $idServAdmin
                        );
                    } catch (\Throwable $e) {
                        $error = [$e->getMessage()];
                        throw new Exception($e->getMessage(), 0, $e);
                    }
                }
                $id_efectores_a_eliminar = array_diff($persona_efectores, $post_efectores);
                if (count($id_efectores_a_eliminar) > 0) {
                    foreach ($id_efectores_a_eliminar as $id_efector_a_eliminar) {
                        $pesAdm = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio(
                            (int) $persona->id_persona,
                            (int) $id_efector_a_eliminar,
                            $idServAdmin
                        );
                        if ($pesAdm !== null) {
                            $pesAdm->delete();
                        }
                    }
                }

                $transaction->commit();
                return $this->redirect(['user-management/user/view', 'id' => $id]);
            } catch (Exception $e) {
                //var_dump($e->getMessage());die;
                $transaction->rollBack();
            }
        }

        return $this->render('create_admin_efector', [
            'persona' => $persona,
            'persona_efectores' => $persona_efectores,
            'error' => $error,
        ]);
    }

    public function actionCreateAdminEfector($id_rr_hh)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $servicioAdminEfector = Servicio::find()->where(['item_name' => 'AdminEfector'])->one();
        if ($servicioAdminEfector === null) {
            return Json::encode(['error' => true, 'message' => 'Servicio AdminEfector no configurado.']);
        }
        $idServ = (int) $servicioAdminEfector->id_servicio;

        $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromIdRrhh((int) $id_rr_hh);
        if ($idPersona === null || $idPersona <= 0) {
            return Json::encode(['error' => true, 'message' => 'RRHH no encontrado.']);
        }
        $idEfectores = ProfesionalEfectorServicio::find()
            ->select(['id_efector'])
            ->distinct()
            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
            ->column();
        foreach ($idEfectores as $idEf) {
            try {
                ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
                    $idPersona,
                    (int) $idEf,
                    $idServ
                );
            } catch (\Throwable $e) {
                return Json::encode(['error' => true, 'message' => $e->getMessage()]);
            }
        }

        return "ok";

    }

    public function actionRemoveAdminEfector($id_rr_hh)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $servicioAdminEfector = Servicio::find()->where(['item_name' => 'AdminEfector'])->one();
        if ($servicioAdminEfector === null) {
            return "ok";
        }
        $idServ = (int) $servicioAdminEfector->id_servicio;

        $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromIdRrhh((int) $id_rr_hh);
        if ($idPersona !== null && $idPersona > 0) {
            foreach (
                ProfesionalEfectorServicio::find()
                    ->where([
                        'id_persona' => $idPersona,
                        'id_servicio' => $idServ,
                        'deleted_at' => null,
                    ])
                    ->all() as $pesAdm
            ) {
                $pesAdm->delete();
            }
        }

        return "ok";
    }

    /**
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id_rr_hh, $id_efector)
    {
        throw new NotFoundHttpException('Actualización descontinuada: gestione asignaciones vía PES.');
    }

    /**
     * Autocomplete de personas con PES en el efector.
     */
    public function actionPersonasLiveSearch($q = null, $idEfector)
    {
        $out = ['results' => ['id' => '', 'text' => '']];

        if (is_null($q)) {
            return $out;
        }

        $data = ProfesionalEfectorServicio::personasConPesLiveSearch($q, (int) $idEfector);

        return Json::encode(['results' => $data]);
    }

    /**
     * @param mixed $id id PES (primera fila del grupo persona+efector a retirar en bloque)
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $pes = ProfesionalEfectorServicio::findOne(['id' => (int) $id, 'deleted_at' => null]);
        if ($pes === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $idPersona = (int) $pes->id_persona;
        $idEfector = (int) $pes->id_efector;
        foreach (
            ProfesionalEfectorServicio::find()
                ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
                ->all() as $row
        ) {
            $row->delete();
        }

        return $this->redirect(['index']);
    }

}

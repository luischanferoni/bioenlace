<?php

namespace frontend\controllers;

use Yii;
use yii\base\Exception;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\helpers\ArrayHelper;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\filters\AccessRule;

use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionRepository;
use common\models\busquedas\SegNivelInternacionBusqueda;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraCama;
use common\models\Persona;
use common\models\Setup;
use common\models\CoberturaMedica;
use common\models\Efector;
use common\models\Servicio;

use frontend\filters\SisseActionFilter;
use frontend\controllers\MpiApiController;
use common\models\Telefono;
use frontend\components\CPacienteHistorial;
use frontend\components\PacienteHistorial;
use common\models\ProfesionalEfectorServicio;
use common\components\Clinical\PatientHistoriaUrl;
use common\components\Inpatient\InternacionMapaWebContext;
use common\models\Clinical\Encounter;
use webvimark\modules\UserManagement\models\User;

/**
 * InternacionController implements the CRUD actions for SegNivelInternacion model.
 */
class InternacionController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['index', 'espera'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_PACIENTE],
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
     * Lists all SegNivelInternacion models.
     * @return mixed
     * @no_intent_catalog
    */
    public function actionIndex()
    {
        if (Yii::$app->user->getEncounterClass() === Encounter::ENCOUNTER_CLASS_IMP) {
            return $this->redirect(['site/pacientes']);
        }

        return $this->render('hub');
    }

    /**
     * Rondas all SegNivelInternacion models.
     * @return mixed
     * @no_intent_catalog
    */
    public function actionRonda()
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);
        $pacienteInternado = false;

        if (isset($persona->id_persona) && SegNivelInternacion::personaInternada($persona->id_persona)) {

            $pacienteInternado = true;
        }

        $pisos = new InfraestructuraPiso();
        $efector = Yii::$app->user->getIdEfector();


        $pisos_efector = $pisos->pisosPorEfector($efector);
        return $this->render('ronda', [
            'pisos_efector' => $pisos_efector

        ]);
    }

    public function formatearDatosProfesional($modeloPes)
    {
        $array_profesiones = [];
        if (isset($modeloPes->persona->profesionalSalud)) {
            foreach ($modeloPes->persona->profesionalSalud as $profesional) {
                if (isset($profesional->especialidad)) {
                    $array_profesiones[$profesional->profesion->nombre][] = $profesional->especialidad->nombre;
                } else {
                    $array_profesiones[$profesional->profesion->nombre] = [];
                }
            }
        }
        return $array_profesiones;
    }

    /**
     * Displays a single SegNivelInternacion model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @no_intent_catalog
    */
    public function actionView($id)
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);
        $idPersona = (isset($persona->id_persona)) ? $persona->id_persona : null;

        $model = $this->findModel($id);

        if ($idPersona != $model->id_persona) {
            $model_persona = Persona::findOne($model->id_persona);
            $session = Yii::$app->getSession();
            $session->set('persona', serialize($model_persona));
        }

        $paciente = $model->paciente;

        $model_profesional_pes = null;
        if ((int) ($model->id_profesional_efector_servicio ?? 0) > 0) {
            $model_profesional_pes = ProfesionalEfectorServicio::findOne([
                'id' => (int) $model->id_profesional_efector_servicio,
                'deleted_at' => null,
            ]);
        }
        $datosProfesional = $model_profesional_pes !== null ? $this->formatearDatosProfesional($model_profesional_pes) : [];

        $puedeAtender = Servicio::puedeAtender(Yii::$app->user->getServicioActual());

        $altaCtx = [];
        if ($model->enableExternacion()) {
            try {
                $idEfector = (int) Yii::$app->user->getIdEfector();
                if ($idEfector > 0) {
                    $altaCtx = (new \common\components\Inpatient\InternacionAltaEstructuradaService())
                        ->contextoAlta($model, $idEfector);
                }
            } catch (\Throwable $e) {
                Yii::warning('Contexto alta API: ' . $e->getMessage(), __METHOD__);
            }
        }

        $cambioCtx = [];
        if ($model->enableCambioCama()) {
            try {
                $idEfector = (int) Yii::$app->user->getIdEfector();
                if ($idEfector > 0) {
                    $cambioCtx = (new \common\components\Inpatient\InternacionCambioCamaService())
                        ->contextoCambioCama($model, $idEfector);
                }
            } catch (\Throwable $e) {
                Yii::warning('Contexto cambio cama API: ' . $e->getMessage(), __METHOD__);
            }
        }

        // Captura clínica: timeline + formulario encounter (IMP), no pestañas MVC legacy.
        $urlCapturaClinica = null;
        if ($puedeAtender && !$model->internacionConAlta()) {
            $urlCapturaClinica = PatientHistoriaUrl::captura(
                (int) $model->id_persona,
                Encounter::PARENT_INTERNACION,
                (int) $model->id
            );
        }

        return $this->render('view', [
            'model' => $model,
            'model_profesional_pes' => $model_profesional_pes,
            'datosProfesional' => $datosProfesional,
            'type' => Yii::$app->getRequest()->getQueryParam('type'),
            'urlCapturaClinica' => $urlCapturaClinica,
            'puedeAtender' => $puedeAtender,
            'altaCtx' => $altaCtx,
            'cambioCtx' => $cambioCtx,
        ]);
    }

    /**
     * Ingreso vía API (sustituye formulario MVC legacy).
     *
     * @return mixed
     * @no_intent_catalog
     */
    public function actionIngreso()
    {
        $raw = Yii::$app->session['persona'] ?? null;
        if ($raw === null) {
            Yii::$app->session->setFlash('error', 'Seleccione un paciente antes del ingreso.');
            return $this->redirect(['index']);
        }
        $persona = unserialize($raw);
        if (!$persona instanceof Persona) {
            Yii::$app->session->setFlash('error', 'Sesión de paciente inválida.');
            return $this->redirect(['index']);
        }

        $get = Yii::$app->request->get();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idCama = isset($get['id']) ? (int) $get['id'] : null;
        $idGuardia = isset($get['id_guardia']) ? (int) $get['id_guardia'] : null;

        try {
            $ctx = (new \common\components\Inpatient\InternacionIngresoService())->contextoIngreso(
                (int) $persona->id_persona,
                $idEfector,
                $idCama > 0 ? $idCama : null,
                $idGuardia > 0 ? $idGuardia : null
            );
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['index']);
        }

        return $this->render('ingreso', [
            'persona' => $persona,
            'ctx' => $ctx,
        ]);
    }

    /**
     * @deprecated Redirige a {@see actionIngreso()}.
     * @return \yii\web\Response
     * @no_intent_catalog
     */
    public function actionCreate()
    {
        $params = Yii::$app->request->get();
        return $this->redirect(array_merge(['ingreso'], $params));
    }

    /**
     * Updates an existing SegNivelInternacion model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @no_intent_catalog
    */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        /*
        if (!$model->load(Yii::$app->request->post())) {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
        */

        $model->fecha_fin = date("d/m/Y");

        if ($this->request->isPost) {
            $model->load($this->request->post());
            $model->scenario = SegNivelInternacion::EGRESO_PACIENTE;
            $validar = $model->validate();
            if ($validar) {
                try {
                    SegNivelInternacionRepository::doExternacion($model);
                    return $this->redirect(['porpersona', 'idpersona' => $model->id_persona]);
                } catch (Exception $e) {
                    $model->addError('hora_fin', 'Ocurrió un error inesperado');
                }
            }
        }
        $context = [
            'model' => $model,
            'modal_id' => '',
        ];
        if ($this->request->isAjax) {
            $context['modal_id'] = '#modal_internacion_alta';
            return $this->renderAjax('update', $context);
        }
        return $this->render('update', $context);
    }

    /**
     * Deletes an existing SegNivelInternacion model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @no_intent_catalog
    */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the SegNivelInternacion model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacion the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SegNivelInternacion::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Lists all SegNivelInternacion models.
     * @return mixed
     * @no_intent_catalog
    */
    public function actionFinalizadas()
    {
        $searchModel = new SegNivelInternacionBusqueda();
        $dataProvider = $searchModel->searchFinalizadas(Yii::$app->request->queryParams);
        $efector = Yii::$app->user->getIdEfector();

        return $this->render('finalizadas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider
        ]);
    }

    /**
     * Lists SegNivelInternacion por persona models.
     * @return mixed
     * @no_intent_catalog
    */
    public function actionPorpersona($idpersona)
    {
        if (!$idpersona) return $this->redirect(['/personas/view', 'id' => $idpersona]);
        $model_persona = Persona::findOne($idpersona);
        if (!$model_persona)  return $this->redirect(['/personas/view', 'id' => $idpersona]);
        $searchModel = new SegNivelInternacionBusqueda();
        $dataProvider = $searchModel->searchPorPersona(Yii::$app->request->queryParams, $idpersona);
        $efector = Yii::$app->user->getIdEfector();

        return $this->render('porpersona', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'persona' => $model_persona
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionMostrarDatosAcompaniante($id_internacion)
    {
        $model = $this->findModel($id_internacion);

        return $this->renderAjax('_datosAcompaniante', [
            'model' => $model
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionListado()
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);

        $pacienteInternado = false;

        $pisos = new InfraestructuraPiso();
        $idEfector = Yii::$app->user->getIdEfector();

        $pisos_efector = $pisos->pisosPorEfector($idEfector);

        return $this->render('listado', [
            'pisos_efector' => $pisos_efector,
        ]);
    }
}

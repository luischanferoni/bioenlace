<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\MethodNotAllowedHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\Pagination;
use common\models\AbreviaturasMedicas;
use common\components\ProcesadorTextoMedico;

/**
 * Controlador para administración de abreviaturas médicas
 */
class AbreviaturasController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'sugerencias-pendientes', 'aprobar', 'rechazar'],
                        'allow' => true,
                        'roles' => ['@'], // Solo usuarios autenticados
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'aprobar' => ['POST'],
                    'rechazar' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Listar abreviaturas médicas
     */
    public function actionIndex()
    {
        $especialidad = Yii::$app->request->get('especialidad');
        $categoria = Yii::$app->request->get('categoria');
        $busqueda = Yii::$app->request->get('busqueda');

        $query = AbreviaturasMedicas::find()->where(['activo' => 1]);

        if ($especialidad) {
            $query->andWhere(['or', 
                ['especialidad' => $especialidad], 
                ['especialidad' => null]
            ]);
        }

        if ($categoria) {
            $query->andWhere(['categoria' => $categoria]);
        }

        if ($busqueda) {
            $query->andWhere(['or',
                ['like', 'abreviatura', $busqueda],
                ['like', 'expansion_completa', $busqueda]
            ]);
        }

        $countQuery = clone $query;
        $totalCount = $countQuery->count();

        $pagination = new Pagination([
            'totalCount' => $totalCount,
            'pageSize' => 20,
        ]);

        $abreviaturas = $query->orderBy(['frecuencia_uso' => SORT_DESC])
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        // Renderizar la vista index con los datos y paginación
        return $this->render('index', [
            'abreviaturas' => $abreviaturas,
            'total' => (int)$totalCount,
            'especialidad' => $especialidad,
            'categoria' => $categoria,
            'busqueda' => $busqueda,
            'pagination' => $pagination,
        ]);
    }

    /**
     * Obtener abreviaturas pendientes de aprobación (activo=0)
     */
    public function actionSugerenciasPendientes()
    {
        $limite = Yii::$app->request->get('limite', 50);
        
        $query = AbreviaturasMedicas::find()
            ->where(['activo' => 0])
            ->orderBy(['fecha_creacion' => SORT_DESC])
            ->limit($limite);
        
        $sugerencias = $query->all();

        return $this->render('sugerencias-pendientes', [
            'sugerencias' => $sugerencias,
            'limite' => $limite,
        ]);
    }

    /**
     * Aprobar una abreviatura pendiente (activarla)
     */
    public function actionAprobar()
    {
        if (!Yii::$app->request->isPost) {
            throw new MethodNotAllowedHttpException('Este endpoint acepta sólo solicitudes POST.');
        }

        $id = Yii::$app->request->post('id');

        if (!$id) {
            Yii::$app->session->setFlash('error', 'ID de abreviatura requerido');
            return $this->redirect(['sugerencias-pendientes']);
        }

        $abreviatura = AbreviaturasMedicas::findOne($id);

        if (!$abreviatura) {
            Yii::$app->session->setFlash('error', 'Abreviatura no encontrada');
            return $this->redirect(['sugerencias-pendientes']);
        }

        $abreviatura->activo = 1;
        
        if ($abreviatura->save(false)) {
            Yii::$app->session->setFlash('success', 'Abreviatura aprobada y activada');
        } else {
            Yii::$app->session->setFlash('error', 'Error al aprobar abreviatura');
        }

        return $this->redirect(['sugerencias-pendientes']);
    }

    /**
     * Rechazar una abreviatura pendiente (eliminarla)
     */
    public function actionRechazar()
    {
        if (Yii::$app->request->isPost) {
            $id = Yii::$app->request->post('id');

            if (!$id) {
                Yii::$app->session->setFlash('error', 'ID de abreviatura requerido');
                return $this->redirect(['sugerencias-pendientes']);
            }

            $abreviatura = AbreviaturasMedicas::findOne($id);

            if (!$abreviatura) {
                Yii::$app->session->setFlash('error', 'Abreviatura no encontrada');
                return $this->redirect(['sugerencias-pendientes']);
            }

            if ($abreviatura->delete()) {
                Yii::$app->session->setFlash('success', 'Abreviatura rechazada y eliminada');
            } else {
                Yii::$app->session->setFlash('error', 'Error al rechazar abreviatura');
            }

            return $this->redirect(['sugerencias-pendientes']);
        }

        $id = Yii::$app->request->get('id');
        $abreviatura = AbreviaturasMedicas::findOne($id);

        return $this->render('rechazar', [
            'abreviatura' => $abreviatura,
        ]);
    }

    /**
     * Agregar nueva abreviatura médica
     */
    public function actionAgregar()
    {
        if (Yii::$app->request->isPost) {
            $body = Yii::$app->request->getBodyParams();

            $datos = [
                'abreviatura' => $body['abreviatura'] ?? '',
                'expansion_completa' => $body['expansion_completa'] ?? '',
                'categoria' => $body['categoria'] ?? null,
                'especialidad' => $body['especialidad'] ?? null,
                'contexto' => $body['contexto'] ?? null,
                'sinonimos' => $body['sinonimos'] ?? null
            ];

            if (empty($datos['abreviatura']) || empty($datos['expansion_completa'])) {
                Yii::$app->session->setFlash('error', 'Abreviatura y expansión son obligatorias');
                return $this->redirect(['agregar']);
            }

            $resultado = AbreviaturasMedicas::agregarAbreviatura($datos);

            if ($resultado) {
                Yii::$app->session->setFlash('success', 'Abreviatura agregada correctamente');
            } else {
                Yii::$app->session->setFlash('error', 'Error al agregar abreviatura');
            }

            return $this->redirect(['index']);
        }

        return $this->render('agregar');
    }

    /**
     * Actualizar abreviatura médica
     */
    public function actionActualizar()
    {
        if (Yii::$app->request->isPost) {
            $body = Yii::$app->request->getBodyParams();
            $id = $body['id'] ?? null;

            if (!$id) {
                Yii::$app->session->setFlash('error', 'ID de abreviatura requerido');
                return $this->redirect(['index']);
            }

            $abreviatura = AbreviaturasMedicas::findOne($id);

            if (!$abreviatura) {
                Yii::$app->session->setFlash('error', 'Abreviatura no encontrada');
                return $this->redirect(['index']);
            }

            $abreviatura->attributes = $body;

            if ($abreviatura->save()) {
                Yii::$app->session->setFlash('success', 'Abreviatura actualizada correctamente');
            } else {
                Yii::$app->session->setFlash('error', 'Error al actualizar abreviatura');
            }

            return $this->redirect(['index']);
        }

        $id = Yii::$app->request->get('id');
        $abreviatura = AbreviaturasMedicas::findOne($id);

        return $this->render('actualizar', [
            'abreviatura' => $abreviatura,
        ]);
    }

    /**
     * Eliminar abreviatura médica (marcar como inactiva)
     */
    public function actionEliminar()
    {
        if (Yii::$app->request->isPost) {
            $id = Yii::$app->request->post('id');

            if (!$id) {
                Yii::$app->session->setFlash('error', 'ID de abreviatura requerido');
                return $this->redirect(['index']);
            }

            $abreviatura = AbreviaturasMedicas::findOne($id);

            if (!$abreviatura) {
                Yii::$app->session->setFlash('error', 'Abreviatura no encontrada');
                return $this->redirect(['index']);
            }

            $abreviatura->activo = 0;

            if ($abreviatura->save()) {
                Yii::$app->session->setFlash('success', 'Abreviatura eliminada correctamente');
            } else {
                Yii::$app->session->setFlash('error', 'Error al eliminar abreviatura');
            }

            return $this->redirect(['index']);
        }

        $id = Yii::$app->request->get('id');
        $abreviatura = AbreviaturasMedicas::findOne($id);

        return $this->render('eliminar', [
            'abreviatura' => $abreviatura,
        ]);
    }
}

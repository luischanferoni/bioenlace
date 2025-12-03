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
use common\models\AbreviaturasSugeridas;
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
                        'actions' => ['index', 'mas-reportadas', 'estadisticas', 'sugerencias-pendientes', 'agregar-expansion', 'aprobar', 'rechazar'],
                        'allow' => true,
                        'roles' => ['@'], // Solo usuarios autenticados
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'agregar-expansion' => ['GET','POST'],
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
     * Obtener abreviaturas más reportadas
     */
    public function actionMasReportadas()
    {
        $limite = Yii::$app->request->get('limite', 20);
        $abreviaturas = ProcesadorTextoMedico::getAbreviaturasMasReportadas($limite);

        return $this->render('mas-reportadas', [
            'abreviaturas' => $abreviaturas,
            'limite' => $limite,
        ]);
    }

    /**
     * Obtener estadísticas de sugerencias
     */
    public function actionEstadisticas()
    {
        $estadisticas = ProcesadorTextoMedico::getEstadisticasSugerencias();

        return $this->render('estadisticas', [
            'estadisticas' => $estadisticas,
        ]);
    }

    /**
     * Obtener sugerencias pendientes
     */
    public function actionSugerenciasPendientes()
    {
        $limite = Yii::$app->request->get('limite', 50);
        $sugerencias = ProcesadorTextoMedico::getSugerenciasPendientes($limite);

        return $this->render('sugerencias-pendientes', [
            'sugerencias' => $sugerencias,
            'limite' => $limite,
        ]);
    }

    /**
     * Aprobar una abreviatura sugerida
     */
    public function actionAprobar()
    {
        // Esta acción sólo cambia el estado a 'aprobada'. Requiere que exista 'expansion_propuesta'.
        if (!Yii::$app->request->isPost) {
            throw new MethodNotAllowedHttpException('Este endpoint acepta sólo solicitudes POST.');
        }

        $id = Yii::$app->request->get('id');

        if (!$id) {
            Yii::$app->session->setFlash('error', 'ID de sugerencia requerido');
            return $this->redirect(['sugerencias-pendientes']);
        }

        $sugerencia = AbreviaturasSugeridas::findOne($id);

        if (!$sugerencia) {
            Yii::$app->session->setFlash('error', 'Sugerencia no encontrada');
            return $this->redirect(['sugerencias-pendientes']);
        }

        if (empty($sugerencia->expansion_propuesta)) {
            Yii::$app->session->setFlash('error', 'Debe cargar una expansión antes de aprobar la sugerencia');
            return $this->redirect(['sugerencias-pendientes']);
        }

        $usuarioId = Yii::$app->user->id ?? 1;

        if ($sugerencia->aprobar($usuarioId, '')) {
            Yii::$app->session->setFlash('success', 'Abreviatura aprobada y agregada a la base de datos');
        } else {
            $errores = $sugerencia->getErrors();
            if (!empty($errores)) {
                $mensajeError = 'Error de validación: ';
                foreach ($errores as $campo => $erroresCampo) {
                    $mensajeError .= implode(', ', $erroresCampo) . ' ';
                }
                Yii::$app->session->setFlash('error', trim($mensajeError));
            } else {
                Yii::$app->session->setFlash('error', 'Error al aprobar abreviatura');
            }
        }

        return $this->redirect(['sugerencias-pendientes']);
    }

    /**
     * Cargar/editar expansión y comentarios para una sugerencia
     */
    public function actionAgregarExpansion()
    {
        if (Yii::$app->request->isGet) {
            $id = Yii::$app->request->get('id');
            $sugerencia = AbreviaturasSugeridas::findOne($id);

            return $this->render('agregar-expansion', [
                'sugerencia' => $sugerencia,
            ]);
        }

        if (Yii::$app->request->isPost) {
            $body = Yii::$app->request->getBodyParams();
            $id = $body['id'] ?? null;
            $expansion = $body['expansion'] ?? null;
            $comentarios = $body['comentarios'] ?? null;

            if (!$id) {
                Yii::$app->session->setFlash('error', 'ID de sugerencia requerido');
                return $this->redirect(['sugerencias-pendientes']);
            }

            $sugerencia = AbreviaturasSugeridas::findOne($id);
            if (!$sugerencia) {
                Yii::$app->session->setFlash('error', 'Sugerencia no encontrada');
                return $this->redirect(['sugerencias-pendientes']);
            }

            $sugerencia->expansion_propuesta = $expansion;
            $sugerencia->comentarios = $comentarios;
            $sugerencia->save(false);

            Yii::$app->session->setFlash('success', 'Expansión guardada. Ahora puede aprobar la sugerencia.');
            return $this->redirect(['sugerencias-pendientes']);
        }

        throw new MethodNotAllowedHttpException('Método no permitido.');
    }

    /**
     * Rechazar una abreviatura sugerida
     */
    public function actionRechazar()
    {
        if (Yii::$app->request->isPost) {
            $body = Yii::$app->request->getBodyParams();
            $id = $body['id'] ?? null;
            $comentarios = $body['comentarios'] ?? '';

            if (!$id) {
                Yii::$app->session->setFlash('error', 'ID de sugerencia requerido');
                return $this->redirect(['sugerencias-pendientes']);
            }

            $sugerencia = AbreviaturasSugeridas::findOne($id);

            if (!$sugerencia) {
                Yii::$app->session->setFlash('error', 'Sugerencia no encontrada');
                return $this->redirect(['sugerencias-pendientes']);
            }

            $usuarioId = Yii::$app->user->id ?? 1;

            if ($sugerencia->rechazar($usuarioId, $comentarios)) {
                Yii::$app->session->setFlash('success', 'Abreviatura rechazada');
            } else {
                Yii::$app->session->setFlash('error', 'Error al rechazar abreviatura');
            }

            return $this->redirect(['sugerencias-pendientes']);
        }

        $id = Yii::$app->request->get('id');
        $sugerencia = AbreviaturasSugeridas::findOne($id);

        return $this->render('rechazar', [
            'sugerencia' => $sugerencia,
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

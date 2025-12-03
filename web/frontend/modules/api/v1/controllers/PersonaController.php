<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use common\models\Persona;
use common\models\Consulta;
use common\models\Turno;

class PersonaController extends BaseController
{
    public $modelClass = 'common\models\Persona';

    /**
     * Obtener personas
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;
        
        $query = Persona::find();
        
        // Aplicar filtros de búsqueda
        if ($search = $request->get('search')) {
            $query->andWhere([
                'or',
                ['like', 'nombre', $search],
                ['like', 'apellido', $search],
                ['like', 'documento', $search],
            ]);
        }

        // Paginación
        $page = (int)$request->get('page', 1);
        $perPage = (int)$request->get('per_page', 20);
        $offset = ($page - 1) * $perPage;
        
        $total = $query->count();
        $personas = $query->offset($offset)->limit($perPage)->all();

        $formattedPersonas = [];
        foreach ($personas as $persona) {
            $formattedPersonas[] = [
                'id' => $persona->id_persona,
                'nombre' => $persona->getNombreCompleto(),
                'documento' => $persona->documento,
                'edad' => $persona->edad,
                'telefono' => $persona->telefono,
                'email' => $persona->email,
                'created_at' => $persona->created_at,
            ];
        }

        return $this->success([
            'personas' => $formattedPersonas,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Obtener persona por ID
     */
    public function actionView($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }

        return $this->success([
            'id' => $persona->id_persona,
            'nombre' => $persona->getNombreCompleto(),
            'documento' => $persona->documento,
            'fecha_nacimiento' => $persona->fecha_nacimiento,
            'edad' => $persona->edad,
            'sexo' => $persona->sexo,
            'telefono' => $persona->telefono,
            'email' => $persona->email,
            'direccion' => $persona->direccion,
            'created_at' => $persona->created_at,
        ]);
    }

    /**
     * Obtener timeline completo de persona
     * Incluye: Turnos, Consultas, Internaciones, Guardias, Documentos Externos, Encuestas, Estudios
     */
    public function actionTimeline($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }

        $efector_sesion = Yii::$app->user->getIdEfector();
        $servicios = Yii::$app->user->getServicios();
        $idRrhh = Yii::$app->user->getIdRecursoHumano();

        // Query para Turnos
        $queryTurnos = (new \yii\db\Query())
            ->select([
                'id' => 'turnos.id_turnos',
                'fecha' => 'CONCAT(turnos.fecha," ",turnos.hora)',
                'resumen' => 'CONCAT("Turno")',
                'parent_class' => 'turnos.parent_class',
                'id_servicio' => 'id_servicio_asignado',
                'servicio' => 'servicios.nombre',
                'tipo' => 'turnos.estado',
                'parent_id' => 'turnos.id_turnos',
                'rr_hh' => 'CONCAT(COALESCE(personas.apellido,"")," ",COALESCE(personas.nombre,""))',
                'id_rr_hh' => 'turnos.id_rrhh_servicio_asignado',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Turno")'
            ])
            ->join('LEFT JOIN', 'rrhh_servicio', 'rrhh_servicio.id = turnos.id_rrhh_servicio_asignado')
            ->join('LEFT JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = turnos.id_servicio_asignado')
            ->join('LEFT JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = turnos.id_efector')
            ->join('JOIN', 'servicios_efector as se', 'se.id_servicio = turnos.id_servicio_asignado and se.id_efector = turnos.id_efector')
            ->join('LEFT JOIN', 'servicios as pase_prev', 'pase_prev.id_servicio = se.pase_previo')
            ->from('turnos')
            ->where(['turnos.id_persona' => $id])
            ->andWhere('turnos.deleted_at IS NULL');

        if ($efector_sesion) {
            $queryTurnos->andWhere(['turnos.id_efector' => $efector_sesion]);
        }

        // Query para Consultas (sin parent turno/internacion/pase previo)
        $queryConsultas = (new \yii\db\Query())
            ->select([
                'id' => 'consultas.id_consulta',
                'fecha' => 'consultas.created_at',
                'resumen' => 'CONCAT("Consulta")',
                'parent_class' => 'consultas.parent_class',
                'id_servicio' => 'consultas.id_servicio',
                'servicio' => 'servicios.nombre',
                'tipo' => 'CONCAT(consultas.id_configuracion, "-", consultas.paso_completado)',
                'parent_id' => 'consultas.id_consulta',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'consultas.id_rr_hh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Consulta")'
            ])
            ->join('JOIN', 'servicios', 'servicios.id_servicio = consultas.id_servicio')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = consultas.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = rrhh_efector.id_efector')
            ->from('consultas')
            ->andWhere(['consultas.id_persona' => $id])
            ->andWhere(['<>', 'consultas.parent_class', Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO]])
            ->andWhere(['<>', 'consultas.parent_class', Consulta::PARENT_CLASSES[Consulta::PARENT_INTERNACION]])
            ->andWhere(['<>', 'consultas.parent_class', Consulta::PARENT_CLASSES[Consulta::PARENT_PASE_PREVIO]])
            ->andWhere('consultas.deleted_at IS NULL');

        // Query para Consultas con parent turno
        $queryConsultasTurnos = (new \yii\db\Query())
            ->select([
                'id' => 'consultas.id_consulta',
                'fecha' => 'CONCAT(turnos.fecha," ",turnos.hora)',
                'resumen' => 'CONCAT("Consulta")',
                'parent_class' => 'consultas.parent_class',
                'id_servicio' => 'consultas.id_servicio',
                'servicio' => 'servicios.nombre',
                'tipo' => 'CONCAT(consultas.id_configuracion, "-", consultas.paso_completado)',
                'parent_id' => 'consultas.id_consulta',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'consultas.id_rr_hh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Consulta")'
            ])
            ->join('JOIN', 'turnos', 'turnos.id_turnos = consultas.id_turnos OR turnos.id_turnos = consultas.parent_id')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = consultas.id_servicio')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = consultas.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = rrhh_efector.id_efector')
            ->from('consultas')
            ->andWhere(['consultas.id_persona' => $id])
            ->andWhere(['in', 'consultas.parent_class', [
                Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO],
                Consulta::PARENT_CLASSES[Consulta::PARENT_PASE_PREVIO]
            ]])
            ->andWhere('consultas.deleted_at IS NULL');

        // Query para Internaciones
        $queryInternacion = (new \yii\db\Query())
            ->select([
                'id' => 'seg_nivel_internacion.id',
                'fecha' => 'TIMESTAMP(fecha_inicio,hora_inicio)',
                'resumen' => 'situacion_al_ingresar',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'CONCAT("NULL")',
                'servicio' => 'tipo_ingreso.nombre',
                'tipo' => 'CONCAT("NULL")',
                'parent_id' => 'CONCAT("NULL")',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'seg_nivel_internacion.id_rrhh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Internacion")'
            ])
            ->join('JOIN', 'tipo_ingreso', 'seg_nivel_internacion.id_tipo_ingreso = tipo_ingreso.id_tipo_ingreso')
            ->join('JOIN', 'rrhh_servicio', 'seg_nivel_internacion.id_rrhh = rrhh_servicio.id')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'infraestructura_cama as ic', 'ic.id = seg_nivel_internacion.id_cama')
            ->join('JOIN', 'infraestructura_sala as is', 'ic.id_sala = is.id')
            ->join('JOIN', 'infraestructura_piso as ip', 'is.id_piso = ip.id')
            ->join('JOIN', 'efectores', 'efectores.id_efector = ip.id_efector')
            ->from('seg_nivel_internacion')
            ->andWhere(['seg_nivel_internacion.id_persona' => $id])
            ->andWhere('seg_nivel_internacion.deleted_at IS NULL');

        // Query para Guardias
        $queryGuardias = (new \yii\db\Query())
            ->select([
                'id' => 'guardia.id',
                'fecha' => 'TIMESTAMP(fecha,hora)',
                'resumen' => 'situacion_al_ingresar',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'CONCAT("NULL")',
                'servicio' => 'situacion_al_ingresar',
                'tipo' => 'guardia.estado',
                'parent_id' => 'CONCAT("NULL")',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'guardia.id_rr_hh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Guardia")'
            ])
            ->join('JOIN', 'rrhh_efector', 'guardia.id_rr_hh = rrhh_efector.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = rrhh_efector.id_efector')
            ->from('guardia')
            ->andWhere(['guardia.id_persona' => $id])
            ->andWhere('guardia.deleted_at IS NULL');

        // Query para Documentos Externos
        $queryDocumentosExternos = (new \yii\db\Query())
            ->select([
                'id' => 'documentos_externos.id',
                'fecha' => 'documentos_externos.fecha',
                'resumen' => 'documentos_externos.titulo',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'rrhh_servicio.id_servicio',
                'servicio' => 'servicios.nombre',
                'tipo' => 'CONCAT("NULL")',
                'parent_id' => 'documentos_externos.id',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'rrhh_servicio.id_rr_hh',
                'efector' => 'CONCAT("NULL")',
                'tipo_historia' => 'CONCAT("DocumentoExterno")'
            ])
            ->join('JOIN', 'rrhh_servicio', 'documentos_externos.id_rrhh_servicio = rrhh_servicio.id')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->from('documentos_externos')
            ->where(['documentos_externos.id_persona' => $id])
            ->andWhere('documentos_externos.deleted_at IS NULL');

        // Query para Encuestas Parches Mamarios
        $queryEncuestasPM = (new \yii\db\Query())
            ->select([
                'id' => 'encuesta_parches_mamarios.id',
                'fecha' => 'encuesta_parches_mamarios.fecha_prueba',
                'resumen' => 'CONCAT("EncuestaParchesMamarios")',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'CONCAT("NULL")',
                'servicio' => 'CONCAT("NULL")',
                'tipo' => 'CONCAT("NULL")',
                'parent_id' => 'encuesta_parches_mamarios.id',
                'rr_hh' => 'CONCAT("NULL")',
                'id_rr_hh' => 'encuesta_parches_mamarios.id_rr_hh',
                'efector' => 'CONCAT("NULL")',
                'tipo_historia' => 'CONCAT("EncuestaParchesMamarios")'
            ])
            ->from('encuesta_parches_mamarios')
            ->andWhere(['encuesta_parches_mamarios.id_persona' => $id])
            ->andWhere('encuesta_parches_mamarios.deleted_at IS NULL');

        // Unir todas las queries
        $historial = (new \yii\db\Query())
            ->from(['historial' => $queryEncuestasPM->union(
                $queryInternacion->union(
                    $queryTurnos->union(
                        $queryConsultas->union(
                            $queryConsultasTurnos->union(
                                $queryGuardias->union(
                                    $queryDocumentosExternos
                                )
                            )
                        )
                    )
                )
            )])
            ->orderBy(['fecha' => SORT_DESC])
            ->all();

        // Obtener información médica adicional
        $condicionesActivas = [];
        $condicionesCronicas = [];
        $hallazgos = [];
        $antecedentes_personales = [];
        $antecedentes_familiares = [];

        try {
            list($condicionesActivas, $condicionesCronicas) = 
                \common\models\DiagnosticoConsultaRepository::getCondicionesPaciente($persona->id_persona);
        } catch (\Exception $e) {
            // Si falla, dejar arrays vacíos
        }

        try {
            $hallazgos = \common\models\Alergias::find()
                ->where(['id_persona' => $persona->id_persona])
                ->all();
        } catch (\Exception $e) {
            // Si falla, dejar array vacío
        }

        try {
            $antecedentes_personales = \common\models\PersonasAntecedente::find()
                ->where(['id_persona' => $persona->id_persona, 'tipo_antecedente' => 'Personal'])
                ->all();
        } catch (\Exception $e) {
            // Si falla, dejar array vacío
        }

        try {
            $antecedentes_familiares = \common\models\PersonasAntecedente::find()
                ->where(['id_persona' => $persona->id_persona, 'tipo_antecedente' => 'Familiar'])
                ->all();
        } catch (\Exception $e) {
            // Si falla, dejar array vacío
        }

        // Formatear timeline
        $timeline = [];
        foreach ($historial as $item) {
            $timeline[] = [
                'id' => $item['id'],
                'tipo' => $item['tipo_historia'],
                'fecha' => $item['fecha'],
                'resumen' => $item['resumen'],
                'servicio' => $item['servicio'],
                'id_servicio' => $item['id_servicio'],
                'parent_class' => $item['parent_class'],
                'parent_id' => $item['parent_id'],
                'profesional' => $item['rr_hh'],
                'id_rr_hh' => $item['id_rr_hh'],
                'efector' => $item['efector'],
                'tipo_detalle' => $item['tipo'],
            ];
        }

        // Formatear condiciones activas
        $condicionesActivasFormatted = [];
        foreach ($condicionesActivas as $condicion) {
            if (isset($condicion->codigoSnomed) && $condicion->codigoSnomed) {
                $condicionesActivasFormatted[] = [
                    'codigo' => isset($condicion->codigoSnomed->code) ? $condicion->codigoSnomed->code : null,
                    'termino' => isset($condicion->codigoSnomed->term) ? $condicion->codigoSnomed->term : null,
                ];
            }
        }

        // Formatear condiciones crónicas
        $condicionesCronicasFormatted = [];
        foreach ($condicionesCronicas as $condicion) {
            if (isset($condicion->codigoSnomed) && $condicion->codigoSnomed) {
                $condicionesCronicasFormatted[] = [
                    'codigo' => isset($condicion->codigoSnomed->code) ? $condicion->codigoSnomed->code : null,
                    'termino' => isset($condicion->codigoSnomed->term) ? $condicion->codigoSnomed->term : null,
                ];
            }
        }

        // Formatear hallazgos (alergias)
        $hallazgosFormatted = [];
        foreach ($hallazgos as $hallazgo) {
            $hallazgosFormatted[] = [
                'id' => $hallazgo->id_alergia,
                'codigo' => isset($hallazgo->codigoSnomed) && isset($hallazgo->codigoSnomed->code) ? $hallazgo->codigoSnomed->code : null,
                'termino' => isset($hallazgo->codigoSnomed) && isset($hallazgo->codigoSnomed->term) ? $hallazgo->codigoSnomed->term : null,
            ];
        }

        // Formatear antecedentes
        $antecedentesPersonalesFormatted = [];
        foreach ($antecedentes_personales as $ant) {
            $antecedentesPersonalesFormatted[] = [
                'id' => $ant->id,
                'situacion' => isset($ant->snomedSituacion) && isset($ant->snomedSituacion->term) ? $ant->snomedSituacion->term : null,
            ];
        }

        $antecedentesFamiliaresFormatted = [];
        foreach ($antecedentes_familiares as $ant) {
            $antecedentesFamiliaresFormatted[] = [
                'id' => $ant->id,
                'situacion' => isset($ant->snomedSituacion) && isset($ant->snomedSituacion->term) ? $ant->snomedSituacion->term : null,
            ];
        }

        return $this->success([
            'persona' => [
                'id' => $persona->id_persona,
                'nombre_completo' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
                'documento' => $persona->documento,
                'fecha_nacimiento' => $persona->fecha_nacimiento,
                'edad' => $persona->edad,
                'sexo' => $persona->sexo,
            ],
            'informacion_medica' => [
                'condiciones_activas' => $condicionesActivasFormatted,
                'condiciones_cronicas' => $condicionesCronicasFormatted,
                'hallazgos' => $hallazgosFormatted,
                'antecedentes_personales' => $antecedentesPersonalesFormatted,
                'antecedentes_familiares' => $antecedentesFamiliaresFormatted,
            ],
            'timeline' => $timeline,
            'total_eventos' => count($timeline),
        ]);
    }

    /**
     * Crear nueva persona
     */
    public function actionCreate()
    {
        $request = Yii::$app->request;
        
        $persona = new Persona();
        $persona->load($request->post(), '');
        $persona->created_at = date('Y-m-d H:i:s');

        if (!$persona->save()) {
            return $this->error('Error creando persona', $persona->getErrors(), 422);
        }

        return $this->success([
            'id' => $persona->id_persona,
            'nombre' => $persona->getNombreCompleto(),
        ], 'Persona creada exitosamente', 201);
    }

    /**
     * Actualizar persona
     */
    public function actionUpdate($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }

        $request = Yii::$app->request;
        $persona->load($request->post(), '');
        $persona->updated_at = date('Y-m-d H:i:s');

        if (!$persona->save()) {
            return $this->error('Error actualizando persona', $persona->getErrors(), 422);
        }

        return $this->success([
            'id' => $persona->id_persona,
            'nombre' => $persona->getNombreCompleto(),
        ], 'Persona actualizada exitosamente');
    }

    /**
     * Eliminar persona
     */
    public function actionDelete($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }

        if (!$persona->delete()) {
            return $this->error('Error eliminando persona', null, 500);
        }

        return $this->success(null, 'Persona eliminada exitosamente');
    }
}

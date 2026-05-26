<?php

namespace common\models;

use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;

/*
  Logica de Negocio de procesos que involucra DiagnosticoConsulta.
 */
class DiagnosticoConsultaRepository
{
    protected static function getStatesSubset($subset, $state_labels) {
      $options = [];
      foreach($subset as $state) {
        $options[$state] = $state_labels[$state];
      }
      return $options;
    }
    
    public static function getClinicalStatusDisplayLabel($state){
        return ArrayHelper::getValue(
                DiagnosticoConsulta::ESTADOS_CLINICOS, 
                $state, 
                'undefined');
    }
    
    public static function getVerificationStatusDisplayLabel($state){
        return ArrayHelper::getValue(
                DiagnosticoConsulta::ESTADOS_DE_VERIFICACION, 
                $state, 
                'undefined');
    }
    
    public static function getClinicalStatusForPrev() {
        return DiagnosticoConsulta::ESTADOS_CLINICOS;
    }
    
    public static function getVerificationStatusForPrev() {
        return self::getStatesSubset(
            [
              DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED,
              DiagnosticoConsulta::VERIFICATION_STATUS_REFUTED,
              DiagnosticoConsulta::VERIFICATION_STATUS_ENTERED_IN_ERROR
            ],
            DiagnosticoConsulta::ESTADOS_DE_VERIFICACION
            );
    }
    
    public static function getClinicalStatusForNew() {
      return self::getStatesSubset(
              [
                DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE,
                DiagnosticoConsulta::CLINICAL_STATUS_RECURRENCE,
                DiagnosticoConsulta::CLINICAL_STATUS_RELAPSE
              ],
              DiagnosticoConsulta::ESTADOS_CLINICOS
              );
    }
    
    public static function getVerificationStatusForNew() {
        return self::getStatesSubset(
            [
              DiagnosticoConsulta::VERIFICATION_STATUS_UNCONFIRMED,
              DiagnosticoConsulta::VERIFICATION_STATUS_PROVISIONAL,
              DiagnosticoConsulta::VERIFICATION_STATUS_DIFFERENTIAL,
              DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED,
            ],
            DiagnosticoConsulta::ESTADOS_DE_VERIFICACION
            );
    }
    
    /**
     * Contexto parent/persona desde {@see Encounter} o AR legacy `Consulta`.
     *
     * @param Encounter|object $consulta
     * @return array{persona_id: int, parent_id: int, parent_class: string}
     */
    protected static function resolveConsultaContext($consulta): array
    {
        if ($consulta instanceof Encounter) {
            return [
                'persona_id' => (int) $consulta->subject_persona_id,
                'parent_id' => (int) $consulta->parent_id,
                'parent_class' => Encounter::PARENT_CLASSES[$consulta->parent_type] ?? '',
            ];
        }

        return [
            'persona_id' => (int) $consulta->id_persona,
            'parent_id' => (int) $consulta->parent_id,
            'parent_class' => (string) $consulta->parent_class,
        ];
    }

    /**
     * @param Encounter|object $consultaOrEncounter
     */
    protected static function resolveEncounterId($consultaOrEncounter): int
    {
        if ($consultaOrEncounter instanceof Encounter) {
            return (int) $consultaOrEncounter->id;
        }
        if (isset($consultaOrEncounter->id_consulta) && (int) $consultaOrEncounter->id_consulta > 0) {
            return (int) $consultaOrEncounter->id_consulta;
        }
        if (method_exists($consultaOrEncounter, 'getEncounter_id')) {
            $id = $consultaOrEncounter->getEncounter_id();
            if ($id !== null && (int) $id > 0) {
                return (int) $id;
            }
        }

        throw new \InvalidArgumentException('Encounter id required.');
    }

    /**
     * Crea query diagnosticos previos de una persona.
     *
     * Retorna los diagnosticos previos que no tiene seguimiento (root_id = Null)
     * y el ultimo seguimiento realizado a un diagnostico asosiado a la persona.
     *
     * Retorna la query a la que luego pueden agregarsele mas filtros.
     * Ver funciones que la usan.
     */
    protected static function getQueryDiagPreviosByPersona($persona_id) {
        $view_diagnosticos = 'view_encounter_diagnostico';
        
        # Sq0, Lista el ulitmo movimiento de los diagnosticos
        # con seguimiento, para persona. 
        $sq0 = (new Query())
            ->select('max(dcy.id) as id')
            ->from(['dcx' => $view_diagnosticos])
            ->innerJoin(
                ['dcy' => $view_diagnosticos],
                'dcx.id = dcy.root_id')
            ->where('dcx.id_persona = :persona_id')
            ->andWhere('dcy.id_persona = :persona_id')
            ->addParams([':persona_id' => $persona_id])
            ->groupBy('dcx.id')
        ;
        
        # Sq1: Lista diagnosticos sin seguimiento para persona x
        $sq1 = (new Query())
            ->select('cda.id as id')
            ->from(['cda' => $view_diagnosticos])
            ->leftJoin(
                ['cdb' => $view_diagnosticos],
                'cda.id = cdb.root_id')
            ->where('cda.root_id IS NULL')
            ->andWhere('cdb.root_id IS NULL')
            ->andWhere(
                'cda.id_persona = :persona_id',
                [':persona_id' => $persona_id])
            ;
        # Query principal uniendo las de arriba
        $mq = DiagnosticoPrevio::find()
            ->from(["dcm" => $view_diagnosticos])
            ->innerJoin(
                ['v' => $sq0->union($sq1)],
                'v.id = dcm.id')
            ;
        return $mq;
    }
    
    public static function getQueryDiagnosticosPreviosPendientes($persona_id) {
        $query = self::getQueryDiagPreviosByPersona($persona_id);
        $query->andWhere([
                'dcm.condition_clinical_status' =>
                    ['ACTIVE', 'RECURRENCE', 'RELAPSE'],
                'dcm.condition_verification_status' => 
                    ['CONFIRMED', 'UNCONFIRMED', 'PROVISIONAL', 'DIFFERENTIAL']
            ])
            ;
        return $query;
    }
    
    public static function getDiagnosticosPreviosPendientes($persona_id) {
        $query = self::getQueryDiagnosticosPreviosPendientes($persona_id);
        return $query->all();
    }
    
    /*
     * Diagnosticos previos pendientes en internación.
     */
    public static function getDiagnosticosPreviosPendientesIMP($consulta) {
        $ctx = self::resolveConsultaContext($consulta);
        $query = self::getQueryDiagnosticosPreviosPendientes($ctx['persona_id']);
        $query->andWhere(
            'dcm.c_parent_id = :parent_id',
            [':parent_id' => $ctx['parent_id']]
            )
            ->andWhere(
            'dcm.c_parent_class = :parent_class',
            [':parent_class' => $ctx['parent_class']]
            )
            ;
        return $query->all();
    }
    
    public static function getQueryDiagnosticosPersonaIMP($internacion) {
        $query = self::getQueryDiagPreviosByPersona($internacion->id_persona)
            ->andWhere(
                'dcm.c_parent_id = :parent_id',
                [':parent_id' => $internacion->id]
            )
            ->andWhere(
                'dcm.c_parent_class = :parent_class',
                [':parent_class' => Encounter::PARENT_CLASSES[
                    Encounter::PARENT_INTERNACION
                ]]
            )
            ->addOrderBy('dcm.id DESC')
            ;
        return $query;
    }
    
    /*
     * Contar Diagnosticos cargados en internación,
     * que no sean sin confirmar, refutados, o en error.
     * Se utiliza para validar el alta de internacion.
     */
    public static function getCountDiagnosticosIMP($internacion) {
        $query = self::getQueryDiagnosticosPersonaIMP($internacion);
        $query->andWhere([
                'not in',
                'dcm.condition_verification_status',
                ['UNCONFIRMED', 'REFUTED', 'ENTERED_IN_ERROR']
            ]);
        return $query->count();
    }
    
    /*
     * Obtener todos los diagnosticos de la persona en internación.
     */
    public static function getDiagnosticosPersonaIMP($internacion) {
        $query = self::getQueryDiagnosticosPersonaIMP($internacion);
        return $query->all();
    }

    /*
     * Retorna las condiciones (diagnosticos) recientes de la persona.
     * 
     * Se incluye en la query los cronicos, para realizar solo una 
     * consulta a la base y separa los cronicos aqui.
     * 
     * Retorna: array de condiciones previas, array de condiciones cronicas.
     */
    public static function getCondicionesPaciente($persona_id) {
        $back_date = strtotime(date("Y-m-d")."- 3 month");
        $back_date = date("Y-m-d 00:00", $back_date);
        $query = self::getQueryDiagPreviosByPersona($persona_id);
        $query->Where('dcm.id_persona = :persona_id')
            ->andWhere([
                'or',
                "dcm.c_created_at > :fecha",
                "dcm.cronico = :cronico"
            ])
            ->addParams([
                ':persona_id' => $persona_id,
                ':fecha' => $back_date, 
                ':cronico' => 'SI']
            )
            ;
        $diagnosticos = $query->all();
        
        return [
            array_filter($diagnosticos, function($diag) {
                return $diag->cronico == 'NO';
            }),
            array_filter($diagnosticos, function($diag) {
                return $diag->cronico == 'SI';
            }),
        ];
    }

    /*
     * Guarda diagnosticos previos provenientes del formulario
     * de Diagnosticos.
     */
    public static function saveDiagnosticosPrevios($consulta, $diagsp) {
        $encounterId = self::resolveEncounterId($consulta);
        $encounter = Encounter::findOne($encounterId);
        if ($encounter === null) {
            throw new \InvalidArgumentException('Encounter not found.');
        }

        Condition::updateAll(
            ['deleted_at' => date('Y-m-d H:i:s')],
            [
                'and',
                ['encounter_id' => $encounterId],
                ['like', 'note', '%"previo":true%', false],
                ['deleted_at' => null],
            ]
        );

        foreach ($diagsp as $dp) {
            if ($dp->resolve == 'N') {
                continue;
            }
            $condition = new Condition();
            $condition->encounter_id = $encounterId;
            $condition->subject_persona_id = (int) $encounter->subject_persona_id;
            $condition->code = (string) $dp->codigo;
            $condition->clinical_status = $dp->isCronico()
                ? (string) $dp->condition_clinical_status
                : (string) $dp->new_cclinical_status;
            $condition->verification_status = $dp->isCronico()
                ? (string) $dp->condition_verification_status
                : (string) $dp->new_cverification_status;
            $condition->diagnosis_role = $dp->tipo_prestacion ?? null;
            $rootId = $dp->root_id === null ? $dp->id : $dp->root_id;
            $condition->note = json_encode([
                'previo' => true,
                'cronico' => $dp->cronico,
                'tipo_diagnostico' => $dp->tipo_diagnostico,
                'objeto_prestacion' => $dp->objeto_prestacion,
                'root_id' => $rootId,
            ], JSON_UNESCAPED_UNICODE);
            $condition->recorded_date = date('Y-m-d H:i:s');

            if (!$condition->save()) {
                throw new \Exception('Error saving Condition (diagnóstico previo).');
            }
        }
    }
    
    /*
     * Retornar diagnosticos ingresados especificamente en la
     * consulta, es decir, no relacionados con diagnosticos previos.
     * 
     * Se utiliza en furmulario de diagnosticos.
     */
    public static function getDiagnosticos($consulta) {
        $encounterId = self::resolveEncounterId($consulta);

        return Condition::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->andWhere([
                'or',
                ['note' => null],
                ['not like', 'note', '%"previo":true%', false],
            ])
            ->all();
    }
}
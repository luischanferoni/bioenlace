<?php

namespace common\models;

use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use common\models\Consulta;

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
     * Crea query diagnosticos previos de una persona.
     * 
     * Retorna los diagnosticos previos que no tiene seguimiento (root_id = Null)
     * y el ultimo seguimiento realizado a un diagnostico asosiado a la persona.
     * 
     * Retorna la query a la que luego pueden agregarsele mas filtros.
     * Ver funciones que la usan.
     */
    protected static function getQueryDiagPreviosByPersona($persona_id) {
        $view_diagnosticos = "view_consulta_diagnostico";
        
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
        $query = self::getQueryDiagnosticosPreviosPendientes(
            $consulta->id_persona);
        $query->andWhere(
            'dcm.c_parent_id = :parent_id',
            [':parent_id' => $consulta->parent_id]
            )
            ->andWhere(
            'dcm.c_parent_class = :parent_class',
            [':parent_class' => $consulta->parent_class]
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
                [':parent_class' => Consulta::PARENT_CLASSES[
                    Consulta::PARENT_INTERNACION
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
        # Se borran los anteriore si fue una edición
        DiagnosticoConsulta::deleteAll(
            ['and',
                ['id_consulta' => $consulta->id_consulta],
                ['not', ['root_id' => null]],
            ]);
        
        foreach($diagsp as $dp) {
            if($dp->resolve == 'N')
                continue;
            $d = new DiagnosticoConsulta();
            $d->id_consulta = $consulta->id_consulta;
            $d->codigo = $dp->codigo;
            $d->tipo_diagnostico = $dp->tipo_diagnostico;
            $d->cronico = $dp->cronico;
            $d->condition_clinical_status = $dp->new_cclinical_status;
            $d->condition_verification_status = $dp->new_cverification_status;
            $d->tipo_prestacion = $dp->tipo_prestacion;
            $d->objeto_prestacion = $dp->objeto_prestacion;
            $root_id = $dp->root_id === null? $dp->id: $dp->root_id;
            $d->root_id = $root_id;
            
            if($dp->isCronico()) {
                # si es cronico el estado no se puede editar,
                # fijo el estado anterior definido.
                $d->condition_clinical_status = $dp->condition_clinical_status;
                $d->condition_verification_status = $dp->condition_verification_status;
            }
            
            if(!$d->save()) {
                throw new \Exception("Error saving Diagnostico Previo.");
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
        $query = DiagnosticoConsulta::find()
            ->where(
                "id_consulta = :consulta_id", 
                [':consulta_id' => $consulta->id_consulta])
            ->andWhere('root_id IS NULL');
        return $query->all();
    }
}
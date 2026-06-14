<?php

namespace common\models;

use common\components\Domain\Clinical\EncounterDefinitionWorkflowSanitizer;
use common\components\Domain\Clinical\Service\EncounterCaptureContextService;
use Yii;

use common\models\Clinical\EncounterDefinition;
use common\models\Clinical\EncounterDefinitionQuery;
use common\models\Clinical\Encounter;

/**
 * @deprecated Alias de {@see \common\models\Clinical\EncounterDefinition} (`encounter_definition`).
 *
 * @property int $service_id
 * @property string $workflow_json
 */
class ConsultasConfiguracion extends \common\models\Clinical\EncounterDefinition
{
    /** @deprecated use {@see EncounterDefinition::ENCOUNTER_CLASS} */
    public const ENCOUNTER_CLASS = EncounterDefinition::ENCOUNTER_CLASS;

    public static function find(): EncounterDefinitionQuery
    {
        return new EncounterDefinitionQuery(static::class);
    }

    public static function getRelaciones($idConfiguracion)
    {
        $configuracion = self::findOne($idConfiguracion);

        if (!$configuracion) { return false; }
        
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayRelacionesPasos = [];
        foreach ($jsonPasos->conf as  $output) {
            $arrayRelacionesPasos[] = $output->relacion;
        }
        return $arrayRelacionesPasos;
    }

    public static function checkPasoUnico($idConfiguracion)
    {
        $configuracion = self::findOne($idConfiguracion);
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayRelacionesPasos = [];
        if (count($jsonPasos->conf) == 1) {
            return true;
        }else{
            return false;
        }        
    }

    public static function getRelacionesRequeridas($idConfiguracion)
    {
        $configuracion = self::findOne($idConfiguracion);
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayRelacionesPasos = [];
        foreach ($jsonPasos->conf as $output) {
            if (isset($output->requerido) && $output->requerido) {
                if (is_array($output->relacion)) {
                    foreach ($output->relacion as $relacion) {
                        $arrayRelacionesPasos[] = $relacion;
                    }
                } else {
                    $arrayRelacionesPasos[] = $output->relacion;
                }
            }
        }
        return $arrayRelacionesPasos;
    }

    public static function getMenuPorIdConfiguracion($idConsulta, $idConfiguracion, $paso = null, $id_persona)
    {
        $configuracion = self::findOne($idConfiguracion);
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayTitulosPasos = [];
        $arrayUrlsPasos = [];
        $arrayRelaciones = [];
        $mostrarUrlsHeader= false;
        foreach ($jsonPasos->conf as $k => $output) {
            $arrayTitulosPasos[] = (isset($output->requerido) && $output->requerido)? $output->titulo.'<span style="font-size:12px;" class="text-danger"> *</span>': $output->titulo;
            $arrayUrlsPasos[] = ($idConsulta)? Url::to([$output->url.'?id_consulta='.$idConsulta.'&paso='.$k.'&id_persona='.$id_persona]): '#';
            $arrayRelaciones[] = $output->relacion;
            if(!$mostrarUrlsHeader)
                $mostrarUrlsHeader = (isset($output->requerido) && $output->requerido)? true:false;
        }
        $menu = '<nav class="nav nav-pills">';
        
        foreach ($arrayTitulosPasos as $key => $value) {
            $active = (($key == $paso) ||
                ($key == 0 && $paso == null) ||
                ($paso  == 998 &&  $key == (count($arrayTitulosPasos) - 1))) ?
                'active' : '';
            $urlAccion = ($mostrarUrlsHeader)? $arrayUrlsPasos[$key]: '#';

            $id = $arrayRelaciones[$key];

            //en relaciones, hay que contemplar la posibilidad de que venga un array
            if(is_array($arrayRelaciones[$key])){
                $id = implode('-', $arrayRelaciones[$key]);
            }

            $menu .= '<li class="nav-item " ><a id="'.$id.'" class="nav-link atender ' . $active . '" href="'.$urlAccion.'">' . $value . '</a></li>';
        }
        $menu .= '</nav>';
        if($paso === 0){
            //$menu .= '<div style="font-size: 13px;padding: 10px;" class="alert alert-danger d-flex align-items-center" role="alert">';
            //$menu .='      <div>* Para poder finalizar la consulta deberá completar los pasos obligatorios.';
            //$menu .='      </div></div>';
        }

        return $menu;
    }

    public static function getUrlPorIdConfiguracion($idConfiguracion, $paso = null)
    {
        $configuracion = self::findOne($idConfiguracion);

        //$arrayPasos = explode(",", $configuracion->pasos);
        $jsonPasos  = json_decode($configuracion->pasos_json);
        $arrayPasos = [];
        foreach ($jsonPasos->conf as  $output) {
            $arrayPasos[] = $output->url;
        }

        if ($paso !== null) {
            $urlAnterior = isset($arrayPasos[$paso - 1])
                ? EncounterDefinitionWorkflowSanitizer::resolveStepUrl($arrayPasos[$paso - 1])
                : null;
            $urlActual = isset($arrayPasos[$paso])
                ? EncounterDefinitionWorkflowSanitizer::resolveStepUrl($arrayPasos[$paso])
                : null;
            $urlSiguiente = isset($arrayPasos[$paso + 1])
                ? EncounterDefinitionWorkflowSanitizer::resolveStepUrl($arrayPasos[$paso + 1])
                : null;

            return [$urlAnterior, $urlActual, $urlSiguiente];
        }

        $urlAnterior = null;
        $urlActual = EncounterDefinitionWorkflowSanitizer::resolveStepUrl($arrayPasos[0] ?? null);
        $urlSiguiente = isset($arrayPasos[1])
            ? EncounterDefinitionWorkflowSanitizer::resolveStepUrl($arrayPasos[1])
            : null;

        return [$urlAnterior, $urlActual, $urlSiguiente];
    }

    public function getCreatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'created_by']);
    }

    /**
     * @deprecated use {@see EncounterCaptureContextService::validarPermisoAtencion}
     */
    public static function validarPermisoAtencion($parent, $parentId, $paciente)
    {
        return EncounterCaptureContextService::validarPermisoAtencion($parent, $parentId, $paciente);
    }
}

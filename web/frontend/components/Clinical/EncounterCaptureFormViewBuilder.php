<?php

namespace frontend\components\Clinical;

use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use yii\helpers\Url;

/**
 * View model del partial de captura ({@see PacienteController::actionFormularioConsulta}).
 */
final class EncounterCaptureFormViewBuilder
{
    /**
     * @return array{
     *   esEvolucionImp: bool,
     *   formLabel: string,
     *   placeholder: string,
     *   analyzeLabel: string,
     *   analyzeTitle: string,
     *   modoCaptura: string,
     *   motivoPacientePrefill: string,
     *   sttClientConfig: array<string, mixed>,
     *   urlGuardar: string,
     *   urlInicio: string,
     *   personaId: int,
     *   idConfiguracion: int|null,
     *   idConsulta: int|null,
     *   parent: string|null,
     *   parentId: int|null
     * }
     */
    public static function build(
        Persona $paciente,
        $idConfiguracion,
        $idConsulta,
        $parent,
        $parentId,
        string $motivoPacientePrefill = ''
    ): array {
        $parentStr = $parent !== null && $parent !== '' ? (string) $parent : null;
        $parentIdInt = $parentId !== null && $parentId !== '' ? (int) $parentId : null;
        $idConsultaInt = $idConsulta !== null && $idConsulta !== '' && (int) $idConsulta > 0
            ? (int) $idConsulta
            : null;
        $idConfig = $idConfiguracion !== null && $idConfiguracion !== ''
            ? (int) $idConfiguracion
            : null;

        $esEvolucionImp = strtoupper(trim((string) $parentStr)) === Encounter::PARENT_INTERNACION;
        $prefill = $esEvolucionImp ? '' : trim($motivoPacientePrefill);

        return [
            'esEvolucionImp' => $esEvolucionImp,
            'formLabel' => $esEvolucionImp ? 'Evolución' : 'Formulario de consulta',
            'placeholder' => $esEvolucionImp
                ? 'Escribí o dictá la evolución del paciente internado (estado actual, cambios clínicos, plan).'
                : 'Escriba o dicte los detalles de la consulta. El asistente verificará motivos, evolución, diagnóstico, prácticas, etc.',
            'analyzeLabel' => $esEvolucionImp ? 'Analizar evolución' : 'Analizar consulta',
            'analyzeTitle' => $esEvolucionImp
                ? 'Analizar la evolución con IA'
                : 'Analizar la consulta con IA',
            'modoCaptura' => $esEvolucionImp ? 'imp' : 'amb',
            'motivoPacientePrefill' => $prefill,
            'sttClientConfig' => SttConfigService::clientSnapshot(),
            'urlGuardar' => Url::to(['/api/v1/clinical/encounter/guardar']),
            'urlInicio' => Url::to(['/site/index']),
            'personaId' => (int) $paciente->id_persona,
            'idConfiguracion' => $idConfig,
            'idConsulta' => $idConsultaInt,
            'parent' => $parentStr,
            'parentId' => $parentIdInt,
        ];
    }
}

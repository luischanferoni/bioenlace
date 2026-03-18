<?php

namespace common\components\Chatbot\Classification;

use Yii;

/**
 * Clasificador de consultas médicas para procesamiento selectivo.
 * Versión movida desde common\components\ConsultaClassifier.
 */
class ConsultaClassifier
{
    public static function esConsultaSimple($texto)
    {
        if (empty($texto) || strlen(trim($texto)) === 0) {
            return true;
        }

        $textoLower = mb_strtolower(trim($texto), 'UTF-8');
        $longitud = strlen($texto);

        if ($longitud < 50) {
            return true;
        }

        if ($longitud < 20) {
            return true;
        }

        $patronesSimples = [
            '/^(dolor|fiebre|tos|malestar|nauseas|vomito|diarrea|estrenimiento|mareo|dolor de cabeza|cefalea)/i',
            '/^(control|seguimiento|revision|consulta de control|control de|seguimiento de)/i',
            '/^(consulta de rutina|check up|chequeo|consulta rutinaria)/i',
            '/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ\s,\.]+$/u',
            '/(tomar|tomando|toma)\s+(paracetamol|ibuprofeno|aspirina|omeprazol)/i',
            '/^(receta|recetar|prescripcion|prescripción)\s+(para|de)/i',
        ];

        foreach ($patronesSimples as $patron) {
            if (preg_match($patron, $texto)) {
                if ($longitud < 200) {
                    return true;
                }
            }
        }

        if ($longitud > 300) {
            return false;
        }

        $palabrasComplejas = [
            'diagnostico', 'diagnóstico', 'patologia', 'patología', 'sindrome', 'síndrome',
            'tratamiento', 'terapia', 'medicacion', 'medicación', 'prescripcion', 'prescripción',
            'derivacion', 'derivación', 'interconsulta', 'estudios', 'laboratorio',
            'complicacion', 'complicación', 'evolucion', 'evolución', 'pronostico', 'pronóstico',
        ];

        $contadorComplejas = 0;
        foreach ($palabrasComplejas as $palabra) {
            if (stripos($textoLower, $palabra) !== false) {
                $contadorComplejas++;
            }
        }

        if ($contadorComplejas >= 2) {
            return false;
        }

        $numOraciones = substr_count($texto, '.') + substr_count($texto, '!') + substr_count($texto, '?');
        if ($numOraciones > 2) {
            return false;
        }

        return true;
    }

    public static function procesarConsultaSimple($texto, $servicio, $categorias)
    {
        $textoLower = mb_strtolower(trim($texto), 'UTF-8');
        $datosExtraidos = [];

        foreach ($categorias as $categoria) {
            $titulo = $categoria['titulo'];
            $datosExtraidos[$titulo] = [];
        }

        $sintomas = self::extraerSintomas($textoLower);
        if (!empty($sintomas) && isset($datosExtraidos['Síntomas'])) {
            $datosExtraidos['Síntomas'] = $sintomas;
        }

        $diagnosticos = self::extraerDiagnosticosSimples($textoLower);
        if (!empty($diagnosticos) && isset($datosExtraidos['Diagnósticos'])) {
            $datosExtraidos['Diagnósticos'] = $diagnosticos;
        }

        $medicamentos = self::extraerMedicamentosSimples($textoLower);
        if (!empty($medicamentos) && isset($datosExtraidos['Medicamentos'])) {
            $datosExtraidos['Medicamentos'] = $medicamentos;
        }

        if (class_exists('\common\components\CPUProcessor')) {
            $textoProcesado = \common\components\CPUProcessor::procesar('limpieza_texto', $texto);
            $textoProcesado = \common\components\CPUProcessor::procesar('normalizacion', $textoProcesado);
        } else {
            $textoProcesado = $texto;
        }

        return [
            'datosExtraidos' => $datosExtraidos,
            'texto_procesado' => $textoProcesado,
            'procesado_sin_gpu' => true,
            'metodo' => 'ConsultaClassifier::procesarConsultaSimple',
        ];
    }

    private static function extraerSintomas($texto)
    {
        $sintomas = [];
        $patronesSintomas = [
            'dolor' => ['dolor', 'duele', 'dolores'],
            'fiebre' => ['fiebre', 'febril', 'temperatura'],
            'tos' => ['tos', 'tose'],
            'nauseas' => ['nausea', 'nauseas', 'náusea', 'náuseas'],
            'vomito' => ['vomito', 'vómito', 'vomitos', 'vómitos'],
            'diarrea' => ['diarrea'],
            'malestar' => ['malestar', 'malestar general'],
            'cansancio' => ['cansancio', 'fatiga', 'agotamiento'],
        ];

        foreach ($patronesSintomas as $sintoma => $variantes) {
            foreach ($variantes as $variante) {
                if (stripos($texto, $variante) !== false) {
                    $sintomas[] = ucfirst($sintoma);
                    break;
                }
            }
        }

        return array_unique($sintomas);
    }

    private static function extraerDiagnosticosSimples($texto)
    {
        $diagnosticos = [];
        $patronesDiagnosticos = [
            'gripe' => ['gripe', 'influenza'],
            'resfriado' => ['resfriado', 'catarro'],
            'hipertension' => ['hipertensión', 'hipertension', 'hta'],
            'diabetes' => ['diabetes', 'diabético', 'diabetica'],
        ];

        foreach ($patronesDiagnosticos as $diagnostico => $variantes) {
            foreach ($variantes as $variante) {
                if (stripos($texto, $variante) !== false) {
                    $diagnosticos[] = ucfirst($diagnostico);
                    break;
                }
            }
        }

        return array_unique($diagnosticos);
    }

    private static function extraerMedicamentosSimples($texto)
    {
        $medicamentos = [];

        $patronesMedicamentos = [
            'paracetamol', 'ibuprofeno', 'aspirina', 'amoxicilina', 'penicilina',
            'omeprazol', 'metformina', 'losartan', 'atenolol',
        ];

        foreach ($patronesMedicamentos as $medicamento) {
            if (stripos($texto, $medicamento) !== false) {
                $medicamentos[] = ucfirst($medicamento);
            }
        }

        return array_unique($medicamentos);
    }
}



<?php

namespace common\components;

use Yii;

/**
 * Clasificador de consultas médicas para procesamiento selectivo
 * Determina si una consulta es simple (puede procesarse sin GPU) o compleja (requiere IA)
 */
class ConsultaClassifier
{
    /**
     * Determinar si una consulta es simple (no requiere IA completa)
     * @param string $texto Texto de la consulta
     * @return bool
     */
    public static function esConsultaSimple($texto)
    {
        if (empty($texto) || strlen(trim($texto)) === 0) {
            return true;
        }
        
        $textoLower = mb_strtolower(trim($texto), 'UTF-8');
        $longitud = strlen($texto);
        
        // Consultas muy cortas (< 50 caracteres) generalmente son simples
        if ($longitud < 50) {
            return true;
        }
        
        // Consultas extremadamente cortas (< 20 caracteres) siempre son simples
        if ($longitud < 20) {
            return true;
        }
        
        // Patrones simples comunes que no requieren IA (expandido para mayor cobertura)
        $patronesSimples = [
            // Síntomas básicos
            '/^(dolor|fiebre|tos|malestar|nauseas|vomito|diarrea|estrenimiento|mareo|dolor de cabeza|cefalea)/i',
            // Consultas de control
            '/^(control|seguimiento|revision|consulta de control|control de|seguimiento de)/i',
            // Consultas de rutina
            '/^(consulta de rutina|check up|chequeo|consulta rutinaria)/i',
            // Textos muy estructurados y cortos
            '/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ\s,\.]+$/u',
            // Patrones de medicamentos simples
            '/(tomar|tomando|toma)\s+(paracetamol|ibuprofeno|aspirina|omeprazol)/i',
            // Consultas de receta simple
            '/^(receta|recetar|prescripcion|prescripción)\s+(para|de)/i',
        ];
        
        foreach ($patronesSimples as $patron) {
            if (preg_match($patron, $texto)) {
                // Verificar que no sea demasiado largo (consultas largas pueden ser complejas)
                if ($longitud < 200) {
                    return true;
                }
            }
        }
        
        // Consultas con múltiples síntomas, diagnósticos complejos, o texto largo = compleja
        if ($longitud > 300) {
            return false;
        }
        
        // Contar palabras médicas complejas
        $palabrasComplejas = [
            'diagnostico', 'diagnóstico', 'patologia', 'patología', 'sindrome', 'síndrome',
            'tratamiento', 'terapia', 'medicacion', 'medicación', 'prescripcion', 'prescripción',
            'derivacion', 'derivación', 'interconsulta', 'estudios', 'laboratorio',
            'complicacion', 'complicación', 'evolucion', 'evolución', 'pronostico', 'pronóstico'
        ];
        
        $contadorComplejas = 0;
        foreach ($palabrasComplejas as $palabra) {
            if (stripos($textoLower, $palabra) !== false) {
                $contadorComplejas++;
            }
        }
        
        // Si tiene 2+ palabras complejas, es consulta compleja
        if ($contadorComplejas >= 2) {
            return false;
        }
        
        // Si tiene múltiples oraciones (indicador de complejidad)
        $numOraciones = substr_count($texto, '.') + substr_count($texto, '!') + substr_count($texto, '?');
        if ($numOraciones > 2) {
            return false;
        }
        
        // Por defecto, si no cumple criterios de complejidad, es simple
        return true;
    }
    
    /**
     * Procesar consulta simple sin usar GPU
     * Usa reglas predefinidas y CPUProcessor
     * @param string $texto Texto de la consulta
     * @param string $servicio Nombre del servicio
     * @param array $categorias Categorías de configuración
     * @return array Resultado estructurado similar a IA
     */
    public static function procesarConsultaSimple($texto, $servicio, $categorias)
    {
        $textoLower = mb_strtolower(trim($texto), 'UTF-8');
        $datosExtraidos = [];
        
        // Inicializar todas las categorías vacías
        foreach ($categorias as $categoria) {
            $titulo = $categoria['titulo'];
            $datosExtraidos[$titulo] = [];
        }
        
        // Extracción básica usando reglas (sin GPU)
        // Síntomas comunes
        $sintomas = self::extraerSintomas($textoLower);
        if (!empty($sintomas) && isset($datosExtraidos['Síntomas'])) {
            $datosExtraidos['Síntomas'] = $sintomas;
        }
        
        // Diagnósticos simples (patrones comunes)
        $diagnosticos = self::extraerDiagnosticosSimples($textoLower);
        if (!empty($diagnosticos) && isset($datosExtraidos['Diagnósticos'])) {
            $datosExtraidos['Diagnósticos'] = $diagnosticos;
        }
        
        // Medicamentos mencionados (patrones básicos)
        $medicamentos = self::extraerMedicamentosSimples($textoLower);
        if (!empty($medicamentos) && isset($datosExtraidos['Medicamentos'])) {
            $datosExtraidos['Medicamentos'] = $medicamentos;
        }
        
        // Usar CPUProcessor para limpieza y normalización
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
            'metodo' => 'ConsultaClassifier::procesarConsultaSimple'
        ];
    }
    
    /**
     * Extraer síntomas básicos usando patrones
     * @param string $texto
     * @return array
     */
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
    
    /**
     * Extraer diagnósticos simples usando patrones
     * @param string $texto
     * @return array
     */
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
    
    /**
     * Extraer medicamentos mencionados (patrones básicos)
     * @param string $texto
     * @return array
     */
    private static function extraerMedicamentosSimples($texto)
    {
        $medicamentos = [];
        
        // Patrones comunes de medicamentos
        $patronesMedicamentos = [
            'paracetamol', 'ibuprofeno', 'aspirina', 'amoxicilina', 'penicilina',
            'omeprazol', 'metformina', 'losartan', 'atenolol'
        ];
        
        foreach ($patronesMedicamentos as $medicamento) {
            if (stripos($texto, $medicamento) !== false) {
                $medicamentos[] = ucfirst($medicamento);
            }
        }
        
        return array_unique($medicamentos);
    }
}


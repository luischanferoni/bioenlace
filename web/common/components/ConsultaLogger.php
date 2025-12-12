<?php

namespace common\components;

use Yii;

/**
 * Logger específico para análisis de consultas médicas
 * Crea un archivo de log individual por cada request de análisis
 */
class ConsultaLogger
{
    private static $instancia = null;
    private $archivoLog = null;
    private $idConsulta = null;
    private $inicioTiempo = null;
    private $contexto = [];

    /**
     * Inicializar logger para una nueva consulta
     * @param string $textoConsulta
     * @param array $contexto
     * @return ConsultaLogger
     */
    public static function iniciar($textoConsulta, $contexto = [])
    {
        if (self::$instancia) {
            self::$instancia->finalizar(['error' => 'Logger anterior no finalizado']);
        }

        self::$instancia = new self();
        self::$instancia->idConsulta = self::generarIdUnico();
        self::$instancia->inicioTiempo = microtime(true);
        self::$instancia->contexto = $contexto;
        
        // Crear directorio si no existe
        $directorioLogs = Yii::getAlias('@frontend/runtime/logs/analisis-consultas');
        if (!is_dir($directorioLogs)) {
            mkdir($directorioLogs, 0755, true);
        }

        // Crear archivo de log
        $timestamp = date('Ymd_His');
        $nombreArchivo = "consulta_{$timestamp}_{" . self::$instancia->idConsulta . "}.log";
        self::$instancia->archivoLog = $directorioLogs . '/' . $nombreArchivo;

        // Escribir encabezado
        self::$instancia->escribirEncabezado($textoConsulta);

        return self::$instancia;
    }

    /**
     * Obtener instancia activa del logger
     * @return ConsultaLogger|null
     */
    public static function obtenerInstancia()
    {
        return self::$instancia;
    }

    /**
     * Registrar un paso del procesamiento
     * @param string $paso
     * @param string $entrada
     * @param string $salida
     * @param array $metadata
     */
    public function registrar($paso, $entrada, $salida, $metadata = [])
    {
        if (!$this->archivoLog) {
            return;
        }

        $timestamp = $this->obtenerTimestamp();
        $metodo = $metadata['metodo'] ?? 'Desconocido';

        $linea = sprintf(
            "[%s] %s - %s\n",
            $timestamp,
            $paso,
            $metodo
        );

        // Entrada completa
        if ($entrada) {
            $linea .= "→ Entrada:\n" . $this->formatearTexto($entrada) . "\n";
        }

        // Salida completa
        if ($salida) {
            $linea .= "→ Salida:\n" . $this->formatearTexto($salida) . "\n";
        }

        // Metadata adicional
        if (!empty($metadata['cambios'])) {
            $linea .= "  Cambios: " . $metadata['cambios'] . "\n";
        }
        if (!empty($metadata['confianza'])) {
            $linea .= "  Confianza: " . $metadata['confianza'] . "\n";
        }
        if (!empty($metadata['total_cambios'])) {
            $linea .= "  Total cambios: " . $metadata['total_cambios'] . "\n";
        }
        if (!empty($metadata['abreviaturas_encontradas'])) {
            $linea .= "  Abreviaturas encontradas: " . count($metadata['abreviaturas_encontradas']) . "\n";
        }
        if (!empty($metadata['categorias_extraidas'])) {
            $linea .= "  Categorías extraídas: " . $metadata['categorias_extraidas'] . "\n";
        }
        if (!empty($metadata['cambios_detallados'])) {
            $linea .= "  Cambios detallados:\n";
            
            // Manejar tanto arrays como JSON strings
            $cambiosDetallados = $metadata['cambios_detallados'];
            if (is_string($cambiosDetallados)) {
                // Si es JSON string, decodificar
                $cambiosArray = json_decode($cambiosDetallados, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($cambiosArray)) {
                    foreach ($cambiosArray as $cambio) {
                        if (is_array($cambio)) {
                            // Si es un array complejo, mostrar información estructurada
                            $linea .= "    - " . ($cambio['original'] ?? '') . " → " . ($cambio['corrected'] ?? '') . 
                                     " (confianza: " . ($cambio['confidence'] ?? 'N/A') . ")\n";
                        } else {
                            $linea .= "    - " . $cambio . "\n";
                        }
                    }
                } else {
                    // Si no se puede decodificar, mostrar como string
                    $linea .= "    - " . $cambiosDetallados . "\n";
                }
            } elseif (is_array($cambiosDetallados)) {
                // Si es array, procesar normalmente
                foreach ($cambiosDetallados as $cambio) {
                    $linea .= "    - " . $cambio . "\n";
                }
            }
        }
        if (!empty($metadata['abreviaturas_detalladas'])) {
            $linea .= "  Abreviaturas detalladas:\n";
            foreach ($metadata['abreviaturas_detalladas'] as $abreviatura) {
                $linea .= "    - " . $abreviatura . "\n";
            }
        }
        
        // Metadata adicional para corrección IA
        if (!empty($metadata['proveedor'])) {
            $linea .= "  Proveedor: " . $metadata['proveedor'] . "\n";
        }
        if (!empty($metadata['modelo'])) {
            $linea .= "  Modelo: " . $metadata['modelo'] . "\n";
        }
        if (isset($metadata['longitud_texto'])) {
            $linea .= "  Longitud texto: " . $metadata['longitud_texto'] . " caracteres\n";
        }
        if (!empty($metadata['especialidad'])) {
            $linea .= "  Especialidad: " . $metadata['especialidad'] . "\n";
        }
        if (isset($metadata['confidence'])) {
            $linea .= "  Confianza: " . $metadata['confidence'] . "\n";
        }
        if (isset($metadata['tiempo'])) {
            $linea .= "  Tiempo procesamiento: " . $metadata['tiempo'] . " segundos\n";
        }
        if (isset($metadata['status_code'])) {
            $linea .= "  Status code: " . $metadata['status_code'] . "\n";
        }
        if (isset($metadata['respuesta_length'])) {
            $linea .= "  Longitud respuesta: " . $metadata['respuesta_length'] . " caracteres\n";
        }

        $this->escribir($linea . "\n");
    }

    /**
     * Finalizar el log con el resultado
     * @param array $resultado
     */
    public function finalizar($resultado)
    {
        if (!$this->archivoLog) {
            return;
        }

        $tiempoTotal = microtime(true) - $this->inicioTiempo;
        $timestamp = $this->obtenerTimestamp();

        $linea = sprintf(
            "[%s] FINALIZACIÓN - ConsultaController::actionAnalizar\n",
            $timestamp
        );

        $linea .= "Estado: " . ($resultado['success'] ? 'SUCCESS' : 'ERROR') . "\n";
        
        if (isset($resultado['tiene_datos_faltantes'])) {
            $linea .= "Datos faltantes: " . ($resultado['tiene_datos_faltantes'] ? 'SÍ' : 'NO') . "\n";
        }
        
        if (isset($resultado['requiere_validacion'])) {
            $linea .= "Requiere validación: " . ($resultado['requiere_validacion'] ? 'SÍ' : 'NO') . "\n";
        }

        $this->escribir($linea . "\n");

        // Pie del log
        $this->escribirPie($tiempoTotal);

        // Cerrar archivo
        if (is_resource($this->archivoLog)) {
            fclose($this->archivoLog);
        }

        // Limpiar instancia
        self::$instancia = null;
    }

    /**
     * Escribir encabezado del log
     * @param string $textoConsulta
     */
    private function escribirEncabezado($textoConsulta)
    {
        $timestamp = date('Y-m-d H:i:s');
        $medicoId = $this->contexto['idRrHhServicio'] ?? 'N/A';
        $servicio = $this->contexto['servicio'] ?? 'N/A';

        $encabezado = str_repeat('=', 80) . "\n";
        $encabezado .= "ANÁLISIS DE CONSULTA - {$timestamp}\n";
        $encabezado .= "ID: {$this->idConsulta}\n";
        $encabezado .= "Médico ID: {$medicoId}\n";
        $encabezado .= "Servicio: {$servicio}\n";
        $encabezado .= str_repeat('=', 80) . "\n\n";

        $encabezado .= "[{$this->obtenerTimestamp()}] INICIO - ConsultaController::actionAnalizar\n";
        $encabezado .= "Texto Original:\n";
        $encabezado .= $this->formatearTexto($textoConsulta) . "\n\n";

        $this->escribir($encabezado);
    }

    /**
     * Escribir pie del log
     * @param float $tiempoTotal
     */
    private function escribirPie($tiempoTotal)
    {
        $pie = "\n" . str_repeat('=', 80) . "\n";
        $pie .= "DURACIÓN TOTAL: " . round($tiempoTotal * 1000) . "ms\n";
        $pie .= str_repeat('=', 80) . "\n";

        $this->escribir($pie);
    }

    /**
     * Escribir línea al archivo
     * @param string $linea
     */
    private function escribir($linea)
    {
        if (!$this->archivoLog) {
            return;
        }

        $archivo = fopen($this->archivoLog, 'a');
        if ($archivo) {
            fwrite($archivo, $linea);
            fclose($archivo);
        }
    }

    /**
     * Obtener timestamp con milisegundos
     * @return string
     */
    private function obtenerTimestamp()
    {
        $tiempo = microtime(true);
        $segundos = floor($tiempo);
        $milisegundos = round(($tiempo - $segundos) * 1000);
        
        return date('H:i:s', $segundos) . '.' . str_pad($milisegundos, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Formatear texto para el log con indentación
     * @param string $texto
     * @return string
     */
    private function formatearTexto($texto)
    {
        if (empty($texto)) {
            return '  (vacío)';
        }
        
        // Agregar indentación a cada línea
        $lineas = explode("\n", $texto);
        $lineasFormateadas = [];
        
        foreach ($lineas as $linea) {
            $lineasFormateadas[] = '  ' . $linea;
        }
        
        return implode("\n", $lineasFormateadas);
    }

    /**
     * Generar ID único para la consulta
     * @return string
     */
    private static function generarIdUnico()
    {
        return substr(uniqid(), -12);
    }

    /**
     * Obtener ruta del archivo de log actual
     * @return string|null
     */
    public function getArchivoLog()
    {
        return $this->archivoLog;
    }

    /**
     * Obtener ID de la consulta actual
     * @return string|null
     */
    public function getIdConsulta()
    {
        return $this->idConsulta;
    }
}

<?php

namespace common\components\Domain\Clinical\Legacy;

use Yii;

/**
 * Logger de análisis de captura clínica (un archivo por request).
 */
class ConsultaLogger
{
    private static $instancia = null;
    private $archivoLog = null;
    private $idConsulta = null;
    private $inicioTiempo = null;
    private $contexto = [];

    /**
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

        $directorioLogs = Yii::getAlias('@frontend/runtime/logs/analisis-consultas');
        if (!is_dir($directorioLogs)) {
            mkdir($directorioLogs, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $nombreArchivo = "consulta_{$timestamp}_{" . self::$instancia->idConsulta . "}.log";
        self::$instancia->archivoLog = $directorioLogs . '/' . $nombreArchivo;
        self::$instancia->escribirEncabezado($textoConsulta);

        return self::$instancia;
    }

    public static function obtenerInstancia()
    {
        return self::$instancia;
    }

    /**
     * @param string $paso
     * @param string|array|null $entrada
     * @param string|array|null $salida
     * @param array $metadata
     */
    public function registrar($paso, $entrada, $salida, $metadata = [])
    {
        if (!$this->archivoLog) {
            return;
        }

        $metodo = $metadata['metodo'] ?? 'Desconocido';
        $linea = sprintf(
            "[%s] %s - %s\n",
            $this->obtenerTimestamp(),
            $paso,
            $metodo
        );

        if ($entrada !== null && $entrada !== '') {
            $linea .= "→ Entrada:\n" . $this->formatearValor($entrada) . "\n";
        }
        if ($salida !== null && $salida !== '') {
            $linea .= "→ Salida:\n" . $this->formatearValor($salida) . "\n";
        }

        foreach (['proveedor', 'status_code', 'categorias_extraidas', 'error'] as $key) {
            if (!array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                continue;
            }
            $linea .= '  ' . $key . ': ' . $metadata[$key] . "\n";
        }

        $this->escribir($linea . "\n");
    }

    /**
     * Registra el JSON estructurado devuelto por la extracción IA.
     *
     * @param array|string $payload
     */
    public function registrarJsonIa($payload, string $metodo = 'IA extracción'): void
    {
        $this->registrar('IA JSON', null, $payload, ['metodo' => $metodo]);
    }

    /**
     * @param array $resultado
     */
    public function finalizar($resultado)
    {
        if (!$this->archivoLog) {
            return;
        }

        $tiempoTotal = microtime(true) - $this->inicioTiempo;
        $linea = sprintf(
            "[%s] FINALIZACIÓN\n",
            $this->obtenerTimestamp()
        );
        $linea .= 'Estado: ' . (!empty($resultado['success']) ? 'SUCCESS' : 'ERROR') . "\n";

        if (isset($resultado['tiene_datos_faltantes'])) {
            $linea .= 'Datos faltantes: ' . ($resultado['tiene_datos_faltantes'] ? 'SÍ' : 'NO') . "\n";
        }
        if (!empty($resultado['texto_procesado']) && is_string($resultado['texto_procesado'])) {
            $tp = $resultado['texto_procesado'];
            if (mb_strlen($tp) > 280) {
                $tp = mb_substr($tp, 0, 280) . '…';
            }
            $linea .= "texto_procesado:\n" . $this->formatearTexto($tp) . "\n";
        }
        $extraidos = $resultado['datos']['datosExtraidos']
            ?? $resultado['datosExtraidos']
            ?? null;
        if (is_array($extraidos)) {
            $counts = [];
            foreach ($extraidos as $cat => $rows) {
                if ($cat === 'Error') {
                    continue;
                }
                $n = is_array($rows) ? count($rows) : (trim((string) $rows) !== '' ? 1 : 0);
                if ($n > 0) {
                    $counts[] = $cat . '=' . $n;
                }
            }
            if ($counts !== []) {
                $linea .= 'Categorías: ' . implode(', ', $counts) . "\n";
            }
        }

        $this->escribir($linea . "\n");
        $this->escribirPie($tiempoTotal);

        if (is_resource($this->archivoLog)) {
            fclose($this->archivoLog);
        }
        self::$instancia = null;
    }

    private function escribirEncabezado($textoConsulta)
    {
        $timestamp = date('Y-m-d H:i:s');
        $medicoId = $this->contexto['id_profesional_efector_servicio']
            ?? $this->contexto['idRrHhServicio']
            ?? 'N/A';
        $servicio = $this->contexto['servicio'] ?? 'N/A';

        $encabezado = str_repeat('=', 80) . "\n";
        $encabezado .= "ANÁLISIS DE CONSULTA - {$timestamp}\n";
        $encabezado .= "ID: {$this->idConsulta}\n";
        $encabezado .= "Médico ID: {$medicoId}\n";
        $encabezado .= "Servicio: {$servicio}\n";
        $encabezado .= str_repeat('=', 80) . "\n\n";
        $encabezado .= "[{$this->obtenerTimestamp()}] INICIO\n";
        $encabezado .= "Texto original:\n";
        $encabezado .= $this->formatearTexto($textoConsulta) . "\n\n";

        $this->escribir($encabezado);
    }

    private function escribirPie($tiempoTotal)
    {
        $pie = "\n" . str_repeat('=', 80) . "\n";
        $pie .= 'DURACIÓN TOTAL: ' . round($tiempoTotal * 1000) . "ms\n";
        $pie .= str_repeat('=', 80) . "\n";
        $this->escribir($pie);
    }

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

    private function obtenerTimestamp()
    {
        $tiempo = microtime(true);
        $segundos = floor($tiempo);
        $milisegundos = round(($tiempo - $segundos) * 1000);

        return date('H:i:s', $segundos) . '.' . str_pad($milisegundos, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param mixed $valor
     */
    private function formatearValor($valor): string
    {
        if (is_array($valor)) {
            $json = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            return $this->formatearTexto($json !== false ? $json : '(json inválido)');
        }

        return $this->formatearTexto((string) $valor);
    }

    private function formatearTexto($texto)
    {
        if ($texto === null || $texto === '') {
            return '  (vacío)';
        }
        $lineas = explode("\n", (string) $texto);
        $out = [];
        foreach ($lineas as $linea) {
            $out[] = '  ' . $linea;
        }

        return implode("\n", $out);
    }

    private static function generarIdUnico()
    {
        return substr(uniqid(), -12);
    }

    public function getArchivoLog()
    {
        return $this->archivoLog;
    }

    public function getIdConsulta()
    {
        return $this->idConsulta;
    }
}

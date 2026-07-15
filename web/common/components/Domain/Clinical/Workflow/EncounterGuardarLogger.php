<?php

namespace common\components\Domain\Clinical\Workflow;

use Yii;

/**
 * Logger de guardar encounter (mismo estilo que análisis de consulta).
 * Archivos en @frontend/runtime/logs/guardar-encounters/
 */
final class EncounterGuardarLogger
{
    private ?string $archivoLog = null;
    private $inicioTiempo = null;
    private string $id = '';
    /** @var list<string> */
    private array $lineBuffer = [];

    /**
     * @param array<string, mixed> $contexto
     */
    public static function iniciar(string $textoNota, array $contexto = []): self
    {
        $logger = new self();
        $logger->id = substr(uniqid('', true), -12);
        $logger->inicioTiempo = microtime(true);
        $logger->archivoLog = self::resolveLogPath($logger->id);
        $logger->escribirEncabezado($textoNota, $contexto);

        return $logger;
    }

    private static function resolveLogPath(string $id): ?string
    {
        $candidates = [];
        try {
            $candidates[] = Yii::getAlias('@frontend/runtime/logs/guardar-encounters');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $candidates[] = Yii::getAlias('@runtime/logs/guardar-encounters');
        } catch (\Throwable $e) {
            // ignore
        }
        $candidates[] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bioenlace-guardar-encounters';

        foreach ($candidates as $dir) {
            if ($dir === '' || $dir === false) {
                continue;
            }
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                continue;
            }
            if (!is_writable($dir)) {
                continue;
            }

            return $dir . DIRECTORY_SEPARATOR . 'guardar_' . date('Ymd_His') . '_' . $id . '.log';
        }

        Yii::error('EncounterGuardarLogger: no se pudo crear directorio de logs', 'encounter-doc');

        return null;
    }

    /**
     * @param mixed $entrada
     * @param mixed $salida
     * @param array<string, mixed> $metadata
     */
    public function registrar(string $paso, $entrada = null, $salida = null, array $metadata = []): void
    {
        $metodo = (string) ($metadata['metodo'] ?? '');
        $linea = sprintf("[%s] %s%s\n", $this->ts(), $paso, $metodo !== '' ? ' - ' . $metodo : '');
        if ($entrada !== null && $entrada !== '') {
            $linea .= "→ Entrada:\n" . $this->format($entrada) . "\n";
        }
        if ($salida !== null && $salida !== '') {
            $linea .= "→ Salida:\n" . $this->format($salida) . "\n";
        }
        foreach ($metadata as $key => $value) {
            if ($key === 'metodo' || $value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $linea .= '  ' . $key . ': ' . $value . "\n";
        }
        $this->escribir($linea . "\n");
        // También a Yii runtime.log para no depender solo del archivo dedicado.
        Yii::info(trim(preg_replace('/\s+/', ' ', $linea) ?? $linea), 'encounter-guardar');
    }

    /**
     * @param array<string, mixed> $resultado
     */
    public function finalizar(array $resultado): void
    {
        $linea = sprintf("[%s] FINALIZACIÓN\n", $this->ts());
        $linea .= 'Estado: ' . (!empty($resultado['success']) ? 'SUCCESS' : 'ERROR') . "\n";
        if (!empty($resultado['message'])) {
            $linea .= 'Mensaje: ' . $resultado['message'] . "\n";
        }
        if (!empty($resultado['encounter_id'])) {
            $linea .= 'encounter_id: ' . $resultado['encounter_id'] . "\n";
        }
        if (isset($resultado['persistido']) && is_array($resultado['persistido'])) {
            $p = $resultado['persistido'];
            $linea .= sprintf(
                "Resumen persistido: note=%s reason=%s conditions=%s meds=%s srs=%s care_plans=%s\n",
                !empty($p['note']) ? 'SI' : 'NO',
                !empty($p['reason_text']) ? 'SI' : 'NO',
                (string) ($p['conditions'] ?? 0),
                (string) ($p['medication_requests'] ?? 0),
                (string) ($p['service_requests'] ?? 0),
                (string) ($p['care_plans'] ?? 0)
            );
            $linea .= "persistido:\n" . $this->format($p) . "\n";
            $incompleto = empty($p['note'])
                || ((int) ($p['medication_requests'] ?? 0) <= 0 && !empty($resultado['diagnostico_guardar']['final_counts']['Medicación']))
                || (empty($p['reason_text']) && !empty($resultado['diagnostico_guardar']['final_counts']['Motivos de consulta']));
            if ($incompleto) {
                $linea .= "⚠ PERSISTENCIA INCOMPLETA — revisar staged/final/por_modelo abajo\n";
            }
        }
        if (isset($resultado['diagnostico_guardar']) && is_array($resultado['diagnostico_guardar'])) {
            $linea .= "diagnostico_guardar:\n" . $this->format($resultado['diagnostico_guardar']) . "\n";
        }
        if ($this->archivoLog !== null) {
            $linea .= 'archivo_log: ' . $this->archivoLog . "\n";
        }
        $this->escribir($linea . "\n");

        $elapsed = microtime(true) - (float) $this->inicioTiempo;
        $this->escribir(
            "\n" . str_repeat('=', 80) . "\n"
            . 'DURACIÓN TOTAL: ' . round($elapsed * 1000) . "ms\n"
            . str_repeat('=', 80) . "\n"
        );

        Yii::warning(
            'encounter.guardar fin log_id=' . $this->id
            . ' success=' . (!empty($resultado['success']) ? '1' : '0')
            . ' encounter=' . ($resultado['encounter_id'] ?? '-')
            . ' meds=' . ($resultado['persistido']['medication_requests'] ?? '-')
            . ' archivo=' . ($this->archivoLog ?? 'none'),
            'encounter-guardar'
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getArchivoLog(): ?string
    {
        return $this->archivoLog;
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function escribirEncabezado(string $textoNota, array $contexto): void
    {
        $linea = str_repeat('=', 80) . "\n";
        $linea .= 'GUARDAR ENCOUNTER - ' . date('Y-m-d H:i:s') . "\n";
        $linea .= 'ID: ' . $this->id . "\n";
        foreach (['id_persona', 'encounter_id', 'parent', 'parent_id', 'id_configuracion'] as $key) {
            if (array_key_exists($key, $contexto) && $contexto[$key] !== null && $contexto[$key] !== '') {
                $linea .= $key . ': ' . $contexto[$key] . "\n";
            }
        }
        $linea .= str_repeat('=', 80) . "\n\n";
        $linea .= '[' . $this->ts() . "] INICIO\n";
        $linea .= "Nota / texto:\n" . $this->format($textoNota) . "\n\n";
        $this->escribir($linea);
    }

    /**
     * @param mixed $valor
     */
    private function format($valor): string
    {
        if (is_array($valor)) {
            $json = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $valor = $json !== false ? $json : '(json inválido)';
        }
        $text = (string) $valor;
        if ($text === '') {
            return '  (vacío)';
        }
        $out = [];
        foreach (explode("\n", $text) as $line) {
            $out[] = '  ' . $line;
        }

        return implode("\n", $out);
    }

    private function ts(): string
    {
        $t = microtime(true);
        $s = (int) floor($t);
        $ms = (int) round(($t - $s) * 1000);

        return date('H:i:s', $s) . '.' . str_pad((string) $ms, 3, '0', STR_PAD_LEFT);
    }

    private function escribir(string $linea): void
    {
        $this->lineBuffer[] = $linea;
        if ($this->archivoLog === null) {
            return;
        }
        $fh = @fopen($this->archivoLog, 'a');
        if ($fh) {
            fwrite($fh, $linea);
            fclose($fh);
        }
    }
}

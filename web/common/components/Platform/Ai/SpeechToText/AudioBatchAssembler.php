<?php

namespace common\components\Platform\Ai\SpeechToText;

use Yii;

/**
 * Concatena varios archivos de audio en uno (FFmpeg) para una sola llamada STT.
 * Normaliza a WAV 16 kHz mono para unificar formatos (m4a, opus, etc.).
 */
final class AudioBatchAssembler
{
    /**
     * @param list<string> $paths Rutas absolutas existentes
     * @return string|null Ruta de archivo temporal (el caller debe borrarlo), o null si falla
     */
    public static function assembleToTempFile(array $paths): ?string
    {
        $existing = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path !== '' && is_file($path) && filesize($path) > 0) {
                $existing[] = $path;
            }
        }
        if ($existing === []) {
            return null;
        }
        // Un solo archivo: devolver la ruta original (el caller no la borra).
        if (count($existing) === 1) {
            return $existing[0];
        }

        $ffmpeg = Yii::$app->params['ffmpeg_path'] ?? 'ffmpeg';
        $normalized = [];
        $listFile = null;
        $output = null;

        try {
            foreach ($existing as $i => $src) {
                $tmp = tempnam(sys_get_temp_dir(), 'stt_norm_');
                if ($tmp === false) {
                    throw new \RuntimeException('No se pudo crear temp de normalización');
                }
                $wav = $tmp . '.wav';
                @unlink($tmp);
                $cmd = sprintf(
                    '%s -i %s -ar 16000 -ac 1 -c:a pcm_s16le %s -y 2>&1',
                    escapeshellarg($ffmpeg),
                    escapeshellarg($src),
                    escapeshellarg($wav)
                );
                exec($cmd, $out, $code);
                if ($code !== 0 || !is_file($wav) || filesize($wav) === 0) {
                    Yii::warning(
                        'AudioBatchAssembler: falló normalizar #' . $i . ' code=' . $code,
                        'speech-to-text'
                    );
                    @unlink($wav);
                    continue;
                }
                $normalized[] = $wav;
            }

            if ($normalized === []) {
                return null;
            }
            if (count($normalized) === 1) {
                return $normalized[0];
            }

            $listFile = tempnam(sys_get_temp_dir(), 'stt_concat_list_');
            if ($listFile === false) {
                throw new \RuntimeException('No se pudo crear lista concat');
            }
            $listBody = '';
            foreach ($normalized as $n) {
                $safe = str_replace("'", "'\\''", str_replace('\\', '/', $n));
                $listBody .= "file '{$safe}'\n";
            }
            file_put_contents($listFile, $listBody);

            $outTmp = tempnam(sys_get_temp_dir(), 'stt_batch_out_');
            if ($outTmp === false) {
                throw new \RuntimeException('No se pudo crear salida concat');
            }
            $output = $outTmp . '.wav';
            @unlink($outTmp);

            $cmd = sprintf(
                '%s -f concat -safe 0 -i %s -c:a pcm_s16le %s -y 2>&1',
                escapeshellarg($ffmpeg),
                escapeshellarg($listFile),
                escapeshellarg($output)
            );
            exec($cmd, $out2, $code2);
            if ($code2 !== 0 || !is_file($output) || filesize($output) === 0) {
                Yii::error(
                    'AudioBatchAssembler: falló concat code=' . $code2 . ' ' . implode("\n", $out2 ?? []),
                    'speech-to-text'
                );
                @unlink($output);

                return null;
            }

            Yii::info(
                'AudioBatchAssembler: ' . count($normalized) . ' audios → 1 archivo',
                'speech-to-text'
            );

            return $output;
        } catch (\Throwable $e) {
            Yii::error('AudioBatchAssembler: ' . $e->getMessage(), 'speech-to-text');
            if ($output !== null) {
                @unlink($output);
            }

            return null;
        } finally {
            if ($listFile !== null) {
                @unlink($listFile);
            }
            // Borrar normalizados intermedios salvo el único que se devolvió
            // (si count===1 ya returneamos antes del finally con ese path; aquí count>1)
            if (isset($normalized) && is_array($normalized) && count($normalized) > 1) {
                foreach ($normalized as $n) {
                    @unlink($n);
                }
            }
        }
    }

    /**
     * Hash estable del contenido de varios archivos (para caché STT de lote).
     *
     * @param list<string> $paths
     */
    public static function contentFingerprint(array $paths): string
    {
        $parts = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            if (!is_file($path)) {
                continue;
            }
            $parts[] = basename($path) . ':' . filesize($path) . ':' . filemtime($path);
        }

        return hash('sha256', implode('|', $parts));
    }
}

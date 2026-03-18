<?php

namespace common\components\Ai\SpeechToText;

use Yii;

final class AudioPreprocessor
{
    /**
     * @param string $audioPath data URI/base64/path
     * @param array $opciones
     * @param int $maxAudioSize
     * @return string audio binary
     */
    public static function preprocesarAudio($audioPath, $opciones, $maxAudioSize)
    {
        if (preg_match('/^data:audio\/(\w+);base64,/', $audioPath)) {
            $base64 = substr($audioPath, strpos($audioPath, ',') + 1);
            $audioData = base64_decode($base64);
        } elseif (file_exists($audioPath)) {
            $audioData = file_get_contents($audioPath);
        } else {
            $audioData = base64_decode($audioPath);
        }

        if (!$audioData || strlen($audioData) > $maxAudioSize) {
            throw new \Exception("Audio inválido o excede el tamaño máximo permitido");
        }

        $optimizar = $opciones['optimizar'] ?? (Yii::$app->params['optimizar_audio'] ?? true);
        if ($optimizar) {
            $usarChunking = $opciones['chunking'] ?? (Yii::$app->params['chunk_audio_duration'] ?? true);
            if ($usarChunking) {
                $audioData = self::aplicarChunkingInteligente($audioData, $opciones);
            }

            $audioData = self::eliminarSilencios($audioData);
            $audioData = self::comprimirAudio($audioData);
        }

        return $audioData;
    }

    private static function aplicarChunkingInteligente($audioData, $opciones = [])
    {
        $ffmpegPath = Yii::$app->params['ffmpeg_path'] ?? 'ffmpeg';
        $duracionChunk = $opciones['chunk_duration'] ?? (Yii::$app->params['chunk_audio_duration'] ?? 10);

        $tempInput = tempnam(sys_get_temp_dir(), 'audio_chunk_input_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'audio_chunk_output_');

        try {
            file_put_contents($tempInput, $audioData);

            $command = sprintf(
                '%s -i %s -af silencedetect=noise=%sdB:d=0.5 -f null - 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($tempInput)
            );

            $output = shell_exec($command);
            $segmentosVoz = self::extraerSegmentosConVoz($output, $tempInput, $ffmpegPath, $duracionChunk);

            if (empty($segmentosVoz)) {
                return $audioData;
            }

            $audioChunked = self::concatenarSegmentos($segmentosVoz, $ffmpegPath, $tempOutput);

            @unlink($tempInput);
            foreach ($segmentosVoz as $segmento) {
                @unlink($segmento['archivo']);
            }

            Yii::info("Chunking inteligente: " . count($segmentosVoz) . " segmentos con voz extraídos", 'speech-to-text');
            return $audioChunked;
        } catch (\Exception $e) {
            Yii::warning("Error en chunking inteligente: " . $e->getMessage() . ". Usando audio completo.", 'speech-to-text');
            @unlink($tempInput);
            @unlink($tempOutput);
            return $audioData;
        }
    }

    private static function extraerSegmentosConVoz($output, $audioPath, $ffmpegPath, $duracionChunk)
    {
        $segmentos = [];

        preg_match_all('/silence_start: ([\d.]+)/', $output, $silenciosInicio);
        preg_match_all('/silence_end: ([\d.]+)/', $output, $silenciosFin);

        $inicioAudio = 0;
        $indiceSilencio = 0;

        while ($inicioAudio < 3600) {
            $finSegmento = $inicioAudio + $duracionChunk;

            if (isset($silenciosInicio[1][$indiceSilencio])) {
                $inicioSilencio = (float)$silenciosInicio[1][$indiceSilencio];
                if ($inicioSilencio > $inicioAudio && $inicioSilencio < $finSegmento) {
                    $finSegmento = $inicioSilencio;
                    $indiceSilencio++;
                }
            }

            if ($finSegmento - $inicioAudio >= 0.5) {
                $tempSegmento = tempnam(sys_get_temp_dir(), 'audio_segment_');

                $command = sprintf(
                    '%s -i %s -ss %.2f -t %.2f -acodec copy %s -y 2>&1',
                    escapeshellarg($ffmpegPath),
                    escapeshellarg($audioPath),
                    $inicioAudio,
                    $finSegmento - $inicioAudio,
                    escapeshellarg($tempSegmento)
                );

                shell_exec($command);

                if (file_exists($tempSegmento) && filesize($tempSegmento) > 0) {
                    $segmentos[] = [
                        'inicio' => $inicioAudio,
                        'fin' => $finSegmento,
                        'archivo' => $tempSegmento,
                    ];
                }
            }

            $inicioAudio = $finSegmento;
            if (!isset($silenciosInicio[1][$indiceSilencio])) {
                break;
            }
        }

        return $segmentos;
    }

    private static function concatenarSegmentos($segmentos, $ffmpegPath, $outputPath)
    {
        if (empty($segmentos)) {
            return '';
        }

        $concatList = tempnam(sys_get_temp_dir(), 'concat_list_');
        $listContent = '';
        foreach ($segmentos as $segmento) {
            $listContent .= "file '" . str_replace('\\', '/', $segmento['archivo']) . "'\n";
        }
        file_put_contents($concatList, $listContent);

        $command = sprintf(
            '%s -f concat -safe 0 -i %s -acodec copy %s -y 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($concatList),
            escapeshellarg($outputPath)
        );
        shell_exec($command);

        $audioConcatenado = file_exists($outputPath) ? file_get_contents($outputPath) : '';
        @unlink($concatList);
        return $audioConcatenado;
    }

    private static function comprimirAudio($audioData)
    {
        $ffmpegPath = Yii::$app->params['ffmpeg_path'] ?? 'ffmpeg';

        $tempInput = tempnam(sys_get_temp_dir(), 'audio_input_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'audio_output_');

        try {
            file_put_contents($tempInput, $audioData);

            $command = sprintf(
                '%s -i %s -ar 16000 -ac 1 -b:a 32k -f opus %s -y 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($tempInput),
                escapeshellarg($tempOutput)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($tempOutput) || filesize($tempOutput) === 0) {
                $errorMsg = "Error comprimiendo audio. FFmpeg retornó código: {$returnCode}. " . implode("\n", $output);
                Yii::error($errorMsg, 'speech-to-text');
                throw new \Exception($errorMsg);
            }

            $compressed = file_get_contents($tempOutput);
            if (empty($compressed)) {
                throw new \Exception("Audio comprimido está vacío");
            }

            Yii::info("Audio comprimido: " . strlen($audioData) . " -> " . strlen($compressed) . " bytes", 'speech-to-text');
            return $compressed;
        } catch (\Exception $e) {
            if (file_exists($tempInput)) {
                @unlink($tempInput);
            }
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
            throw $e;
        }
    }

    private static function eliminarSilencios($audioData)
    {
        $ffmpegPath = Yii::$app->params['ffmpeg_path'] ?? 'ffmpeg';

        $tempInput = tempnam(sys_get_temp_dir(), 'audio_input_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'audio_output_');

        try {
            file_put_contents($tempInput, $audioData);

            $command = sprintf(
                '%s -i %s -af "silenceremove=start_periods=1:start_duration=0.1:start_threshold=-30dB:detection=peak,aformat=dblp,areverse,silenceremove=start_periods=1:start_duration=0.1:start_threshold=-30dB:detection=peak,aformat=dblp,areverse" %s -y 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($tempInput),
                escapeshellarg($tempOutput)
            );

            exec($command, $output, $returnCode);
            if ($returnCode !== 0 || !file_exists($tempOutput) || filesize($tempOutput) === 0) {
                $errorMsg = "Error eliminando silencios. FFmpeg retornó código: {$returnCode}. " . implode("\n", $output);
                Yii::error($errorMsg, 'speech-to-text');
                throw new \Exception($errorMsg);
            }

            $processed = file_get_contents($tempOutput);
            if (empty($processed)) {
                throw new \Exception("Audio procesado está vacío después de eliminar silencios");
            }

            Yii::info("Silencios eliminados del audio. Tamaño original: " . strlen($audioData) . " -> " . strlen($processed) . " bytes", 'speech-to-text');
            return $processed;
        } catch (\Exception $e) {
            if (file_exists($tempInput)) {
                @unlink($tempInput);
            }
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
            throw $e;
        }
    }
}


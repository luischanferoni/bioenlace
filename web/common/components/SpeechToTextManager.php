<?php

namespace common\components;

use Yii;
use yii\httpclient\Client;

/**
 * Gestor de conversión de audio a texto usando HuggingFace
 * Optimizado para reducir costos mediante pre-procesamiento y selección de modelos
 */
class SpeechToTextManager
{
    private const CACHE_TTL = 86400; // 24 horas para transcripciones
    private const MAX_AUDIO_SIZE = 25 * 1024 * 1024; // 25MB máximo
    
    /**
     * Modelos disponibles ordenados por costo (de menor a mayor)
     */
    private const MODELOS = [
        'economico' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish',
        'balanceado' => 'jonatasgrosman/wav2vec2-large-xlsr-53-spanish',
        'premium' => 'openai/whisper-large-v2'
    ];
    
    /**
     * Convertir audio a texto
     */
    public static function transcribir($audioPath, $modelo = 'economico', $opciones = [])
    {
        $inicio = microtime(true);
        
        try {
            // Verificar cache
            $cacheKey = 'stt_' . md5($audioPath . $modelo);
            $yiiCache = Yii::$app->cache;
            if ($yiiCache) {
                $cached = $yiiCache->get($cacheKey);
                if ($cached !== false) {
                    \Yii::info("Transcripción obtenida desde cache", 'speech-to-text');
                    return $cached;
                }
            }
            
            // Cargar y pre-procesar audio (puede lanzar excepción si falla optimización)
            $audioData = self::preprocesarAudio($audioPath, $opciones);
            
            // Seleccionar modelo
            $modeloSeleccionado = self::MODELOS[$modelo] ?? self::MODELOS['economico'];
            $modeloConfigurado = Yii::$app->params['hf_stt_model'] ?? null;
            
            // Registrar uso del modelo para gestión de memoria
            ModelManager::registrarUso($modeloSeleccionado, 'stt');
            
            // Verificar si el modelo debe estar cargado
            if (!ModelManager::debeEstarCargado($modeloSeleccionado, 'stt')) {
                \Yii::warning("Modelo STT no disponible en memoria: {$modeloSeleccionado}. Cargando...", 'speech-to-text');
                // En un sistema real, aquí se cargaría el modelo
            }
            if ($modeloConfigurado) {
                $modeloSeleccionado = $modeloConfigurado;
            }
            
            // Realizar transcripción
            $resultado = self::llamarAPIHuggingFace($audioData, $modeloSeleccionado);
            
            if ($resultado && !empty($resultado['texto'])) {
                // Guardar en cache
                if ($yiiCache) {
                    $yiiCache->set($cacheKey, $resultado, self::CACHE_TTL);
                }
                
                $tiempo = microtime(true) - $inicio;
                $resultado['tiempo_procesamiento'] = $tiempo;
                $resultado['modelo_usado'] = $modeloSeleccionado;
                
                \Yii::info("Audio transcrito exitosamente en {$tiempo}s", 'speech-to-text');
                return $resultado;
            }
            
            return [
                'texto' => '',
                'confidence' => 0,
                'error' => 'No se pudo transcribir el audio'
            ];
            
        } catch (\Exception $e) {
            \Yii::error("Error en transcripción de audio: " . $e->getMessage(), 'speech-to-text');
            return [
                'texto' => '',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private static function preprocesarAudio($audioPath, $opciones = [])
    {
        // Cargar audio
        if (preg_match('/^data:audio\/(\w+);base64,/', $audioPath, $matches)) {
            $base64 = substr($audioPath, strpos($audioPath, ',') + 1);
            $audioData = base64_decode($base64);
        } elseif (file_exists($audioPath)) {
            $audioData = file_get_contents($audioPath);
        } else {
            $audioData = base64_decode($audioPath);
        }
        
        if (!$audioData || strlen($audioData) > self::MAX_AUDIO_SIZE) {
            throw new \Exception("Audio inválido o excede el tamaño máximo permitido");
        }
        
        // Verificar si se debe optimizar (por defecto sí, a menos que se especifique)
        $optimizar = $opciones['optimizar'] ?? (Yii::$app->params['optimizar_audio'] ?? true);
        
        if ($optimizar) {
            // 1. Chunking inteligente: procesar solo partes con voz
            $usarChunking = $opciones['chunking'] ?? (Yii::$app->params['chunk_audio_duration'] ?? true);
            if ($usarChunking) {
                $audioData = self::aplicarChunkingInteligente($audioData, $opciones);
            }
            
            // 2. Eliminar silencios primero (reduce tamaño)
            // SIN FALLBACK: Si falla, lanza excepción
            $audioData = self::eliminarSilencios($audioData);
            
            // 3. Comprimir audio (reduce tamaño en 60-80%)
            // SIN FALLBACK: Si falla, lanza excepción
            $audioData = self::comprimirAudio($audioData);
        }
        
        return $audioData;
    }
    
    /**
     * Aplicar chunking inteligente para procesar solo partes con voz
     * @param string $audioData Datos de audio
     * @param array $opciones Opciones de chunking
     * @return string Audio procesado con solo partes con voz
     */
    private static function aplicarChunkingInteligente($audioData, $opciones = [])
    {
        $ffmpegPath = Yii::$app->params['ffmpeg_path'] ?? 'ffmpeg';
        $duracionChunk = $opciones['chunk_duration'] ?? (Yii::$app->params['chunk_audio_duration'] ?? 10); // 10 segundos por defecto
        $umbralVoz = $opciones['umbral_voz'] ?? -30; // dB threshold para detectar voz
        
        $tempInput = tempnam(sys_get_temp_dir(), 'audio_chunk_input_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'audio_chunk_output_');
        
        try {
            file_put_contents($tempInput, $audioData);
            
            // Detectar partes con voz usando silencedetect de FFmpeg
            $command = sprintf(
                '%s -i %s -af silencedetect=noise=%sdB:d=0.5 -f null - 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($tempInput)
            );
            
            $output = shell_exec($command);
            
            // Extraer segmentos con voz
            $segmentosVoz = self::extraerSegmentosConVoz($output, $tempInput, $ffmpegPath, $duracionChunk);
            
            if (empty($segmentosVoz)) {
                // Si no se detectaron segmentos, usar audio completo
                return $audioData;
            }
            
            // Concatenar solo los segmentos con voz
            $audioChunked = self::concatenarSegmentos($segmentosVoz, $ffmpegPath, $tempOutput);
            
            // Limpiar archivos temporales
            @unlink($tempInput);
            foreach ($segmentosVoz as $segmento) {
                @unlink($segmento['archivo']);
            }
            
            \Yii::info("Chunking inteligente: " . count($segmentosVoz) . " segmentos con voz extraídos", 'speech-to-text');
            
            return $audioChunked;
            
        } catch (\Exception $e) {
            // Si falla chunking, usar audio completo (pero registrar el error)
            \Yii::warning("Error en chunking inteligente: " . $e->getMessage() . ". Usando audio completo.", 'speech-to-text');
            @unlink($tempInput);
            @unlink($tempOutput);
            return $audioData;
        }
    }
    
    /**
     * Extraer segmentos con voz del audio
     * @param string $output Output de FFmpeg silencedetect
     * @param string $audioPath Ruta del audio
     * @param string $ffmpegPath Ruta de FFmpeg
     * @param int $duracionChunk Duración máxima de cada chunk
     * @return array Array de segmentos con voz
     */
    private static function extraerSegmentosConVoz($output, $audioPath, $ffmpegPath, $duracionChunk)
    {
        $segmentos = [];
        
        // Parsear output de silencedetect para encontrar silencios
        preg_match_all('/silence_start: ([\d.]+)/', $output, $silenciosInicio);
        preg_match_all('/silence_end: ([\d.]+)/', $output, $silenciosFin);
        
        $inicioAudio = 0;
        $indiceSilencio = 0;
        
        // Extraer segmentos entre silencios (partes con voz)
        while ($inicioAudio < 3600) { // Máximo 1 hora
            $finSegmento = $inicioAudio + $duracionChunk;
            
            // Verificar si hay silencio en este rango
            if (isset($silenciosInicio[1][$indiceSilencio])) {
                $inicioSilencio = (float)$silenciosInicio[1][$indiceSilencio];
                $finSilencio = isset($silenciosFin[1][$indiceSilencio]) ? (float)$silenciosFin[1][$indiceSilencio] : $finSegmento;
                
                if ($inicioSilencio > $inicioAudio && $inicioSilencio < $finSegmento) {
                    // Hay silencio en este segmento, cortar antes del silencio
                    $finSegmento = $inicioSilencio;
                    $indiceSilencio++;
                }
            }
            
            // Extraer segmento si tiene duración mínima (0.5 segundos)
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
                        'archivo' => $tempSegmento
                    ];
                }
            }
            
            $inicioAudio = $finSegmento;
            
            // Si no hay más silencios, terminar
            if (!isset($silenciosInicio[1][$indiceSilencio])) {
                break;
            }
        }
        
        return $segmentos;
    }
    
    /**
     * Concatenar segmentos de audio
     * @param array $segmentos Array de segmentos
     * @param string $ffmpegPath Ruta de FFmpeg
     * @param string $outputPath Ruta de salida
     * @return string Datos de audio concatenado
     */
    private static function concatenarSegmentos($segmentos, $ffmpegPath, $outputPath)
    {
        if (empty($segmentos)) {
            return '';
        }
        
        // Crear archivo de lista para FFmpeg concat
        $concatList = tempnam(sys_get_temp_dir(), 'concat_list_');
        $listContent = '';
        foreach ($segmentos as $segmento) {
            $listContent .= "file '" . str_replace('\\', '/', $segmento['archivo']) . "'\n";
        }
        file_put_contents($concatList, $listContent);
        
        // Concatenar segmentos
        $command = sprintf(
            '%s -f concat -safe 0 -i %s -acodec copy %s -y 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($concatList),
            escapeshellarg($outputPath)
        );
        
        shell_exec($command);
        
        // Leer audio concatenado
        $audioConcatenado = file_exists($outputPath) ? file_get_contents($outputPath) : '';
        
        @unlink($concatList);
        
        return $audioConcatenado;
    }
    
    /**
     * Comprimir audio para reducir tamaño antes de enviar a GPU
     * Optimización: Reduce sample rate, canales, bitrate
     * SIN FALLBACK: Si falla, lanza excepción
     */
    private static function comprimirAudio($audioData)
    {
        // Verificar si FFmpeg está disponible
        $ffmpegPath = Yii::$app->params['ffmpeg_path'] ?? 'ffmpeg';
        
        // Intentar comprimir con FFmpeg
        $tempInput = tempnam(sys_get_temp_dir(), 'audio_input_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'audio_output_');
        
        try {
            file_put_contents($tempInput, $audioData);
            
            // Comprimir: 16kHz, mono, OPUS, 32kbps
            $command = sprintf(
                '%s -i %s -ar 16000 -ac 1 -b:a 32k -f opus %s -y 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($tempInput),
                escapeshellarg($tempOutput)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0 || !file_exists($tempOutput) || filesize($tempOutput) === 0) {
                $errorMsg = "Error comprimiendo audio. FFmpeg retornó código: {$returnCode}. " . implode("\n", $output);
                \Yii::error($errorMsg, 'speech-to-text');
                throw new \Exception($errorMsg);
            }
            
            $compressed = file_get_contents($tempOutput);
            
            if (empty($compressed)) {
                throw new \Exception("Audio comprimido está vacío");
            }
            
            \Yii::info("Audio comprimido: " . strlen($audioData) . " -> " . strlen($compressed) . " bytes", 'speech-to-text');
            
            return $compressed;
            
        } catch (\Exception $e) {
            // Limpiar archivos temporales antes de lanzar excepción
            if (file_exists($tempInput)) {
                @unlink($tempInput);
            }
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
            throw $e; // SIN FALLBACK: Lanzar excepción
        }
    }
    
    /**
     * Eliminar silencios del audio antes de procesar
     * Optimización: Reduce tiempo de procesamiento
     * SIN FALLBACK: Si falla, lanza excepción
     */
    private static function eliminarSilencios($audioData)
    {
        // Verificar si FFmpeg está disponible
        $ffmpegPath = Yii::$app->params['ffmpeg_path'] ?? 'ffmpeg';
        
        $tempInput = tempnam(sys_get_temp_dir(), 'audio_input_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'audio_output_');
        
        try {
            file_put_contents($tempInput, $audioData);
            
            // Eliminar silencios: inicio, final y largos (>0.5s)
            // Usar silenceremove dos veces (ida y vuelta) para eliminar inicio y final
            $command = sprintf(
                '%s -i %s -af "silenceremove=start_periods=1:start_duration=0.1:start_threshold=-30dB:detection=peak,aformat=dblp,areverse,silenceremove=start_periods=1:start_duration=0.1:start_threshold=-30dB:detection=peak,aformat=dblp,areverse" %s -y 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($tempInput),
                escapeshellarg($tempOutput)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0 || !file_exists($tempOutput) || filesize($tempOutput) === 0) {
                $errorMsg = "Error eliminando silencios. FFmpeg retornó código: {$returnCode}. " . implode("\n", $output);
                \Yii::error($errorMsg, 'speech-to-text');
                throw new \Exception($errorMsg);
            }
            
            $processed = file_get_contents($tempOutput);
            
            if (empty($processed)) {
                throw new \Exception("Audio procesado está vacío después de eliminar silencios");
            }
            
            \Yii::info("Silencios eliminados del audio. Tamaño original: " . strlen($audioData) . " -> " . strlen($processed) . " bytes", 'speech-to-text');
            
            return $processed;
            
        } catch (\Exception $e) {
            // Limpiar archivos temporales antes de lanzar excepción
            if (file_exists($tempInput)) {
                @unlink($tempInput);
            }
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
            throw $e; // SIN FALLBACK: Lanzar excepción
        }
    }
    
    private static function llamarAPIHuggingFace($audioData, $modelo)
    {
        try {
            $apiKey = Yii::$app->params['hf_api_key'] ?? '';
            if (empty($apiKey)) {
                return null;
            }
            
            $audioBase64 = base64_encode($audioData);
            
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("https://api-inference.huggingface.co/models/{$modelo}")
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'inputs' => $audioBase64,
                    'options' => [
                        'wait_for_model' => false
                    ]
                ]))
                ->send();
            
            if ($response->isOk) {
                $data = json_decode($response->content, true);
                
                $texto = '';
                if (isset($data['text'])) {
                    $texto = $data['text'];
                } elseif (isset($data[0]['text'])) {
                    $texto = $data[0]['text'];
                } elseif (is_string($data)) {
                    $texto = $data;
                }
                
                if (!empty($texto)) {
                    return [
                        'texto' => trim($texto),
                        'confidence' => isset($data['confidence']) ? (float)$data['confidence'] : 0.8
                    ];
                }
            }
            
        } catch (\Exception $e) {
            \Yii::error("Error llamando API HuggingFace: " . $e->getMessage(), 'speech-to-text');
        }
        
        return null;
    }
}

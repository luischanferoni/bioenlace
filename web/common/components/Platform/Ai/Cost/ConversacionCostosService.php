<?php

namespace common\components\Platform\Ai\Cost;

use common\components\Platform\Assistant\Chat\ChatOrchestrator;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use Yii;

/**
 * Ejecuta conversaciones de prueba y devuelve métricas de {@see AICostTracker}.
 *
 * @see web/docs/costos/pruebas-costos-ia.md
 */
final class ConversacionCostosService
{
    private const CONVERSACIONES_DIR = '@common/data/conversaciones';

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarConversaciones(): array
    {
        $base = Yii::getAlias(self::CONVERSACIONES_DIR);
        if (!is_dir($base)) {
            return [];
        }

        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $rutaAbs = $file->getPathname();
            $rutaRel = self::rutaRelativaDesdeBase($base, $rutaAbs);
            $meta = self::cargarConversacion($rutaRel);

            $out[] = [
                'ruta' => $rutaRel,
                'tipo' => (string) ($meta['tipo'] ?? ''),
                'nombre' => (string) ($meta['nombre'] ?? $rutaRel),
                'descripcion' => (string) ($meta['descripcion'] ?? ''),
                'mensajes' => count(is_array($meta['mensajes'] ?? null) ? $meta['mensajes'] : []),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return strcmp($a['ruta'], $b['ruta']);
        });

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ejecutar(string $rutaRelativa, int $userId): array
    {
        self::validarRutaRelativa($rutaRelativa);
        $conversacion = self::cargarConversacion($rutaRelativa);
        $mensajes = $conversacion['mensajes'] ?? [];
        if (!is_array($mensajes) || $mensajes === []) {
            throw new \InvalidArgumentException('La conversación no tiene mensajes.');
        }

        AICostTracker::iniciarEjecucionPrueba();
        AICostTracker::reset();
        ChatPreprocessContext::clear();

        $detalle = [];
        foreach ($mensajes as $indice => $mensaje) {
            if (!is_string($mensaje) || trim($mensaje) === '') {
                continue;
            }

            $out = ChatOrchestrator::handle(['content' => trim($mensaje)], $userId);
            $detalle[] = [
                'indice' => (int) $indice,
                'mensaje' => $mensaje,
                'exito' => (bool) ($out['success'] ?? false),
                'user_goal' => ChatPreprocessContext::userGoal(),
                'respuesta' => ChatOrchestrator::botReplyTextForPersistence($out),
            ];
        }

        $resumen = AICostTracker::getResumen();
        $estimacion = AICostEstimationService::estimarDesdeResumen($resumen);
        AICostTracker::finalizarEjecucionPrueba();

        return [
            'conversacion' => $conversacion,
            'ruta' => $rutaRelativa,
            'detalle' => $detalle,
            'resumen' => $resumen,
            'estimacion' => $estimacion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ejecutarTodas(int $userId): array
    {
        $resultados = [];
        foreach (self::listarConversaciones() as $item) {
            $resultados[] = self::ejecutar((string) $item['ruta'], $userId);
        }

        $resumenAgregado = self::agregarResumenes(array_map(
            static fn (array $r): array => is_array($r['resumen'] ?? null) ? $r['resumen'] : [],
            $resultados
        ));

        return [
            'resultados' => $resultados,
            'resumen_agregado' => $resumenAgregado,
            'estimacion_agregada' => AICostEstimationService::estimarDesdeResumen($resumenAgregado),
        ];
    }

    /**
     * @param list<array<string, mixed>> $resumenes
     * @return array<string, mixed>
     */
    private static function agregarResumenes(array $resumenes): array
    {
        $agregado = [
            'evitada_por_cache' => 0,
            'evitada_por_dedup' => 0,
            'evitada_por_cpu' => 0,
            'evitada_por_validacion' => 0,
            'llamada_simulada' => 0,
            'llamada_real' => 0,
            'tokens' => [
                'prompt_token_count' => 0,
                'cached_content_token_count' => 0,
                'cached_content_token_count_simulado' => 0,
                'candidates_token_count' => 0,
                'thoughts_token_count' => 0,
            ],
            'por_contexto' => [],
            'tracking_habilitado' => true,
        ];

        foreach ($resumenes as $resumen) {
            $agregado['evitada_por_cache'] += (int) ($resumen['evitada_por_cache'] ?? 0);
            $agregado['evitada_por_dedup'] += (int) ($resumen['evitada_por_dedup'] ?? 0);
            $agregado['evitada_por_cpu'] += (int) ($resumen['evitada_por_cpu'] ?? 0);
            $agregado['evitada_por_validacion'] += (int) ($resumen['evitada_por_validacion'] ?? 0);
            $agregado['llamada_simulada'] += (int) ($resumen['llamada_simulada'] ?? 0);
            $agregado['llamada_real'] += (int) ($resumen['llamada_real'] ?? 0);

            $tokens = is_array($resumen['tokens'] ?? null) ? $resumen['tokens'] : [];
            foreach (array_keys($agregado['tokens']) as $key) {
                $agregado['tokens'][$key] += (int) ($tokens[$key] ?? 0);
            }

            $porCtx = is_array($resumen['por_contexto'] ?? null) ? $resumen['por_contexto'] : [];
            foreach ($porCtx as $ctx => $stats) {
                if (!is_array($stats)) {
                    continue;
                }
                if (!isset($agregado['por_contexto'][$ctx])) {
                    $agregado['por_contexto'][$ctx] = [
                        'llamadas' => 0,
                        'prompt_tokens' => 0,
                        'cached_tokens' => 0,
                        'candidates_tokens' => 0,
                    ];
                }
                $agregado['por_contexto'][$ctx]['llamadas'] += (int) ($stats['llamadas'] ?? 0);
                $agregado['por_contexto'][$ctx]['prompt_tokens'] += (int) ($stats['prompt_tokens'] ?? 0);
                $agregado['por_contexto'][$ctx]['cached_tokens'] += (int) ($stats['cached_tokens'] ?? 0);
                $agregado['por_contexto'][$ctx]['candidates_tokens'] += (int) ($stats['candidates_tokens'] ?? 0);
            }
        }

        $prompt = (int) $agregado['tokens']['prompt_token_count'];
        $cached = (int) $agregado['tokens']['cached_content_token_count'];
        $agregado['total_evitadas'] = $agregado['evitada_por_cache']
            + $agregado['evitada_por_dedup']
            + $agregado['evitada_por_cpu']
            + $agregado['evitada_por_validacion'];
        $agregado['tokens']['billable_input_token_count'] = max(0, $prompt - $cached);
        $agregado['tokens']['ratio_input_en_cache'] = $prompt > 0 ? round($cached / $prompt, 4) : 0.0;

        return $agregado;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cargarConversacion(string $rutaRelativa): array
    {
        self::validarRutaRelativa($rutaRelativa);
        $path = Yii::getAlias(self::CONVERSACIONES_DIR) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa) . '.json';

        if (!is_file($path)) {
            throw new \InvalidArgumentException('Conversación no encontrada: ' . $rutaRelativa);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('No se pudo leer la conversación: ' . $rutaRelativa);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('JSON inválido en conversación: ' . $rutaRelativa);
        }

        return $data;
    }

    private static function validarRutaRelativa(string $ruta): void
    {
        $ruta = trim($ruta);
        if ($ruta === '' || !preg_match('#^[a-z0-9_]+/[a-z0-9_]+$#', $ruta)) {
            throw new \InvalidArgumentException('Ruta de conversación inválida.');
        }
    }

    private static function rutaRelativaDesdeBase(string $base, string $rutaAbs): string
    {
        $rel = substr($rutaAbs, strlen(rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR));
        $rel = preg_replace('/\.json$/i', '', $rel) ?? $rel;

        return str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    }
}

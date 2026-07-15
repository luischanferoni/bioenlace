<?php

namespace common\components\Domain\Clinical\Presentation;

/**
 * Construye el bloque declarativo `capture_review` para clientes móvil / JSON
 * a partir del resultado de análisis IA y la configuración de categorías.
 */
final class EncounterCaptureReviewPresenter
{
    /**
     * @param array<string, mixed> $datosResultado Respuesta IA (`datosExtraidos`, etc.) o mapa plano de extraídos
     * @param list<array<string, mixed>> $categorias Configuración de categorías del encounter
     */
    public function build(
        array $datosResultado,
        array $categorias,
        string $textoOriginal,
        ?string $textoProcesado,
        bool $tieneDatosFaltantes
    ): array {
        $extraidos = $this->resolveExtraidos($datosResultado);
        $systemError = $this->resolveSystemError($extraidos);
        $categories = $this->buildCategories($extraidos, $categorias, $textoOriginal);
        // Todo lo extraído se tilda por defecto. `source` (clinical|ai) es solo señal UI:
        // filtrar por clinical hacía que Medicación/Indicaciones no entraran al guardar
        // (el encounter quedaba con diagnósticos vía codificación automática y sin note/meds).
        $defaultStaged = [];
        foreach ($categories as $category) {
            foreach ($category['items'] as $item) {
                $defaultStaged[] = $item['id'];
            }
        }

        $puedeConfirmar = $systemError === null
            && trim($textoOriginal) !== ''
            && !($tieneDatosFaltantes && $defaultStaged === []);

        return [
            'version' => 1,
            'texto_original' => $textoOriginal,
            'texto_procesado' => $textoProcesado,
            'tiene_datos_faltantes' => $tieneDatosFaltantes,
            'system_error' => $systemError,
            'puede_confirmar' => $puedeConfirmar,
            'categories' => $categories,
            'default_staged_item_ids' => $defaultStaged,
        ];
    }

    /**
     * @param array<string, mixed> $extraidos
     * @return array{texto: string, detalle: string, tipo: string}|null
     */
    public static function blockingErrorFromExtraidos(array $extraidos): ?array
    {
        $err = $extraidos['Error'] ?? null;
        if (!is_array($err)) {
            return null;
        }

        $tipo = (string) ($err['tipo'] ?? '');
        if (!in_array($tipo, ['error_sistema', 'error_ia', 'error_configuracion'], true)) {
            return null;
        }

        return [
            'texto' => (string) ($err['texto'] ?? ''),
            'detalle' => (string) ($err['detalle'] ?? ''),
            'tipo' => $tipo,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function resolveExtraidos(array $datos): array
    {
        if (isset($datos['datosExtraidos']) && is_array($datos['datosExtraidos'])) {
            return $datos['datosExtraidos'];
        }

        return $datos;
    }

    /**
     * @param array<string, mixed> $extraidos
     * @return array{texto: string, detalle: string, tipo: string}|null
     */
    private function resolveSystemError(array $extraidos): ?array
    {
        $err = $extraidos['Error'] ?? null;
        if (!is_array($err)) {
            return null;
        }

        $tipo = (string) ($err['tipo'] ?? '');
        if ($tipo !== 'error_sistema' && $tipo !== 'error_ia' && $tipo !== 'error_configuracion') {
            return null;
        }

        return [
            'texto' => (string) ($err['texto'] ?? ''),
            'detalle' => (string) ($err['detalle'] ?? ''),
            'tipo' => $tipo,
        ];
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     * @return list<array<string, mixed>>
     */
    private function buildCategories(array $extraidos, array $categorias, string $textoClinico = ''): array
    {
        $out = [];
        $haystack = $this->foldClinicalText($textoClinico);

        if ($categorias !== []) {
            foreach ($categorias as $categoria) {
                if (!is_array($categoria)) {
                    continue;
                }
                $title = trim((string) ($categoria['titulo'] ?? ''));
                if ($title === '' || $title === 'Error') {
                    continue;
                }
                $campos = $categoria['campos_requeridos'] ?? [];
                $out[] = [
                    'key' => $this->categoryKey($title),
                    'title' => $title,
                    'model' => (string) ($categoria['modelo'] ?? ''),
                    'required' => ($categoria['requerido'] ?? false) === true,
                    'items' => $this->parseCategoryItems(
                        $title,
                        $this->resolveCategoryRaw($extraidos, $title, (string) ($categoria['modelo'] ?? '')),
                        is_array($campos) ? $campos : [],
                        $haystack
                    ),
                ];
            }

            return $out;
        }

        foreach ($extraidos as $key => $raw) {
            if ($key === 'Error' || !is_string($key) || $key === '') {
                continue;
            }
            $items = $this->parseCategoryItems($key, $raw, [], $haystack);
            if ($items === []) {
                continue;
            }
            $out[] = [
                'key' => $this->categoryKey($key),
                'title' => $key,
                'required' => false,
                'items' => $items,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $extraidos
     */
    private function resolveCategoryRaw(array $extraidos, string $title, string $modelo): mixed
    {
        foreach ([$title, $modelo] as $key) {
            if ($key !== '' && array_key_exists($key, $extraidos)) {
                return $extraidos[$key];
            }
        }

        $want = [];
        foreach ([$title, $modelo] as $key) {
            if ($key === '') {
                continue;
            }
            $want[$this->normalizeExtractionKey($key)] = true;
        }
        foreach ($extraidos as $k => $value) {
            if (is_string($k) && isset($want[$this->normalizeExtractionKey($k)])) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeExtractionKey(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }

    /**
     * @param list<string> $camposRequeridos
     * @return list<array<string, mixed>>
     */
    private function parseCategoryItems(
        string $categoryTitle,
        mixed $raw,
        array $camposRequeridos = [],
        string $clinicalHaystack = ''
    ): array {
        if ($raw === null) {
            return [];
        }

        if (is_string($raw) && trim($raw) !== '') {
            return [
                $this->makeItem($categoryTitle, 0, trim($raw), ['texto' => trim($raw)], null, $clinicalHaystack),
            ];
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $index = 0;
        foreach ($raw as $row) {
            if (is_string($row) && trim($row) !== '') {
                $out[] = $this->makeItem(
                    $categoryTitle,
                    $index,
                    trim($row),
                    ['texto' => trim($row)],
                    null,
                    $clinicalHaystack
                );
                $index++;
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $label = $this->labelFromMap($row, $camposRequeridos);
            if ($label === '') {
                continue;
            }
            $out[] = $this->makeItem(
                $categoryTitle,
                $index,
                $label,
                $row,
                $this->subtitleFromMap($row, $camposRequeridos, $label),
                $clinicalHaystack
            );
            $index++;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function makeItem(
        string $categoryTitle,
        int $index,
        string $label,
        array $payload,
        ?string $subtitle = null,
        string $clinicalHaystack = ''
    ): array {
        $item = [
            'id' => $categoryTitle . '::' . $index,
            'label' => $label,
            'payload' => $payload,
            'source' => $this->resolveItemSource($label, $payload, $clinicalHaystack),
        ];
        if ($subtitle !== null && $subtitle !== '') {
            $item['subtitle'] = $subtitle;
        }

        return $item;
    }

    /**
     * clinical = anclado en el texto del profesional; ai = aporte / enriquecimiento de la IA.
     *
     * @param array<string, mixed> $payload
     */
    private function resolveItemSource(string $label, array $payload, string $clinicalHaystack): string
    {
        if ($clinicalHaystack === '') {
            return 'clinical';
        }
        $haystack = $this->expandClinicalAbbreviations($clinicalHaystack);
        $candidates = [$label];
        foreach ($payload as $value) {
            if (is_string($value) && trim($value) !== '') {
                $candidates[] = trim($value);
            } elseif (is_numeric($value)) {
                $candidates[] = (string) $value;
            }
        }
        foreach ($candidates as $candidate) {
            $folded = $this->foldClinicalText($candidate);
            if ($folded === '') {
                continue;
            }
            if (mb_strlen($folded) >= 4 && mb_strpos($haystack, $folded) !== false) {
                return 'clinical';
            }
            // Cifras / signos vitales cortos (p. ej. 138/88).
            if (preg_match('/\d/', $folded) === 1 && mb_strpos($haystack, $folded) !== false) {
                return 'clinical';
            }
            $tokens = preg_split('/\s+/u', $folded) ?: [];
            $hits = 0;
            $significant = 0;
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 4 && !preg_match('/\d/', $token)) {
                    continue;
                }
                $significant++;
                if (mb_strpos($haystack, $token) !== false) {
                    $hits++;
                }
            }
            if ($significant > 0 && $hits >= max(1, (int) ceil($significant * 0.5))) {
                return 'clinical';
            }
        }

        return 'ai';
    }

    /** Expande abreviaturas clínicas frecuentes para anclar ítems al texto del profesional. */
    private function expandClinicalAbbreviations(string $foldedHaystack): string
    {
        $out = $foldedHaystack;
        $map = [
            '/\bta\b/u' => 'ta tension arterial',
            '/\bfc\b/u' => 'fc frecuencia cardiaca',
            '/\bfr\b/u' => 'fr frecuencia respiratoria',
            '/\btemp\b/u' => 'temp temperatura',
            '/\bsat\b/u' => 'sat saturacion',
        ];
        foreach ($map as $pattern => $replacement) {
            $out = preg_replace($pattern, $replacement, $out) ?? $out;
        }

        return $out;
    }

    private function foldClinicalText(string $text): string
    {
        $folded = strtr(mb_strtolower(trim($text), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/u', ' ', $folded) ?? $folded;
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $camposRequeridos
     */
    private function labelFromMap(array $map, array $camposRequeridos = []): string
    {
        foreach ($camposRequeridos as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $value = trim((string) ($map[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['termino', 'descripcion', 'texto', 'nombre', 'display', 'medicamento', 'label'] as $key) {
            $value = trim((string) ($map[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $parts = [];
        foreach ($map as $key => $value) {
            if ($value === null) {
                continue;
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $parts[] = $key . ': ' . $text;
            if (count($parts) >= 3) {
                break;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $camposRequeridos
     */
    private function subtitleFromMap(array $map, array $camposRequeridos = [], string $label = ''): ?string
    {
        foreach (['codigo', 'codigo_cie10', 'cie10', 'conceptId'] as $key) {
            $value = trim((string) ($map[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $parts = [];
        $remaining = array_slice($camposRequeridos, 1);
        if ($remaining === []) {
            foreach ($map as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $text = trim((string) $value);
                if ($text === '' || $text === $label) {
                    continue;
                }
                $parts[] = $text;
                if (count($parts) >= 4) {
                    break;
                }
            }
        } else {
            foreach ($remaining as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $value = trim((string) ($map[$key] ?? ''));
                if ($value === '' || $value === $label) {
                    continue;
                }
                $parts[] = $value;
                if (count($parts) >= 4) {
                    break;
                }
            }
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function categoryKey(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug) ?? $slug;
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'categoria';
    }
}

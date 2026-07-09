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
        $categories = $this->buildCategories($extraidos, $categorias);
        $defaultStaged = [];

        foreach ($categories as $category) {
            foreach ($category['items'] as $item) {
                $defaultStaged[] = $item['id'];
            }
        }

        return [
            'version' => 1,
            'texto_original' => $textoOriginal,
            'texto_procesado' => $textoProcesado,
            'tiene_datos_faltantes' => $tieneDatosFaltantes,
            'system_error' => $systemError,
            'categories' => $categories,
            'default_staged_item_ids' => $defaultStaged,
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
    private function buildCategories(array $extraidos, array $categorias): array
    {
        $out = [];

        if ($categorias !== []) {
            foreach ($categorias as $categoria) {
                if (!is_array($categoria)) {
                    continue;
                }
                $title = trim((string) ($categoria['titulo'] ?? ''));
                if ($title === '' || $title === 'Error') {
                    continue;
                }
                $out[] = [
                    'key' => $this->categoryKey($title),
                    'title' => $title,
                    'required' => ($categoria['requerido'] ?? false) === true,
                    'items' => $this->parseCategoryItems($title, $extraidos[$title] ?? null),
                ];
            }

            return $out;
        }

        foreach ($extraidos as $key => $raw) {
            if ($key === 'Error' || !is_string($key) || $key === '') {
                continue;
            }
            $items = $this->parseCategoryItems($key, $raw);
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
     * @return list<array<string, mixed>>
     */
    private function parseCategoryItems(string $categoryTitle, mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_string($raw) && trim($raw) !== '') {
            return [
                $this->makeItem($categoryTitle, 0, trim($raw), ['texto' => trim($raw)]),
            ];
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $index = 0;
        foreach ($raw as $row) {
            if (is_string($row) && trim($row) !== '') {
                $out[] = $this->makeItem($categoryTitle, $index, trim($row), ['texto' => trim($row)]);
                $index++;
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $label = $this->labelFromMap($row);
            if ($label === '') {
                continue;
            }
            $out[] = $this->makeItem(
                $categoryTitle,
                $index,
                $label,
                $row,
                $this->subtitleFromMap($row)
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
        ?string $subtitle = null
    ): array {
        $item = [
            'id' => $categoryTitle . '::' . $index,
            'label' => $label,
            'payload' => $payload,
        ];
        if ($subtitle !== null && $subtitle !== '') {
            $item['subtitle'] = $subtitle;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $map
     */
    private function labelFromMap(array $map): string
    {
        foreach (['termino', 'descripcion', 'texto', 'nombre', 'display'] as $key) {
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
     */
    private function subtitleFromMap(array $map): ?string
    {
        foreach (['codigo', 'codigo_cie10', 'cie10', 'conceptId'] as $key) {
            $value = trim((string) ($map[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function categoryKey(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug) ?? $slug;
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'categoria';
    }
}

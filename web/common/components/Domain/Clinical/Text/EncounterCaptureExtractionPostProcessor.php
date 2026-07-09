<?php

namespace common\components\Domain\Clinical\Text;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

/**
 * Ajusta la clasificación IA de captura clínica según reglas declarativas en metadata.
 */
final class EncounterCaptureExtractionPostProcessor
{
    private EncounterCaptureClinicalTermValidator $termValidator;

    public function __construct(?EncounterCaptureClinicalTermValidator $termValidator = null)
    {
        $this->termValidator = $termValidator ?? new EncounterCaptureClinicalTermValidator();
    }

    /**
     * @param array<string, mixed> $resultadoIA Respuesta normalizada con clave datosExtraidos
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    public function apply(array $resultadoIA, array $categorias, string $clinicalText): array
    {
        $extraidos = $resultadoIA['datosExtraidos'] ?? null;
        if (!is_array($extraidos)) {
            return $resultadoIA;
        }

        $resultadoIA['datosExtraidos'] = $this->filterNonClinicalExtractions(
            $extraidos,
            $categorias,
            $clinicalText
        );

        $relocateConfig = ClinicalTextIaMetadata::encounterCaptureRelocateConfig();
        if (($relocateConfig['enabled'] ?? false) !== true) {
            return $resultadoIA;
        }

        return $this->relocateIsolatedDiagnosisTerms($resultadoIA, $categorias, $clinicalText, $relocateConfig);
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    private function filterNonClinicalExtractions(array $extraidos, array $categorias, string $clinicalText): array
    {
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();
        if (($config['enabled'] ?? false) !== true) {
            return $extraidos;
        }

        $models = $config['category_models'] ?? ['ConsultaMotivos', 'DiagnosticoConsulta'];
        if (!is_array($models)) {
            $models = ['ConsultaMotivos', 'DiagnosticoConsulta'];
        }

        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $modelo = (string) ($categoria['modelo'] ?? '');
            if ($modelo === '' || !in_array($modelo, $models, true)) {
                continue;
            }
            $titulo = trim((string) ($categoria['titulo'] ?? ''));
            if ($titulo === '' || !isset($extraidos[$titulo]) || !is_array($extraidos[$titulo])) {
                continue;
            }

            $filtered = [];
            foreach ($extraidos[$titulo] as $item) {
                if ($this->termValidator->isPlausibleExtraction($item, $clinicalText, $config)) {
                    $filtered[] = $item;
                }
            }
            $extraidos[$titulo] = $filtered;
        }

        return $extraidos;
    }

    /**
     * @param array<string, mixed> $resultadoIA
     * @param list<array<string, mixed>> $categorias
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function relocateIsolatedDiagnosisTerms(
        array $resultadoIA,
        array $categorias,
        string $clinicalText,
        array $config
    ): array {
        $extraidos = $resultadoIA['datosExtraidos'] ?? null;
        if (!is_array($extraidos)) {
            return $resultadoIA;
        }

        $motivoModel = (string) ($config['motivo_model'] ?? 'ConsultaMotivos');
        $diagnosisModels = $config['diagnosis_models'] ?? ['DiagnosticoConsulta'];
        if (!is_array($diagnosisModels)) {
            $diagnosisModels = ['DiagnosticoConsulta'];
        }

        $motivoTitulo = $this->resolveTitulo($categorias, $motivoModel);
        $diagnosisTitulo = $this->resolveFirstTitulo($categorias, $diagnosisModels);
        if ($motivoTitulo === null || $diagnosisTitulo === null) {
            return $resultadoIA;
        }

        $motivoItems = $this->normalizeItems($extraidos[$motivoTitulo] ?? null);
        if ($motivoItems === []) {
            return $resultadoIA;
        }

        $diagnosisItems = $this->normalizeItems($extraidos[$diagnosisTitulo] ?? null);
        if ($diagnosisItems !== []) {
            return $resultadoIA;
        }

        if (!$this->shouldRelocateIsolatedTerm($clinicalText, $motivoItems, $config)) {
            return $resultadoIA;
        }

        $resultadoIA['datosExtraidos'][$diagnosisTitulo] = $motivoItems;
        $resultadoIA['datosExtraidos'][$motivoTitulo] = [];

        return $resultadoIA;
    }

    /**
     * @param list<string|array<string, mixed>> $motivoItems
     * @param array<string, mixed> $config
     */
    private function shouldRelocateIsolatedTerm(string $clinicalText, array $motivoItems, array $config): bool
    {
        if (count($motivoItems) !== 1) {
            return false;
        }

        $item = $motivoItems[0];
        $label = $this->itemLabel($item);
        if ($label === '' || $this->normalizeText($clinicalText) !== $this->normalizeText($label)) {
            return false;
        }

        $maxWords = (int) ($config['max_words'] ?? 5);
        if ($maxWords > 0 && $this->wordCount($clinicalText) > $maxWords) {
            return false;
        }

        $filterConfig = ClinicalTextIaMetadata::encounterCaptureFilterConfig();

        return $this->termValidator->isPlausibleIsolatedDiagnosisCandidate($item, $clinicalText, $filterConfig);
    }

    private function normalizeText(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return '';
        }

        return (string) preg_replace('/\s+/u', ' ', $normalized);
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? count($words) : 0;
    }

    /**
     * @param list<array<string, mixed>> $categorias
     */
    private function resolveTitulo(array $categorias, string $modelo): ?string
    {
        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            if (($categoria['modelo'] ?? '') === $modelo) {
                $titulo = trim((string) ($categoria['titulo'] ?? ''));
                if ($titulo !== '') {
                    return $titulo;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $categorias
     * @param list<string> $modelos
     */
    private function resolveFirstTitulo(array $categorias, array $modelos): ?string
    {
        foreach ($modelos as $modelo) {
            $titulo = $this->resolveTitulo($categorias, (string) $modelo);
            if ($titulo !== null) {
                return $titulo;
            }
        }

        return null;
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function normalizeItems(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_string($raw) && trim($raw) !== '') {
            return [trim($raw)];
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (is_string($row) && trim($row) !== '') {
                $out[] = trim($row);
                continue;
            }
            if (is_array($row) && $row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param string|array<string, mixed> $item
     */
    private function itemLabel(mixed $item): string
    {
        if (is_string($item)) {
            return trim($item);
        }
        if (!is_array($item)) {
            return '';
        }
        foreach (['termino', 'descripcion', 'texto', 'nombre'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

<?php

namespace common\components\Domain\Clinical\Text;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

/**
 * Ajusta la clasificación IA de captura clínica según reglas declarativas en metadata.
 *
 * Reubicación motivo → diagnóstico: solo término aislado estructuralmente equivalente al texto,
 * sin framing narrativo ni léxico de queja subjetiva ({@see ClinicalTextIaMetadata::clinicalLexiconPattern}).
 */
final class EncounterCaptureExtractionPostProcessor
{
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

        $config = ClinicalTextIaMetadata::encounterCaptureRelocateConfig();
        if (($config['enabled'] ?? false) !== true) {
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
        if (!$this->clinicalTextMatchesSingleMotivoItem($clinicalText, $motivoItems)) {
            return false;
        }

        $maxWords = (int) ($config['max_words'] ?? 5);
        if ($maxWords > 0 && $this->wordCount($clinicalText) > $maxWords) {
            return false;
        }

        $retainKeys = $config['retain_if_lexicon_keys'] ?? [];
        if (!is_array($retainKeys)) {
            $retainKeys = [];
        }

        foreach ($retainKeys as $key) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            if (ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($clinicalText, trim($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string|array<string, mixed>> $motivoItems
     */
    private function clinicalTextMatchesSingleMotivoItem(string $clinicalText, array $motivoItems): bool
    {
        if (count($motivoItems) !== 1) {
            return false;
        }

        $label = $this->itemLabel($motivoItems[0]);
        if ($label === '') {
            return false;
        }

        return $this->normalizeText($clinicalText) === $this->normalizeText($label);
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
        $normalized = trim($text);
        if ($normalized === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

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

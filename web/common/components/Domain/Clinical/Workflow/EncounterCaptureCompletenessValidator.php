<?php

namespace common\components\Domain\Clinical\Workflow;

/**
 * Completitud de captura clínica vs categorías del EncounterDefinition
 * (`requerido` + contrato de dominio por modelo / `campos_requeridos` legacy).
 *
 * Si el modelo declara `completenessForExtractedRow`, se usa ese contrato;
 * si no, se aplica la lista plana `campos_requeridos`.
 */
final class EncounterCaptureCompletenessValidator
{
    /**
     * @param array<string, mixed> $extraidos mapa categoría → filas (datosExtraidos)
     * @param list<array<string, mixed>> $categorias de {@see EncounterDefinition::getCategoriasParaPrompt}
     * @return array{
     *   complete: bool,
     *   tiene_datos_faltantes: bool,
     *   missing_categories: list<string>,
     *   incomplete_items: list<array{category: string, index: int, label: string, missing_fields: list<string>}>,
     *   issues: list<array{id: string, field: string, message: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>,
     *   message: string
     * }
     */
    public function validate(array $extraidos, array $categorias): array
    {
        $missingCategories = [];
        $incompleteItems = [];
        $issues = [];

        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $title = trim((string) ($categoria['titulo'] ?? ''));
            if ($title === '' || $title === 'Error') {
                continue;
            }
            $required = ($categoria['requerido'] ?? false) === true;
            $modelo = (string) ($categoria['modelo'] ?? '');
            $campos = $this->normalizeCampos($categoria['campos_requeridos'] ?? []);
            $raw = $this->resolveCategoryRaw($extraidos, $title, $modelo);
            $rows = $this->normalizeRows($raw);

            if ($rows === []) {
                if ($required) {
                    $missingCategories[] = $title;
                }
                continue;
            }

            if ($this->usesDomainRowContract($modelo)) {
                foreach ($rows as $index => $row) {
                    $check = $this->domainCompletenessForRow($modelo, $row);
                    if ($check['missing_fields'] === []) {
                        continue;
                    }
                    $incompleteItems[] = [
                        'category' => $title,
                        'index' => (int) $index,
                        'label' => $check['label'],
                        'missing_fields' => $check['missing_fields'],
                    ];
                    $input = $check['input'] ?? null;
                    if (is_object($input) && method_exists($input, 'buildIssues')) {
                        foreach ($input->buildIssues($title, (int) $index) as $issue) {
                            if (is_array($issue)) {
                                $issues[] = $issue;
                            }
                        }
                    } else {
                        foreach ($check['missing_fields'] as $field) {
                            $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                                $title,
                                (int) $index,
                                (string) $field,
                                'Completá «' . $field . '».',
                                [],
                                true
                            );
                        }
                    }
                }
                continue;
            }

            if ($campos === []) {
                continue;
            }

            foreach ($rows as $index => $row) {
                $missingFields = $this->missingFieldsForRow($row, $campos);
                if ($missingFields === []) {
                    continue;
                }
                $incompleteItems[] = [
                    'category' => $title,
                    'index' => (int) $index,
                    'label' => $this->rowLabel($row, $campos),
                    'missing_fields' => $missingFields,
                ];
                foreach ($missingFields as $field) {
                    $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                        $title,
                        (int) $index,
                        (string) $field,
                        'Completá «' . $field . '».',
                        [],
                        true
                    );
                }
            }
        }

        $complete = $missingCategories === [] && $incompleteItems === [];

        return [
            'complete' => $complete,
            'tiene_datos_faltantes' => !$complete,
            'missing_categories' => $missingCategories,
            'incomplete_items' => $incompleteItems,
            'issues' => $issues,
            'message' => $this->buildMessage($missingCategories, $incompleteItems),
        ];
    }

    private function usesDomainRowContract(string $modelo): bool
    {
        return $this->domainCompletenessHandler($modelo) !== null;
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input?: object}
     */
    private function domainCompletenessForRow(string $modelo, $row): array
    {
        $handler = $this->domainCompletenessHandler($modelo);
        if ($handler === null) {
            return ['missing_fields' => [], 'label' => 'ítem'];
        }
        $check = $handler($row);

        return [
            'missing_fields' => $check['missing_fields'] ?? [],
            'label' => $check['label'] ?? 'ítem',
            'input' => $check['input'] ?? null,
        ];
    }

    /**
     * @return (callable(array<string, mixed>|string): array{missing_fields: list<string>, label: string, input?: object})|null
     */
    private function domainCompletenessHandler(string $modelo): ?callable
    {
        $class = $this->resolveModeloClass($modelo);
        if ($class === null || !method_exists($class, 'completenessForExtractedRow')) {
            return null;
        }

        return [$class, 'completenessForExtractedRow'];
    }

    private function resolveModeloClass(string $modelo): ?string
    {
        $modelo = trim($modelo);
        if ($modelo === '') {
            return null;
        }
        if (str_contains($modelo, '\\')) {
            return class_exists($modelo) ? $modelo : null;
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $modelo)) {
            return null;
        }
        $class = '\\common\\models\\' . $modelo;

        return class_exists($class) ? $class : null;
    }

    /**
     * @param mixed $campos
     * @return list<string>
     */
    private function normalizeCampos($campos): array
    {
        if (!is_array($campos)) {
            return [];
        }
        $out = [];
        foreach ($campos as $campo) {
            $s = trim((string) $campo);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $extraidos
     * @return mixed
     */
    private function resolveCategoryRaw(array $extraidos, string $title, string $modelo)
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
            $want[$this->normalizeKey($key)] = true;
        }
        foreach ($extraidos as $k => $value) {
            if (is_string($k) && isset($want[$this->normalizeKey($k)])) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param mixed $raw
     * @return list<array<string, mixed>|string>
     */
    private function normalizeRows($raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_string($raw)) {
            return trim($raw) !== '' ? [trim($raw)] : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        if ($raw === []) {
            return [];
        }
        // Mapa asociativo único (IA a veces no envuelve en []).
        if ($this->isAssocMap($raw)) {
            return [$raw];
        }
        $out = [];
        foreach ($raw as $row) {
            if (is_string($row) && trim($row) !== '') {
                $out[] = trim($row);
            } elseif (is_array($row) && $row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isAssocMap(array $row): bool
    {
        if ($row === []) {
            return false;
        }
        $i = 0;
        foreach (array_keys($row) as $k) {
            if ($k !== $i) {
                return true;
            }
            $i++;
        }

        return false;
    }

    /**
     * @param array<string, mixed>|string $row
     * @param list<string> $campos
     * @return list<string>
     */
    private function missingFieldsForRow($row, array $campos): array
    {
        if (is_string($row)) {
            // String suelto solo cubre el primer campo de etiqueta; el resto falta.
            if (count($campos) <= 1) {
                return [];
            }
            return array_slice($campos, 1);
        }
        if (!is_array($row)) {
            return $campos;
        }
        $missing = [];
        foreach ($campos as $campo) {
            if ($this->fieldValue($row, $campo) === '') {
                $missing[] = $campo;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fieldValue(array $row, string $campo): string
    {
        if (array_key_exists($campo, $row)) {
            return trim((string) $row[$campo]);
        }
        $want = $this->normalizeKey($campo);
        foreach ($row as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if ($this->normalizeKey($k) === $want) {
                return trim((string) $v);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed>|string $row
     * @param list<string> $campos
     */
    private function rowLabel($row, array $campos): string
    {
        if (is_string($row)) {
            return $row;
        }
        foreach ($campos as $campo) {
            $v = $this->fieldValue($row, $campo);
            if ($v !== '') {
                return $v;
            }
        }
        foreach (['termino', 'texto', 'nombre', 'display', 'label', 'medicamento', 'Practica', 'Indicacion'] as $k) {
            $v = $this->fieldValue($row, $k);
            if ($v !== '') {
                return $v;
            }
        }

        return 'ítem';
    }

    private function normalizeKey(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }

    /**
     * @param list<string> $missingCategories
     * @param list<array{category: string, index: int, label: string, missing_fields: list<string>}> $incompleteItems
     */
    private function buildMessage(array $missingCategories, array $incompleteItems): string
    {
        if ($missingCategories === [] && $incompleteItems === []) {
            return '';
        }
        $parts = [];
        if ($missingCategories !== []) {
            $parts[] = 'Faltan categorías obligatorias: ' . implode(', ', $missingCategories) . '.';
        }
        foreach ($incompleteItems as $item) {
            $fields = implode(', ', $item['missing_fields']);
            $label = trim((string) ($item['label'] ?? ''));
            $cat = (string) ($item['category'] ?? '');
            if ($label !== '') {
                $parts[] = "En {$cat} («{$label}») faltan: {$fields}.";
            } else {
                $parts[] = "En {$cat} faltan: {$fields}.";
            }
        }

        return implode(' ', $parts);
    }
}

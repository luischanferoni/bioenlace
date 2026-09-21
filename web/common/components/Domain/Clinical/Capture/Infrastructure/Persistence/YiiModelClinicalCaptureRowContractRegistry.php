<?php

namespace common\components\Domain\Clinical\Capture\Infrastructure\Persistence;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRowContractRegistry;

/**
 * Adapter: tipologías en `common/models/Clinical` + contratos `*Input`.
 *
 * Deuda: la semántica sigue en Input/Yii Model; el Domain ya no las resuelve.
 */
final class YiiModelClinicalCaptureRowContractRegistry implements ClinicalCaptureRowContractRegistry
{
    public function supports(string $modelo): bool
    {
        $class = $this->resolveModeloClass($modelo);

        return $class !== null && method_exists($class, 'completenessForExtractedRow');
    }

    public function assess(string $modelo, $row, string $categoryTitle, int $index): ?ClinicalCaptureRowCompleteness
    {
        $class = $this->resolveModeloClass($modelo);
        if ($class === null || !method_exists($class, 'completenessForExtractedRow')) {
            return null;
        }

        $check = $class::completenessForExtractedRow($row);
        $missing = is_array($check['missing_fields'] ?? null) ? $check['missing_fields'] : [];
        $label = trim((string) ($check['label'] ?? 'ítem'));
        $issues = [];
        $input = $check['input'] ?? null;
        if (is_object($input) && method_exists($input, 'buildIssues')) {
            foreach ($input->buildIssues($categoryTitle, $index) as $issue) {
                if (is_array($issue)) {
                    $issues[] = $issue;
                }
            }
        }

        return new ClinicalCaptureRowCompleteness($missing, $label !== '' ? $label : 'ítem', $issues);
    }

    public function applyResolution(string $modelo, array $row, string $field, mixed $value): ?array
    {
        $class = $this->resolveModeloClass($modelo);
        if ($class === null || !method_exists($class, 'applyResolutionToRow')) {
            return null;
        }

        $out = $class::applyResolutionToRow($row, $field, $value);

        return is_array($out) ? $out : $row;
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
        foreach (['\\common\\models\\Clinical\\', '\\common\\models\\'] as $prefix) {
            $class = $prefix . $modelo;
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}

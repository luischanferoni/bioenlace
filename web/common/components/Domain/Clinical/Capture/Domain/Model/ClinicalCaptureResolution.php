<?php

namespace common\components\Domain\Clinical\Capture\Domain\Model;

/**
 * Resolución tipada de un issue de captura (`Categoría::índice:campo` → valor).
 */
final class ClinicalCaptureResolution
{
    private string $category;

    private int $index;

    private string $field;

    /** @var mixed */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct(string $category, int $index, string $field, $value)
    {
        $this->category = trim($category);
        $this->index = $index;
        $this->field = trim($field);
        $this->value = $value;
    }

    /**
     * @param mixed $value
     */
    public static function tryFromIssueId(string $issueId, $value): ?self
    {
        $parsed = ClinicalCaptureIssueFactory::parseIssueId($issueId);
        if ($parsed === null) {
            return null;
        }

        return new self($parsed['category'], $parsed['index'], $parsed['field'], $value);
    }

    /**
     * @param array<string, mixed> $resolutions mapa issue_id → value
     * @return list<self>
     */
    public static function listFromMap(array $resolutions): array
    {
        $out = [];
        foreach ($resolutions as $issueId => $value) {
            if (!is_string($issueId) || $issueId === '') {
                continue;
            }
            $one = self::tryFromIssueId($issueId, $value);
            if ($one !== null) {
                $out[] = $one;
            }
        }

        return $out;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function index(): int
    {
        return $this->index;
    }

    public function field(): string
    {
        return $this->field;
    }

    /**
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }

    public function issueId(): string
    {
        return ClinicalCaptureIssueFactory::issueId($this->category, $this->index, $this->field);
    }
}

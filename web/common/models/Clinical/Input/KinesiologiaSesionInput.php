<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\KinesiologiaSesionRowContract;
use yii\base\Model;

/**
 * Sesión de kinesiología en captura.
 * Completitud: {@see KinesiologiaSesionRowContract}.
 */
final class KinesiologiaSesionInput extends Model
{
    /** @var array<string, string|null> valores por clave del prompt */
    public array $values = [];

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return KinesiologiaSesionRowContract::PROMPT_FIELDS;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->values = is_array($row) ? KinesiologiaSesionRowContract::normalize($row) : [];

        return $model;
    }

    public function rules(): array
    {
        return [
            [['values'], 'safe'],
            [['values'], 'validateScales'],
        ];
    }

    public function validateScales(): void
    {
        $ranges = [
            KinesiologiaSesionRowContract::FIELD_FUERZA => [
                KinesiologiaSesionRowContract::STRENGTH_MIN,
                KinesiologiaSesionRowContract::STRENGTH_MAX,
            ],
            KinesiologiaSesionRowContract::FIELD_DOLOR => [
                KinesiologiaSesionRowContract::PAIN_MIN,
                KinesiologiaSesionRowContract::PAIN_MAX,
            ],
        ];
        foreach ($ranges as $field => [$min, $max]) {
            $value = trim((string) ($this->values[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
                $this->addError('values', $field . ' debe estar entre ' . $min . ' y ' . $max . '.');
            }
        }
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        return $this->values === [] ? self::promptFieldNames() : [];
    }

    public function rowLabel(): string
    {
        $parts = [];
        foreach (KinesiologiaSesionRowContract::PROMPT_FIELDS as $field) {
            $value = trim((string) ($this->values[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $field . ': ' . $value;
                if (count($parts) >= 2) {
                    break;
                }
            }
        }

        return $parts === [] ? 'sesión de kinesiología' : implode(' · ', $parts);
    }
}

<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\ObstetriciaControlRowContract;
use yii\base\Model;

/**
 * Control prenatal en captura.
 * Completitud: {@see ObstetriciaControlRowContract}.
 */
final class ObstetriciaControlInput extends Model
{
    /** @var array<string, string|null> valores por clave del prompt */
    public array $values = [];

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return ObstetriciaControlRowContract::PROMPT_FIELDS;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->values = is_array($row) ? ObstetriciaControlRowContract::normalize($row) : [];

        return $model;
    }

    public function rules(): array
    {
        $rules = [['values', 'safe']];

        foreach (ObstetriciaControlRowContract::PROMPT_FIELDS as $field) {
            $storage = ObstetriciaControlRowContract::storageFor($field);
            if ($storage === null || $storage['type'] !== ObstetriciaControlRowContract::KIND_NUMERIC) {
                continue;
            }
            $rules[] = [
                'values',
                'validateNumericField',
                'params' => ['field' => $field],
                'skipOnEmpty' => true,
            ];
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function validateNumericField(string $attribute, $params): void
    {
        $field = (string) ($params['field'] ?? '');
        $value = trim((string) ($this->values[$field] ?? ''));
        if ($value === '' || is_numeric($value)) {
            return;
        }
        $this->addError($attribute, $field . ' debe ser un número.');
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
        foreach (ObstetriciaControlRowContract::PROMPT_FIELDS as $field) {
            $value = trim((string) ($this->values[$field] ?? ''));
            if ($value !== '') {
                return $field . ' ' . $value;
            }
        }

        return 'control prenatal';
    }
}

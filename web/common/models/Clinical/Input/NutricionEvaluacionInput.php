<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\NutricionEvaluacionRowContract;
use yii\base\Model;

/**
 * Evaluación nutricional en captura.
 * Completitud: {@see NutricionEvaluacionRowContract}.
 */
final class NutricionEvaluacionInput extends Model
{
    /** @var array<string, string|null> valores por clave del prompt */
    public array $values = [];

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return NutricionEvaluacionRowContract::PROMPT_FIELDS;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->values = is_array($row) ? NutricionEvaluacionRowContract::normalize($row) : [];

        return $model;
    }

    public function rules(): array
    {
        return [
            [['values'], 'safe'],
            [['values'], 'validateImcDerived'],
        ];
    }

    public function validateImcDerived(): void
    {
        $expected = NutricionEvaluacionRowContract::imc(
            $this->values[NutricionEvaluacionRowContract::FIELD_PESO] ?? null,
            $this->values[NutricionEvaluacionRowContract::FIELD_TALLA] ?? null
        );
        $actual = trim((string) ($this->values[NutricionEvaluacionRowContract::FIELD_IMC] ?? ''));
        if ($expected !== null && $actual !== '' && $actual !== $expected) {
            $this->addError('values', 'El IMC se deriva de peso y talla; no se acepta un valor distinto.');
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
        $imc = trim((string) ($this->values[NutricionEvaluacionRowContract::FIELD_IMC] ?? ''));
        if ($imc !== '') {
            return 'IMC ' . $imc;
        }

        return 'evaluación nutricional';
    }
}

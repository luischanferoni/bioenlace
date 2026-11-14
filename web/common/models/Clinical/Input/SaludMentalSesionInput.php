<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\SaludMentalSesionRowContract;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\MentalHealthInstrumentCatalog;
use yii\base\Model;

/**
 * Sesión de salud mental en captura.
 * Completitud: {@see SaludMentalSesionRowContract}.
 */
final class SaludMentalSesionInput extends Model
{
    /** @var array<string, string|null> valores por clave del prompt */
    public array $values = [];

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return SaludMentalSesionRowContract::promptFields();
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->values = is_array($row) ? SaludMentalSesionRowContract::normalize($row) : [];

        return $model;
    }

    public function rules(): array
    {
        return [
            [['values'], 'safe'],
            [['values'], 'validateInstrumentRanges'],
        ];
    }

    public function validateInstrumentRanges(): void
    {
        foreach (MentalHealthInstrumentCatalog::all() as $code => $instrument) {
            $value = trim((string) ($this->values[$instrument['label']] ?? ''));
            if ($value === '') {
                continue;
            }
            if (!is_numeric($value)) {
                $this->addError('values', $instrument['label'] . ' debe ser un número.');
                continue;
            }
            $score = (float) $value;
            if ($score < $instrument['min'] || $score > $instrument['max']) {
                $this->addError(
                    'values',
                    $instrument['label'] . ' debe estar entre '
                    . $instrument['min'] . ' y ' . $instrument['max'] . '.'
                );
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
        foreach (SaludMentalSesionRowContract::promptFields() as $field) {
            $value = trim((string) ($this->values[$field] ?? ''));
            if ($value !== '') {
                return $field . ': ' . $value;
            }
        }

        return 'sesión';
    }
}

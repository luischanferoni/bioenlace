<?php

namespace common\models\Clinical\Input;

use yii\base\Model;

/**
 * Régimen / dieta en captura IMP.
 * Integridad: texto de indicaciones; el concept_id puede codificarse después.
 */
final class RegimenInput extends Model
{
    public const FIELD_INDICACIONES = 'Indicaciones';

    /** @var string|null */
    public $indicaciones;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_INDICACIONES];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        if (is_string($row)) {
            $model->indicaciones = trim($row);

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        foreach ([self::FIELD_INDICACIONES, 'indicaciones', 'texto', 'display', 'termino'] as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = trim((string) $row[$key]);
            if ($v !== '') {
                $model->indicaciones = $v;
                break;
            }
        }

        return $model;
    }

    public function rules(): array
    {
        return [
            [['indicaciones'], 'trim'],
            [['indicaciones'], 'required', 'message' => 'Faltan las indicaciones de régimen.'],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        if ($this->validate()) {
            return [];
        }

        return $this->hasErrors('indicaciones') ? [self::FIELD_INDICACIONES] : [];
    }

    public function rowLabel(): string
    {
        $t = trim((string) ($this->indicaciones ?? ''));

        return $t !== '' ? mb_substr($t, 0, 80) : 'ítem';
    }
}

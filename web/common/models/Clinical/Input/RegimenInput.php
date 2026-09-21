<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\Policy\RegimenRowContract;
use yii\base\Model;

/**
 * Régimen / dieta en captura IMP.
 * Completitud: {@see RegimenRowContract}.
 */
final class RegimenInput extends Model
{
    public const FIELD_INDICACIONES = RegimenRowContract::FIELD_INDICACIONES;

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
        $model->indicaciones = RegimenRowContract::extractIndicaciones($row) ?: null;

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
        return trim((string) ($this->indicaciones ?? '')) === '' ? [self::FIELD_INDICACIONES] : [];
    }

    public function rowLabel(): string
    {
        $t = trim((string) ($this->indicaciones ?? ''));

        return $t !== '' ? mb_substr($t, 0, 80) : 'ítem';
    }
}

<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\PracticaRowContract;
use yii\base\Model;

/**
 * Contrato de entrada de una práctica realizada en la consulta.
 * Completitud: {@see PracticaRowContract}.
 */
final class PracticaInput extends Model
{
    public const FIELD_PRACTICA = PracticaRowContract::FIELD_PRACTICA;
    public const FIELD_RESULTADO = PracticaRowContract::FIELD_RESULTADO;
    public const FIELD_CODIGO = PracticaRowContract::FIELD_CODIGO;

    /** @var string|null */
    public $practica;

    /** @var string|null */
    public $resultado;

    /** @var string|null */
    public $codigo;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [
            self::FIELD_PRACTICA,
            self::FIELD_RESULTADO,
            self::FIELD_CODIGO,
        ];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->practica = PracticaRowContract::extractPractica($row) ?: null;
        $model->resultado = PracticaRowContract::extractResultado($row) ?: null;
        $model->codigo = PracticaRowContract::extractCodigo($row) ?: null;

        return $model;
    }

    public function rules(): array
    {
        return [
            [['practica'], 'trim'],
            [['practica'], 'required', 'message' => 'Falta el nombre de la práctica.'],
            [['resultado', 'codigo'], 'string'],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        return trim((string) ($this->practica ?? '')) === '' ? [self::FIELD_PRACTICA] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toExtractedRow(): array
    {
        return [
            self::FIELD_PRACTICA => (string) ($this->practica ?? ''),
            self::FIELD_RESULTADO => $this->resultado,
            self::FIELD_CODIGO => $this->codigo,
        ];
    }
}

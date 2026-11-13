<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;
use yii\base\Model;

/**
 * Ítem odontológico tipado (prácticas / diagnósticos / estados).
 * Completitud: {@see OdontologiaItemRowContract}.
 */
final class OdontologiaItemInput extends Model
{
    public const FIELD_TIPO = OdontologiaItemRowContract::FIELD_TIPO;
    public const FIELD_CODIGO = OdontologiaItemRowContract::FIELD_CODIGO;
    public const FIELD_PIEZA = OdontologiaItemRowContract::FIELD_PIEZA;
    public const FIELD_CARAS = OdontologiaItemRowContract::FIELD_CARAS;
    public const FIELD_TIEMPO = OdontologiaItemRowContract::FIELD_TIEMPO;

    /** @var string|null */
    public $tipo;

    /** @var string|null */
    public $codigo;

    /** @var string|null */
    public $pieza;

    /** @var string|null */
    public $caras;

    /** @var string|null */
    public $tiempo;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return OdontologiaItemRowContract::PROMPT_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function tiempoValues(): array
    {
        return OdontologiaItemRowContract::TIME_QUALIFIERS;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->tipo = OdontologiaItemRowContract::extractTipo($row) ?: null;
        $model->codigo = OdontologiaItemRowContract::extractCodigo($row) ?: null;
        $model->pieza = OdontologiaItemRowContract::extractPieza($row) ?: null;
        $model->caras = OdontologiaItemRowContract::extractCaras($row) ?: null;
        $model->tiempo = OdontologiaItemRowContract::extractTiempo($row);

        return $model;
    }

    public function rules(): array
    {
        return [
            [['tipo', 'codigo', 'pieza', 'caras', 'tiempo'], 'string'],
            [
                ['tiempo'],
                'in',
                'range' => OdontologiaItemRowContract::TIME_QUALIFIERS,
                'message' => 'El momento debe ser Pasada, Presente o Futura.',
            ],
            [
                ['codigo'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->tipo ?? '')) === '',
                'message' => 'Indique Tipo o Codigo.',
            ],
            [
                ['tipo'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->codigo ?? '')) === '',
                'message' => 'Indique Tipo o Codigo.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        $tipo = trim((string) ($this->tipo ?? ''));
        $codigo = trim((string) ($this->codigo ?? ''));
        if ($tipo === '' && $codigo === '') {
            return [self::FIELD_TIPO, self::FIELD_CODIGO];
        }

        return [];
    }

    public function rowLabel(): string
    {
        $codigo = trim((string) ($this->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }
        $tipo = trim((string) ($this->tipo ?? ''));

        return $tipo !== '' ? $tipo : 'ítem';
    }
}

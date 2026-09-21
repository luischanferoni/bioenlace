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

    /** @var string|null */
    public $tipo;

    /** @var string|null */
    public $codigo;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_TIPO, self::FIELD_CODIGO];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->tipo = OdontologiaItemRowContract::extractTipo($row) ?: null;
        $model->codigo = OdontologiaItemRowContract::extractCodigo($row) ?: null;

        return $model;
    }

    public function rules(): array
    {
        return [
            [['tipo', 'codigo'], 'string'],
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

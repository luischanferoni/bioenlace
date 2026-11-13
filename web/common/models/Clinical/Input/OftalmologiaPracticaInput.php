<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaPracticaRowContract;
use yii\base\Model;

/**
 * Práctica oftalmológica en captura.
 * Completitud: {@see OftalmologiaPracticaRowContract}.
 */
final class OftalmologiaPracticaInput extends Model
{
    public const FIELD_CODIGO = OftalmologiaPracticaRowContract::FIELD_CODIGO;
    public const FIELD_OJO = OftalmologiaPracticaRowContract::FIELD_OJO;
    public const FIELD_PRUEBA = OftalmologiaPracticaRowContract::FIELD_PRUEBA;
    public const FIELD_ESTADO = OftalmologiaPracticaRowContract::FIELD_ESTADO;
    public const FIELD_INFORME = OftalmologiaPracticaRowContract::FIELD_INFORME;
    public const FIELD_TIPO = OftalmologiaPracticaRowContract::FIELD_TIPO;

    /** @var string|null */
    public $codigo;

    /** @var string|null */
    public $ojo;

    /** @var string|null */
    public $prueba;

    /** @var string|null */
    public $estado;

    /** @var string|null */
    public $informe;

    /** @var string|null */
    public $tipo;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return OftalmologiaPracticaRowContract::PROMPT_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function eyeValues(): array
    {
        return OftalmologiaPracticaRowContract::EYE_VALUES;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->codigo = OftalmologiaPracticaRowContract::extractCodigo($row) ?: null;
        $model->ojo = OftalmologiaPracticaRowContract::extractOjo($row) ?: null;
        $model->prueba = OftalmologiaPracticaRowContract::extractPrueba($row) ?: null;
        $model->estado = OftalmologiaPracticaRowContract::extractEstado($row) ?: null;
        $model->informe = OftalmologiaPracticaRowContract::extractInforme($row) ?: null;
        $model->tipo = OftalmologiaPracticaRowContract::extractTipo($row) ?: null;

        return $model;
    }

    public function rules(): array
    {
        return [
            [['codigo', 'ojo', 'prueba', 'estado', 'informe', 'tipo'], 'string'],
            [
                ['ojo'],
                'in',
                'range' => OftalmologiaPracticaRowContract::EYE_VALUES,
                'message' => 'El ojo debe ser OD, OI o AO.',
            ],
            [
                ['codigo'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->prueba ?? '')) === '',
                'message' => 'Indique Codigo o Prueba.',
            ],
            [
                ['prueba'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->codigo ?? '')) === '',
                'message' => 'Indique Codigo o Prueba.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        $codigo = trim((string) ($this->codigo ?? ''));
        $prueba = trim((string) ($this->prueba ?? ''));
        if ($codigo === '' && $prueba === '') {
            return [self::FIELD_CODIGO, self::FIELD_PRUEBA];
        }

        return [];
    }

    public function rowLabel(): string
    {
        $codigo = trim((string) ($this->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }
        $prueba = trim((string) ($this->prueba ?? ''));

        return $prueba !== '' ? $prueba : 'ítem';
    }
}

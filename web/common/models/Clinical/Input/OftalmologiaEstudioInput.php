<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\Policy\OftalmologiaEstudioRowContract;
use yii\base\Model;

/**
 * Estudio oftalmológico en captura.
 * Completitud: {@see OftalmologiaEstudioRowContract}.
 */
final class OftalmologiaEstudioInput extends Model
{
    public const FIELD_CODIGO = OftalmologiaEstudioRowContract::FIELD_CODIGO;
    public const FIELD_OJO = OftalmologiaEstudioRowContract::FIELD_OJO;
    public const FIELD_INFORME = OftalmologiaEstudioRowContract::FIELD_INFORME;

    /** @var string|null */
    public $codigo;

    /** @var string|null */
    public $ojo;

    /** @var string|null */
    public $informe;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_CODIGO, self::FIELD_OJO, self::FIELD_INFORME];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->codigo = OftalmologiaEstudioRowContract::extractCodigo($row) ?: null;
        $model->ojo = OftalmologiaEstudioRowContract::extractOjo($row) ?: null;
        $model->informe = OftalmologiaEstudioRowContract::extractInforme($row) ?: null;

        return $model;
    }

    public function rules(): array
    {
        return [
            [['codigo', 'ojo', 'informe'], 'string'],
            [
                ['codigo'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->informe ?? '')) === '',
                'message' => 'Indique Codigo o Informe.',
            ],
            [
                ['informe'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->codigo ?? '')) === '',
                'message' => 'Indique Codigo o Informe.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        $codigo = trim((string) ($this->codigo ?? ''));
        $informe = trim((string) ($this->informe ?? ''));
        if ($codigo === '' && $informe === '') {
            return [self::FIELD_CODIGO, self::FIELD_INFORME];
        }

        return [];
    }

    public function rowLabel(): string
    {
        $codigo = trim((string) ($this->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }
        $informe = trim((string) ($this->informe ?? ''));

        return $informe !== '' ? mb_substr($informe, 0, 80) : 'ítem';
    }
}

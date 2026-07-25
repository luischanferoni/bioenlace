<?php

namespace common\models\Clinical\Input;

use yii\base\Model;

/**
 * Estudio oftalmológico en captura.
 * Integridad: Codigo o Informe (Ojo opcional en dictado libre).
 */
final class OftalmologiaEstudioInput extends Model
{
    public const FIELD_CODIGO = 'Codigo';
    public const FIELD_OJO = 'Ojo';
    public const FIELD_INFORME = 'Informe';

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
        if (is_string($row)) {
            $model->informe = trim($row);

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->codigo = self::firstNonEmptyString($row, [self::FIELD_CODIGO, 'codigo', 'code', 'prueba']);
        $model->ojo = self::firstNonEmptyString($row, [self::FIELD_OJO, 'ojo', 'eye']);
        $model->informe = self::firstNonEmptyString($row, [
            self::FIELD_INFORME,
            'informe',
            'resultado',
            'texto',
            'display',
        ]);

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
        if ($this->validate()) {
            return [];
        }
        if ($this->hasErrors('codigo') || $this->hasErrors('informe')) {
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

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function firstNonEmptyString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = trim((string) $row[$key]);
            if ($v !== '') {
                return $v;
            }
        }
        $want = [];
        foreach ($keys as $key) {
            $want[self::foldKey($key)] = true;
        }
        foreach ($row as $k => $v) {
            if (!is_string($k) || !isset($want[self::foldKey($k)])) {
                continue;
            }
            $s = trim((string) $v);
            if ($s !== '') {
                return $s;
            }
        }

        return null;
    }

    private static function foldKey(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }
}

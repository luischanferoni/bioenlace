<?php

namespace common\models\Clinical\Input;

use yii\base\Model;

/**
 * Ítem odontológico tipado (prácticas / diagnósticos / estados).
 * Integridad de captura: alcanza Tipo o Codigo (la pieza/caras pueden faltar en dictado).
 */
final class OdontologiaItemInput extends Model
{
    public const FIELD_TIPO = 'Tipo';
    public const FIELD_CODIGO = 'Codigo';

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
        if (is_string($row)) {
            $model->codigo = trim($row);

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->tipo = self::firstNonEmptyString($row, [self::FIELD_TIPO, 'tipo', 'type']);
        $model->codigo = self::firstNonEmptyString($row, [
            self::FIELD_CODIGO,
            'codigo',
            'code',
            'termino',
            'texto',
            'display',
            'label',
        ]);

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
        if ($this->validate()) {
            return [];
        }
        // Un solo mensaje de dominio: falta identificación del ítem.
        if ($this->hasErrors('tipo') || $this->hasErrors('codigo')) {
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

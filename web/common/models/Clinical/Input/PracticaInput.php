<?php

namespace common\models\Clinical\Input;

use yii\base\Model;

/**
 * Contrato de entrada de una práctica realizada en la consulta.
 * Integridad: basta el nombre de la práctica; Resultado y Codigo son opcionales
 * (codificación / valor pueden completarse después).
 */
final class PracticaInput extends Model
{
    public const FIELD_PRACTICA = 'Practica';
    public const FIELD_RESULTADO = 'Resultado';
    public const FIELD_CODIGO = 'Codigo';

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
        if (is_string($row)) {
            $model->practica = trim($row);

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->practica = self::firstNonEmptyString($row, [
            self::FIELD_PRACTICA,
            'practica',
            'termino',
            'texto',
            'display',
            'label',
        ]);
        $model->resultado = self::firstNonEmptyString($row, [
            self::FIELD_RESULTADO,
            'resultado',
            'result',
            'valor',
        ]);
        $model->codigo = self::firstNonEmptyString($row, [
            self::FIELD_CODIGO,
            'codigo',
            'code',
            'conceptId',
        ]);

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
        if ($this->validate()) {
            return [];
        }
        $missing = [];
        if ($this->hasErrors('practica')) {
            $missing[] = self::FIELD_PRACTICA;
        }

        return $missing;
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

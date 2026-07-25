<?php

namespace common\models\Clinical\Input;

use common\models\ConsultaMedicamentos;
use yii\base\Model;

/**
 * Contrato de entrada de medicación (extracción IA → revisión → MedicationRequest).
 *
 * - mentioned: solo se registra el fármaco (p. ej. “toma enalapril”) → basta el nombre.
 * - ordered: se indica/prescribe → nombre + cantidad + frecuencia; tipos/duración condicionales.
 */
final class MedicacionInput extends Model
{
    public const TYPE_MENTIONED = 'mentioned';
    public const TYPE_ORDERED = 'ordered';

    public const FIELD_NOMBRE = 'Nombre del medicamento';
    public const FIELD_TIPO = 'Tipo';
    public const FIELD_CANTIDAD = 'Cantidad';
    public const FIELD_VIA = 'Via de administracion';
    public const FIELD_FRECUENCIA = 'Frecuencia de administracion';
    public const FIELD_TIPO_FRECUENCIA = 'Tipo de frecuencia';
    public const FIELD_DURACION = 'Duracion del tratamiento';
    public const FIELD_TIPO_DURACION = 'Tipo de duracion';

    /** @var string|null */
    public $nombre;

    /** @var string|null mentioned|ordered */
    public $tipo;

    /** @var string|null */
    public $cantidad;

    /** @var string|null */
    public $via;

    /** @var string|null */
    public $frecuencia;

    /** @var string|null */
    public $tipoFrecuencia;

    /** @var string|null */
    public $duracion;

    /** @var string|null */
    public $tipoDuracion;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [
            self::FIELD_NOMBRE,
            self::FIELD_TIPO,
            self::FIELD_CANTIDAD,
            self::FIELD_VIA,
            self::FIELD_FRECUENCIA,
            self::FIELD_TIPO_FRECUENCIA,
            self::FIELD_DURACION,
            self::FIELD_TIPO_DURACION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return [self::TYPE_MENTIONED, self::TYPE_ORDERED];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        if (is_string($row)) {
            $model->nombre = trim($row);
            $model->inferMissingTipo();
            $model->normalizeTipo();
            $model->applyDefaults();

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->nombre = self::firstNonEmptyString($row, [
            self::FIELD_NOMBRE,
            'nombre',
            'medicamento',
            'medication_display',
            'termino',
            'display',
            'label',
            'texto',
        ]);
        $model->tipo = self::firstNonEmptyString($row, [
            self::FIELD_TIPO,
            'tipo',
            'type',
            'kind',
        ]);
        $model->cantidad = self::firstNonEmptyString($row, [self::FIELD_CANTIDAD, 'cantidad', 'dose', 'dosis']);
        $model->via = self::firstNonEmptyString($row, [self::FIELD_VIA, 'via', 'route']);
        $model->frecuencia = self::firstNonEmptyString($row, [self::FIELD_FRECUENCIA, 'frecuencia', 'frequency']);
        $model->tipoFrecuencia = self::firstNonEmptyString($row, [
            self::FIELD_TIPO_FRECUENCIA,
            'tipo_frecuencia',
            'frecuencia_tipo',
        ]);
        $model->duracion = self::firstNonEmptyString($row, [self::FIELD_DURACION, 'duracion', 'durante']);
        $model->tipoDuracion = self::firstNonEmptyString($row, [
            self::FIELD_TIPO_DURACION,
            'tipo_duracion',
            'durante_tipo',
        ]);
        $model->inferMissingTipo();
        $model->normalizeTipo();
        $model->applyDefaults();

        return $model;
    }

    public function rules(): array
    {
        $freqTypes = array_keys(ConsultaMedicamentos::FRECUENCIAS);
        $durTypes = array_keys(ConsultaMedicamentos::DURANTES);

        return [
            [['nombre'], 'trim'],
            [['nombre'], 'required', 'message' => 'Falta el nombre del medicamento.'],
            [['tipo'], 'required', 'message' => 'Falta el tipo de medicación (mentioned|ordered).'],
            [['tipo'], 'in', 'range' => self::typeValues()],
            [
                ['cantidad', 'frecuencia'],
                'required',
                'when' => static fn (self $m) => $m->tipo === self::TYPE_ORDERED,
                'message' => 'Campo requerido para medicación indicada.',
            ],
            [
                ['tipoFrecuencia'],
                'required',
                'when' => static fn (self $m) => $m->tipo === self::TYPE_ORDERED
                    && trim((string) ($m->frecuencia ?? '')) !== '',
            ],
            [
                ['tipoDuracion'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->duracion ?? '')) !== '',
            ],
            [['tipoFrecuencia'], 'in', 'range' => $freqTypes, 'skipOnEmpty' => true],
            [['tipoDuracion'], 'in', 'range' => $durTypes, 'skipOnEmpty' => true],
            [['cantidad', 'via', 'frecuencia', 'duracion'], 'string'],
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
        $map = [
            'nombre' => self::FIELD_NOMBRE,
            'tipo' => self::FIELD_TIPO,
            'cantidad' => self::FIELD_CANTIDAD,
            'frecuencia' => self::FIELD_FRECUENCIA,
            'tipoFrecuencia' => self::FIELD_TIPO_FRECUENCIA,
            'duracion' => self::FIELD_DURACION,
            'tipoDuracion' => self::FIELD_TIPO_DURACION,
            'via' => self::FIELD_VIA,
        ];
        $missing = [];
        foreach ($map as $attr => $field) {
            if ($this->hasErrors($attr)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toExtractedRow(): array
    {
        return [
            self::FIELD_NOMBRE => (string) ($this->nombre ?? ''),
            self::FIELD_TIPO => (string) ($this->tipo ?? self::TYPE_MENTIONED),
            self::FIELD_CANTIDAD => $this->cantidad,
            self::FIELD_VIA => $this->via,
            self::FIELD_FRECUENCIA => $this->frecuencia,
            self::FIELD_TIPO_FRECUENCIA => $this->tipoFrecuencia,
            self::FIELD_DURACION => $this->duracion,
            self::FIELD_TIPO_DURACION => $this->tipoDuracion,
        ];
    }

    private function normalizeTipo(): void
    {
        $raw = strtolower(trim((string) ($this->tipo ?? '')));
        $raw = str_replace(['-', ' '], '_', $raw);
        $map = [
            'mentioned' => self::TYPE_MENTIONED,
            'mencion' => self::TYPE_MENTIONED,
            'mención' => self::TYPE_MENTIONED,
            'registrado' => self::TYPE_MENTIONED,
            'registro' => self::TYPE_MENTIONED,
            'ordered' => self::TYPE_ORDERED,
            'order' => self::TYPE_ORDERED,
            'indicado' => self::TYPE_ORDERED,
            'indicada' => self::TYPE_ORDERED,
            'prescripto' => self::TYPE_ORDERED,
            'prescripta' => self::TYPE_ORDERED,
            'prescribe' => self::TYPE_ORDERED,
            'prescripcion' => self::TYPE_ORDERED,
            'prescripción' => self::TYPE_ORDERED,
        ];
        $this->tipo = $map[$raw] ?? ($raw !== '' ? $raw : null);
    }

    private function inferMissingTipo(): void
    {
        if (trim((string) ($this->tipo ?? '')) !== '') {
            return;
        }
        $hasDosing = trim((string) ($this->cantidad ?? '')) !== ''
            || trim((string) ($this->frecuencia ?? '')) !== ''
            || trim((string) ($this->via ?? '')) !== ''
            || trim((string) ($this->duracion ?? '')) !== '';
        $this->tipo = $hasDosing ? self::TYPE_ORDERED : self::TYPE_MENTIONED;
    }

    private function applyDefaults(): void
    {
        if ($this->tipo === self::TYPE_ORDERED
            && trim((string) ($this->frecuencia ?? '')) !== ''
            && trim((string) ($this->tipoFrecuencia ?? '')) === '') {
            $this->tipoFrecuencia = ConsultaMedicamentos::FRECUENCIA_TIPO_DIA;
        }
        if (trim((string) ($this->duracion ?? '')) !== ''
            && trim((string) ($this->tipoDuracion ?? '')) === '') {
            $folded = mb_strtolower((string) $this->duracion, 'UTF-8');
            if (str_contains($folded, 'cronic') || str_contains($folded, 'crónic')) {
                $this->tipoDuracion = ConsultaMedicamentos::DURANTE_TIPO_CRONICO;
            } else {
                $this->tipoDuracion = ConsultaMedicamentos::DURANTE_TIPO_DIA;
            }
        }
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

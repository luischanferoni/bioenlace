<?php

namespace common\models\Clinical\Input;

use common\models\Servicio;
use yii\base\Model;

/**
 * Contrato de entrada de una derivación/interconsulta (extracción IA → revisión → ServiceRequest referral).
 *
 * Integridad: hace falta un servicio destino resoluble (`id_servicio` o nombre matcheable).
 * El efector destino suele ser el del encounter solicitante si no viene en la fila.
 */
final class DerivacionInput extends Model
{
    public const FIELD_SERVICIO = 'Servicio';
    public const FIELD_ID_SERVICIO = 'id_servicio';
    public const FIELD_ID_EFECTOR = 'id_efector';
    public const FIELD_INDICACIONES = 'Indicaciones';

    /** @var string|null texto libre del servicio (p. ej. "clínico") */
    public $servicio;

    /** @var int|null */
    public $idServicio;

    /** @var int|null */
    public $idEfector;

    /** @var string|null */
    public $indicaciones;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_SERVICIO];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        if (is_string($row)) {
            $model->servicio = trim($row) !== '' ? trim($row) : null;
            $model->normalize();

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->servicio = self::firstNonEmptyString($row, [
            self::FIELD_SERVICIO,
            'servicio',
            'service',
            'display',
            'texto',
            'termino',
        ]);
        $model->idServicio = self::firstPositiveInt($row, [
            self::FIELD_ID_SERVICIO,
            'target_service_id',
            'idServicio',
        ]);
        $model->idEfector = self::firstPositiveInt($row, [
            self::FIELD_ID_EFECTOR,
            'target_efector_id',
            'idEfector',
        ]);
        $model->indicaciones = self::firstNonEmptyString($row, [
            self::FIELD_INDICACIONES,
            'indicaciones',
            'note',
            'nota',
        ]);
        $model->normalize();

        return $model;
    }

    public function rules(): array
    {
        return [
            [['servicio', 'indicaciones'], 'string'],
            [['idServicio', 'idEfector'], 'integer', 'min' => 1],
            [['idServicio'], 'required', 'message' => 'Falta el servicio de destino de la derivación.'],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        $this->normalize();
        if ($this->idServicio !== null && $this->idServicio > 0) {
            return [];
        }

        return [self::FIELD_SERVICIO];
    }

    /**
     * Issues resolubles: chips de servicios con agenda (sin preselección).
     *
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        $issues = [];
        foreach ($this->missingFieldsForCompleteness() as $field) {
            if ($field !== self::FIELD_SERVICIO) {
                continue;
            }
            $options = self::optionsForServicio();
            if ($options === []) {
                continue;
            }
            $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                $category,
                $index,
                $field,
                $options,
                false
            );
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        if ($field === self::FIELD_SERVICIO || $field === self::FIELD_ID_SERVICIO) {
            if (is_numeric($value)) {
                $id = (int) $value;
                $row[self::FIELD_ID_SERVICIO] = $id;
                $servicio = Servicio::findOne(['id_servicio' => $id]);
                if ($servicio !== null) {
                    $row[self::FIELD_SERVICIO] = (string) $servicio->nombre;
                }
            } else {
                $row[self::FIELD_SERVICIO] = is_string($value) ? trim($value) : (string) $value;
                $resolved = Servicio::findByName((string) $row[self::FIELD_SERVICIO]);
                if ($resolved !== null && $resolved > 0) {
                    $row[self::FIELD_ID_SERVICIO] = $resolved;
                }
            }
        }

        return $row;
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    public static function optionsForServicio(): array
    {
        $out = [];
        foreach (Servicio::getServiciosConTurnos() as $s) {
            $id = (int) ($s->id_servicio ?? 0);
            $nombre = trim((string) ($s->nombre ?? ''));
            if ($id <= 0 || $nombre === '') {
                continue;
            }
            $out[] = ['value' => $id, 'label' => $nombre];
        }

        return $out;
    }

    /**
     * Resuelve ids de destino (servicio por nombre si hace falta; efector por default del encounter).
     *
     * @return array{id_servicio: int|null, id_efector: int|null, display: string|null, note: string|null}
     */
    public function resolveTargets(?int $defaultEfectorId): array
    {
        $this->normalize();
        $idServicio = $this->idServicio;
        $idEfector = $this->idEfector;
        if (($idEfector === null || $idEfector <= 0) && $defaultEfectorId !== null && $defaultEfectorId > 0) {
            $idEfector = $defaultEfectorId;
        }
        $display = $this->servicio;
        if ($display === null || $display === '') {
            if ($idServicio !== null && $idServicio > 0) {
                $s = Servicio::findOne(['id_servicio' => $idServicio]);
                $display = $s !== null ? (string) $s->nombre : null;
            }
        }

        return [
            'id_servicio' => $idServicio !== null && $idServicio > 0 ? $idServicio : null,
            'id_efector' => $idEfector !== null && $idEfector > 0 ? $idEfector : null,
            'display' => $display !== null && $display !== '' ? $display : null,
            'note' => $this->indicaciones !== null && $this->indicaciones !== '' ? $this->indicaciones : null,
        ];
    }

    private function normalize(): void
    {
        if (($this->idServicio === null || $this->idServicio <= 0) && $this->servicio !== null && $this->servicio !== '') {
            $resolved = Servicio::findByName($this->servicio);
            if ($resolved !== null && $resolved > 0) {
                $this->idServicio = $resolved;
            }
        }
        if ($this->idServicio !== null && $this->idServicio <= 0) {
            $this->idServicio = null;
        }
        if ($this->idEfector !== null && $this->idEfector <= 0) {
            $this->idEfector = null;
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

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function firstPositiveInt(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            if (is_numeric($row[$key]) && (int) $row[$key] > 0) {
                return (int) $row[$key];
            }
        }

        return null;
    }
}

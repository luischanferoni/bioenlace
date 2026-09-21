<?php

namespace common\components\Domain\Clinical\Capture\Domain\Policy;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureIssueFactory;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;
use common\components\Domain\Clinical\Capture\Domain\Port\DerivacionRowSupportPort;
use common\components\Domain\Clinical\PedidoAtencion\Domain\CodingSystems;
use common\components\Domain\Clinical\PedidoAtencion\Domain\Model\PedidoAtencion;

/**
 * Contrato Domain: derivación/interconsulta (`ConsultaDerivaciones`).
 * I/O vía {@see DerivacionRowSupportPort}.
 */
final class DerivacionRowContract
{
    public const MODELO = 'ConsultaDerivaciones';

    public const FIELD_SERVICIO = 'Servicio';
    public const FIELD_ID_SERVICIO = 'id_servicio';
    public const FIELD_ID_EFECTOR = 'id_efector';
    public const FIELD_INDICACIONES = 'Indicaciones';
    public const FIELD_ACTO_CODE = 'Acto code';
    public const FIELD_ACTO_SYSTEM = 'Acto system';
    public const FIELD_ACTO_DISPLAY = 'Acto';
    public const FIELD_MODO = 'Modo';

    public const REFERRAL_PRACTICA = 'PRACTICA';
    public const REFERRAL_INTERCONSULTA = 'INTERCONSULTA';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{
     *   servicio: string|null,
     *   idServicio: int|null,
     *   idEfector: int|null,
     *   indicaciones: string|null,
     *   actoCode: string|null,
     *   actoSystem: string|null,
     *   actoDisplay: string|null,
     *   modo: string,
     *   actoCodingCandidates: list<array{code: string, system: string, display: string}>
     * }
     */
    public static function parse($row, DerivacionRowSupportPort $support): array
    {
        $p = [
            'servicio' => null,
            'idServicio' => null,
            'idEfector' => null,
            'indicaciones' => null,
            'actoCode' => null,
            'actoSystem' => null,
            'actoDisplay' => null,
            'modo' => PedidoAtencion::MODO_INTERCONSULTA,
            'actoCodingCandidates' => [],
        ];

        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            $p['servicio'] = $s;
        } elseif (is_array($row)) {
            $p['servicio'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_SERVICIO,
                'servicio',
                'service',
                'texto',
                'termino',
            ]);
            $p['idServicio'] = self::firstPositiveInt($row, [
                self::FIELD_ID_SERVICIO,
                'target_service_id',
                'idServicio',
            ]);
            $p['idEfector'] = self::firstPositiveInt($row, [
                self::FIELD_ID_EFECTOR,
                'target_efector_id',
                'idEfector',
            ]);
            $p['indicaciones'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_INDICACIONES,
                'indicaciones',
                'note',
                'nota',
            ]);
            $p['actoCode'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_ACTO_CODE,
                'codigo',
                'code',
                'acto_code',
            ]);
            $p['actoSystem'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_ACTO_SYSTEM,
                'code_system',
                'acto_system',
            ]);
            $p['actoDisplay'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_ACTO_DISPLAY,
                'acto',
                'acto_display',
            ]);
            if ($p['actoDisplay'] === null && $p['actoCode'] !== null) {
                $p['actoDisplay'] = ExtractedRowFields::firstNonEmpty($row, ['display', 'termino']);
            }
            if ($p['servicio'] === null) {
                $p['servicio'] = ExtractedRowFields::firstNonEmpty($row, ['display']);
            }
            $modo = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_MODO,
                'modo',
                'tipo',
                'referral_kind',
                'tipo_solicitud',
            ]);
            $p['modo'] = self::normalizeModo($modo);
        }

        return self::normalizeAndEnrich($p, $support);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess(
        $row,
        string $categoryTitle,
        int $index,
        DerivacionRowSupportPort $support
    ): ClinicalCaptureRowCompleteness {
        $p = self::parse($row, $support);
        $resolved = $support->resolvePedidoCompleteness(
            $p['idServicio'],
            $p['actoCode'],
            $p['actoSystem'],
            $p['modo'],
            $p['indicaciones'],
            $p['idEfector'],
            $p['actoDisplay']
        );

        $missing = [];
        foreach ($resolved['missing'] as $slot) {
            if ($slot === 'linea') {
                $missing[] = self::FIELD_SERVICIO;
            }
            if ($slot === 'acto') {
                $missing[] = self::FIELD_ACTO_DISPLAY;
            }
        }

        $label = trim((string) ($p['servicio'] ?? ''));
        if ($label === '') {
            $label = 'ítem';
        }

        $issues = [];
        if (in_array(self::FIELD_SERVICIO, $missing, true)) {
            $options = $resolved['lineas'] !== []
                ? array_map(
                    static fn (array $l) => ['value' => $l['id'], 'label' => $l['label']],
                    $resolved['lineas']
                )
                : $support->servicioOptions();
            if ($options !== []) {
                $issues[] = ClinicalCaptureIssueFactory::make(
                    $categoryTitle,
                    $index,
                    self::FIELD_SERVICIO,
                    $options,
                    false
                );
            }
        }
        if (in_array(self::FIELD_ACTO_DISPLAY, $missing, true)) {
            $actoCandidates = $resolved['actos'];
            if ($actoCandidates === [] && $p['actoCodingCandidates'] !== []) {
                $actoCandidates = $p['actoCodingCandidates'];
            }
            $options = array_map(
                static fn (array $a) => [
                    'value' => $a['system'] . '|' . $a['code'],
                    'label' => $a['display'] !== '' ? $a['display'] : $a['code'],
                ],
                $actoCandidates
            );
            if ($options !== []) {
                $issues[] = ClinicalCaptureIssueFactory::make(
                    $categoryTitle,
                    $index,
                    self::FIELD_ACTO_DISPLAY,
                    $options,
                    false
                );
            }
        }

        return new ClinicalCaptureRowCompleteness($missing, $label, $issues);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolution(array $row, string $field, mixed $value, DerivacionRowSupportPort $support): array
    {
        if ($field === self::FIELD_SERVICIO || $field === self::FIELD_ID_SERVICIO) {
            if (is_numeric($value)) {
                $id = (int) $value;
                $row[self::FIELD_ID_SERVICIO] = $id;
                $nombre = $support->resolveLineaNameById($id);
                if ($nombre !== null) {
                    $row[self::FIELD_SERVICIO] = $nombre;
                }
            } else {
                $row[self::FIELD_SERVICIO] = is_string($value) ? trim($value) : (string) $value;
                $hydrated = self::hydrateExtractedRowOrNull($row, $support);
                if ($hydrated !== null) {
                    return $hydrated;
                }
            }
        }

        if ($field === self::FIELD_ACTO_DISPLAY || $field === self::FIELD_ACTO_CODE) {
            $raw = is_string($value) ? trim($value) : (string) $value;
            if (str_contains($raw, '|')) {
                [$system, $code] = explode('|', $raw, 2);
                $row[self::FIELD_ACTO_SYSTEM] = trim($system);
                $row[self::FIELD_ACTO_CODE] = trim($code);
                $row['code_system'] = trim($system);
                $row['codigo'] = trim($code);
            } elseif (!is_numeric($value)) {
                $row[self::FIELD_ACTO_DISPLAY] = $raw;
                $row[self::FIELD_ACTO_CODE] = $raw;
                $row['codigo'] = $raw;
                if (empty($row[self::FIELD_ACTO_SYSTEM]) && empty($row['code_system'])) {
                    $row[self::FIELD_ACTO_SYSTEM] = CodingSystems::SNOMED;
                    $row['code_system'] = CodingSystems::SNOMED;
                }
            }
        }

        if ($field === self::FIELD_MODO) {
            $row[self::FIELD_MODO] = is_string($value) ? trim($value) : (string) $value;
            $row['modo'] = $row[self::FIELD_MODO];
        }

        return $row;
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array<string, mixed>|null
     */
    public static function hydrateExtractedRowOrNull($row, DerivacionRowSupportPort $support): ?array
    {
        $p = self::parse($row, $support);
        if ($p['idServicio'] === null || $p['idServicio'] <= 0) {
            return null;
        }
        $out = is_array($row) ? $row : [];
        $out[self::FIELD_ID_SERVICIO] = $p['idServicio'];
        $out[self::FIELD_SERVICIO] = (string) ($p['servicio'] ?? '');
        if ($p['indicaciones'] !== null && $p['indicaciones'] !== '') {
            $out[self::FIELD_INDICACIONES] = $p['indicaciones'];
        }
        if ($p['actoCode'] !== null) {
            $out[self::FIELD_ACTO_CODE] = $p['actoCode'];
            $out['codigo'] = $p['actoCode'];
        }
        if ($p['actoSystem'] !== null) {
            $out[self::FIELD_ACTO_SYSTEM] = $p['actoSystem'];
            $out['code_system'] = $p['actoSystem'];
        }
        if ($p['actoDisplay'] !== null) {
            $out[self::FIELD_ACTO_DISPLAY] = $p['actoDisplay'];
        }
        $out[self::FIELD_MODO] = $p['modo'];
        $out['modo'] = $p['modo'];

        return $out;
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    public static function refineDatosExtraidos(array $extraidos, array $categorias, DerivacionRowSupportPort $support): array
    {
        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            if ((string) ($categoria['modelo'] ?? '') !== self::MODELO) {
                continue;
            }
            $titulo = trim((string) ($categoria['titulo'] ?? ''));
            if ($titulo === '' || !isset($extraidos[$titulo]) || !is_array($extraidos[$titulo])) {
                continue;
            }
            $refined = [];
            foreach ($extraidos[$titulo] as $row) {
                $hydrated = self::hydrateExtractedRowOrNull($row, $support);
                if ($hydrated !== null) {
                    $refined[] = $hydrated;
                }
            }
            $extraidos[$titulo] = $refined;
        }

        return $extraidos;
    }

    public static function normalizeModo(?string $modo): string
    {
        $raw = strtolower(trim((string) $modo));
        if ($raw === '') {
            return PedidoAtencion::MODO_INTERCONSULTA;
        }
        if (in_array($raw, PedidoAtencion::modos(), true)) {
            return $raw;
        }
        if (in_array($raw, ['practica', 'práctica', self::REFERRAL_PRACTICA], true)
            || str_contains($raw, 'practic')
            || str_contains($raw, 'estudio')
            || str_contains($raw, 'imaging')
        ) {
            return PedidoAtencion::MODO_PRACTICA;
        }
        if (str_contains($raw, 'consult') || $raw === self::REFERRAL_INTERCONSULTA) {
            return PedidoAtencion::MODO_INTERCONSULTA;
        }

        return PedidoAtencion::MODO_INTERCONSULTA;
    }

    public static function referralKindForModo(string $modo): string
    {
        $modo = strtolower(trim($modo));
        if (in_array($modo, [PedidoAtencion::MODO_PRACTICA, PedidoAtencion::MODO_ESTUDIO], true)) {
            return self::REFERRAL_PRACTICA;
        }

        return self::REFERRAL_INTERCONSULTA;
    }

    /**
     * @param array{
     *   servicio: string|null,
     *   idServicio: int|null,
     *   idEfector: int|null,
     *   indicaciones: string|null,
     *   actoCode: string|null,
     *   actoSystem: string|null,
     *   actoDisplay: string|null,
     *   modo: string,
     *   actoCodingCandidates: list<array{code: string, system: string, display: string}>
     * } $p
     * @return array{
     *   servicio: string|null,
     *   idServicio: int|null,
     *   idEfector: int|null,
     *   indicaciones: string|null,
     *   actoCode: string|null,
     *   actoSystem: string|null,
     *   actoDisplay: string|null,
     *   modo: string,
     *   actoCodingCandidates: list<array{code: string, system: string, display: string}>
     * }
     */
    private static function normalizeAndEnrich(array $p, DerivacionRowSupportPort $support): array
    {
        if (($p['idServicio'] === null || $p['idServicio'] <= 0) && $p['servicio'] !== null && $p['servicio'] !== '') {
            $resolved = $support->resolveLineaIdByName($p['servicio']);
            if ($resolved !== null) {
                $p['idServicio'] = $resolved;
            }
        }
        if (($p['idServicio'] === null || $p['idServicio'] <= 0) && $p['servicio'] !== null && $p['servicio'] !== '') {
            $bySpecialty = $support->resolveLineaBySpecialtyNl($p['servicio']);
            if ($bySpecialty !== null) {
                $p['idServicio'] = $bySpecialty['id'];
                $p['servicio'] = $bySpecialty['nombre'];
            }
        }
        if ($p['idServicio'] !== null && $p['idServicio'] > 0) {
            $named = $support->resolveLineaNameById($p['idServicio']);
            if ($named !== null) {
                $p['servicio'] = $named;
            }
        }
        if ($p['idServicio'] !== null && $p['idServicio'] <= 0) {
            $p['idServicio'] = null;
        }
        if ($p['idEfector'] !== null && $p['idEfector'] <= 0) {
            $p['idEfector'] = null;
        }
        if ($p['actoCode'] !== null && trim($p['actoCode']) === '') {
            $p['actoCode'] = null;
        }
        if ($p['actoSystem'] !== null && trim($p['actoSystem']) === '') {
            $p['actoSystem'] = null;
        }
        if ($p['actoCode'] !== null && $p['actoSystem'] === null) {
            $p['actoSystem'] = CodingSystems::SNOMED;
        }
        $p['modo'] = self::normalizeModo($p['modo']);

        $p['actoCodingCandidates'] = [];
        if ($p['actoCode'] === null || trim((string) $p['actoCode']) === '') {
            $display = trim((string) ($p['actoDisplay'] ?? ''));
            if ($display !== '') {
                $result = $support->codeActo($display, $p['modo']);
                if ($result['resolved'] !== null) {
                    $p['actoCode'] = $result['resolved']['code'];
                    $p['actoSystem'] = $result['resolved']['system'];
                    $p['actoDisplay'] = $result['resolved']['display'];
                } else {
                    $p['actoCodingCandidates'] = $result['candidates'];
                }
            }
        }

        return $p;
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

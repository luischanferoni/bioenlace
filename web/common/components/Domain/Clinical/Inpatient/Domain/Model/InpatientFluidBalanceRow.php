<?php

namespace common\components\Domain\Clinical\Inpatient\Domain\Model;

/**
 * Vista de balance hídrico (Observation FHIR → shape legacy para UI).
 */
final class InpatientFluidBalanceRow
{
    public const TREG_INGRESO = 'Ingreso';
    public const TREG_EGRESO = 'Egreso';

    /** @var array<int, string> */
    public static array $tiposCodIngreso = [
        101 => 'SOLUCION FISIOLOGICA',
        102 => 'SOL DEXT AL 5%',
        103 => 'SOL DEXT AL 10%',
        104 => 'SOL DEXT AL 25%',
        105 => 'SOL DEXT AL 30%',
        106 => 'SOLUCION ELECTORLITICA BALANCEADA',
        107 => 'SOLUCION RINGERR',
        108 => 'INFUCOL',
        109 => 'MANITOL',
        110 => 'NUTRICION PARENTERAL',
        111 => 'ANTIBIOTICO',
        112 => 'ALBUM-INA',
        113 => 'HEMO DERIVADAS',
        114 => 'OTROS',
    ];

    /** @var array<int, string> */
    public static array $tiposCodEgreso = [
        201 => 'DIURESIS',
        202 => 'CATARSIS',
        203 => 'COLOST. ILEOSTOMIA',
        204 => 'TAR FISTULAS',
        205 => 'DIALISIS',
        206 => 'DRENA 1',
        207 => 'DRENA 2',
        208 => 'SUDACION',
        209 => 'FIEBRE',
        210 => 'RESPIRACION',
        211 => 'PERSPIRACION',
    ];

    public int $id = 0;
    public int $id_consulta = 0;
    public string $tipo_registro = '';
    public ?int $cod_ingreso = null;
    public ?int $cod_egreso = null;
    public ?int $cantidad = null;
    public mixed $hora_inicio = null;
    public mixed $hora_fin = null;
    public mixed $fecha = null;

    public function getCodigoRegistroDescription(): string
    {
        if ($this->tipo_registro === self::TREG_EGRESO) {
            return self::$tiposCodEgreso[$this->cod_egreso] ?? '';
        }

        return self::$tiposCodIngreso[$this->cod_ingreso] ?? '';
    }
}

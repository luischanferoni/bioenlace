<?php

namespace common\components\Domain\Person\Service\Seed;

use common\models\GeoRecursoInstitucional;
use common\models\GeoRecursoTipo;
use common\models\GeoRecursoTipoAlias;
use common\models\Pais;
use common\models\Provincia;
use Yii;

/**
 * Seed de recursos institucionales (ministerios, etc.) en BD.
 */
final class GeoRecursosInstitucionalesSeedService
{
    /**
     * @return array{tipos: int, aliases: int, recursos: int}
     */
    public function upsertDefaults(): array
    {
        $tipo = 'ministerio_salud';
        if (GeoRecursoTipo::findOne($tipo) === null) {
            $row = new GeoRecursoTipo();
            $row->tipo = $tipo;
            $row->save(false);
        }

        $aliases = ['ministerio de salud', 'ministerio salud', 'ms'];
        $aliasCount = 0;
        foreach ($aliases as $alias) {
            if (GeoRecursoTipoAlias::findOne(['tipo' => $tipo, 'alias' => $alias]) !== null) {
                continue;
            }
            $a = new GeoRecursoTipoAlias();
            $a->tipo = $tipo;
            $a->alias = $alias;
            $a->save(false);
            $aliasCount++;
        }

        $recursos = 0;
        $recursos += $this->upsertRecurso(Pais::ID_ARGENTINA, '86', $tipo, [
            'nombre' => 'Ministerio de Salud de Santiago del Estero',
            'direccion' => 'Av. Juan Bautista Alberdi 601, Santiago del Estero',
            'telefono' => '0385-421-0100',
        ]);
        $recursos += $this->upsertRecurso(Pais::ID_ARGENTINA, '14', $tipo, [
            'nombre' => 'Ministerio de Salud de la Provincia de Buenos Aires',
            'direccion' => 'Calle 7 entre 50 y 51, La Plata',
            'telefono' => '0221-429-3400',
        ]);
        $recursos += $this->upsertRecurso(Pais::ID_ARGENTINA, '82', $tipo, [
            'nombre' => 'Ministerio de Salud de Santa Fe',
            'direccion' => 'San Martín 2627, Santa Fe',
            'telefono' => '0342-450-6500',
        ]);
        $recursos += $this->upsertRecursoNacional(Pais::ID_ARGENTINA, $tipo, [
            'nombre' => 'Ministerio de Salud de la Nación',
            'direccion' => 'Av. Paseo Colón 195, CABA',
            'telefono' => '0800-222-3362',
        ]);
        $recursos += $this->upsertRecurso(Pais::ID_URUGUAY, 'MO', $tipo, [
            'nombre' => 'Ministerio de Salud Pública (Uruguay)',
            'direccion' => '18 de Julio 1892, Montevideo',
            'telefono' => '0800-8855',
        ]);

        return ['tipos' => 1, 'aliases' => $aliasCount, 'recursos' => $recursos];
    }

    /**
     * @param array{nombre: string, direccion?: string, telefono?: string} $data
     */
    private function upsertRecurso(int $idPais, string $codSubdivision, string $tipo, array $data): int
    {
        $provincia = Provincia::findOne(['id_pais' => $idPais, 'cod_indec' => $codSubdivision]);
        if ($provincia === null) {
            Yii::warning("GeoRecursos seed: falta provincia {$codSubdivision} pais={$idPais}", __METHOD__);

            return 0;
        }

        $existing = GeoRecursoInstitucional::findOne([
            'tipo' => $tipo,
            'id_pais' => $idPais,
            'id_provincia' => $provincia->id_provincia,
        ]);
        if ($existing === null) {
            $existing = new GeoRecursoInstitucional();
            $existing->tipo = $tipo;
            $existing->id_pais = $idPais;
            $existing->id_provincia = (int) $provincia->id_provincia;
        }
        $existing->nombre = $data['nombre'];
        $existing->direccion = $data['direccion'] ?? null;
        $existing->telefono = $data['telefono'] ?? null;
        $existing->save(false);

        return 1;
    }

    /**
     * @param array{nombre: string, direccion?: string, telefono?: string} $data
     */
    private function upsertRecursoNacional(int $idPais, string $tipo, array $data): int
    {
        $existing = GeoRecursoInstitucional::find()
            ->where(['tipo' => $tipo, 'id_pais' => $idPais])
            ->andWhere(['id_provincia' => null])
            ->one();
        if ($existing === null) {
            $existing = new GeoRecursoInstitucional();
            $existing->tipo = $tipo;
            $existing->id_pais = $idPais;
            $existing->id_provincia = null;
        }
        $existing->nombre = $data['nombre'];
        $existing->direccion = $data['direccion'] ?? null;
        $existing->telefono = $data['telefono'] ?? null;
        $existing->save(false);

        return 1;
    }
}

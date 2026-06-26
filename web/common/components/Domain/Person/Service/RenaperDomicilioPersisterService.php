<?php

namespace common\components\Domain\Person\Service;

use common\models\Departamento;
use common\models\Domicilio;
use common\models\Localidad;
use common\models\Person\Persona;
use common\models\Persona_domicilio;
use common\models\Provincia;
use Yii;

/**
 * Persiste domicilio de persona a partir de fila RENAPER (inmutable desde la app).
 */
final class RenaperDomicilioPersisterService
{
    /**
     * @param array<string, mixed> $renaper
     * @return array{id_provincia: int|null, id_domicilio: int|null}
     */
    public function persistFromRenaper(Persona $persona, array $renaper): array
    {
        $provincia = $this->resolveProvincia($renaper);
        if ($provincia === null) {
            return ['id_provincia' => null, 'id_domicilio' => null];
        }

        $localidad = $this->resolveLocalidad($renaper, $provincia);
        if ($localidad === null) {
            return ['id_provincia' => (int) $provincia->id_provincia, 'id_domicilio' => null];
        }

        $calle = trim((string) ($renaper['calle'] ?? $renaper['direccion'] ?? ''));
        if ($calle === '') {
            $calle = 'S/D';
        }
        $numero = trim((string) ($renaper['numero'] ?? $renaper['numero_puerta'] ?? ''));
        if ($numero === '') {
            $numero = '0';
        }
        $barrio = trim((string) ($renaper['barrio'] ?? $renaper['ciudad'] ?? $renaper['municipio'] ?? 'S/D'));
        if ($barrio === '') {
            $barrio = 'S/D';
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $domicilio = new Domicilio();
            $domicilio->calle = mb_substr($calle, 0, 60);
            $domicilio->numero = mb_substr($numero, 0, 10);
            $domicilio->barrio = mb_substr($barrio, 0, 60);
            $domicilio->id_localidad = (int) $localidad->id_localidad;
            $domicilio->usuario_alta = 'renaper';
            $domicilio->fecha_alta = date('Y-m-d');
            if (!$domicilio->save(false)) {
                throw new \RuntimeException('No se pudo guardar domicilio RENAPER.');
            }

            Persona_domicilio::updateAll(['activo' => 'NO'], ['id_persona' => (int) $persona->id_persona]);

            $vinculo = new Persona_domicilio();
            $vinculo->id_persona = (int) $persona->id_persona;
            $vinculo->id_domicilio = (int) $domicilio->id_domicilio;
            $vinculo->activo = 'SI';
            $vinculo->usuario_alta = 'renaper';
            $vinculo->fecha_alta = date('Y-m-d');
            if (!$vinculo->save(false)) {
                throw new \RuntimeException('No se pudo vincular domicilio RENAPER.');
            }

            $tx->commit();

            return [
                'id_provincia' => (int) $provincia->id_provincia,
                'id_domicilio' => (int) $domicilio->id_domicilio,
            ];
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('RenaperDomicilioPersister: ' . $e->getMessage(), 'renaper');
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $renaper
     */
    public function resolveProvincia(array $renaper): ?Provincia
    {
        $codIndec = trim((string) ($renaper['id_provincia'] ?? $renaper['cod_provincia'] ?? ''));
        if ($codIndec !== '') {
            $codIndec = str_pad($codIndec, 2, '0', STR_PAD_LEFT);
            $provincia = Provincia::find()->where(['cod_indec' => $codIndec])->one();
            if ($provincia instanceof Provincia) {
                return $provincia;
            }
        }

        $nombre = $this->normalizeNombreProvincia((string) ($renaper['provincia'] ?? $renaper['provincia_nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        $candidatas = Provincia::find()->all();
        foreach ($candidatas as $provincia) {
            if ($this->normalizeNombreProvincia((string) $provincia->nombre) === $nombre) {
                return $provincia;
            }
        }
        foreach ($candidatas as $provincia) {
            $pNombre = $this->normalizeNombreProvincia((string) $provincia->nombre);
            if (str_contains($pNombre, $nombre) || str_contains($nombre, $pNombre)) {
                return $provincia;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $renaper
     */
    private function resolveLocalidad(array $renaper, Provincia $provincia): ?Localidad
    {
        $localidadNombre = trim((string) (
            $renaper['localidad']
            ?? $renaper['ciudad']
            ?? $renaper['municipio']
            ?? ''
        ));
        if ($localidadNombre !== '') {
            $localidad = Localidad::find()
                ->alias('l')
                ->innerJoin(['d' => Departamento::tableName()], 'd.id_departamento = l.id_departamento')
                ->where(['d.id_provincia' => (int) $provincia->id_provincia])
                ->andWhere(['like', 'l.nombre', $localidadNombre, false])
                ->one();
            if ($localidad instanceof Localidad) {
                return $localidad;
            }
        }

        return Localidad::find()
            ->alias('l')
            ->innerJoin(['d' => Departamento::tableName()], 'd.id_departamento = l.id_departamento')
            ->where(['d.id_provincia' => (int) $provincia->id_provincia])
            ->orderBy(['l.id_localidad' => SORT_ASC])
            ->one();
    }

    private function normalizeNombreProvincia(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'N'],
            $value
        );
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}

<?php

namespace common\components\Domain\Integrations\Mpi;

use common\models\Person\Persona;
use Yii;

/**
 * Consulta RENAPER vía gateway MPI (sin empadronamiento ni sincronización MPI).
 */
final class RenaperGatewayService
{
    /**
     * @return array<string, mixed>|null Fila RENAPER normalizada
     */
    public function fetchByPersona(Persona $persona): ?array
    {
        $documento = trim((string) $persona->documento);
        if ($documento === '') {
            return null;
        }

        $sexo = $this->resolveSexoQueryParam($persona);

        return $this->fetch($documento, $sexo);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(string $documento, string $sexo): ?array
    {
        if (!MpiCapability::isEnabled(MpiCapability::RENAPER)) {
            return null;
        }
        if (!Yii::$app->has('mpi')) {
            return null;
        }

        $sexo = trim($sexo);
        if ($sexo === '') {
            return null;
        }

        try {
            /** @var MpiApiClient $mpi */
            $mpi = Yii::$app->mpi;
            $respuesta = $mpi->call(
                'renaper?dni=' . rawurlencode($documento) . '&sexo=' . rawurlencode($sexo),
                '{}'
            );
            if (!is_array($respuesta)) {
                return null;
            }
            $row = $respuesta['data'][0] ?? $respuesta['data'] ?? null;
            if (!is_array($row) || empty($row['apellido'])) {
                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            Yii::error('RENAPER: ' . $e->getMessage(), 'renaper');

            return null;
        }
    }

    private function resolveSexoQueryParam(Persona $persona): string
    {
        $letra = $persona->getSexoLetra();
        if ($letra === 'M' || $letra === 'F') {
            return $letra;
        }

        $sb = (int) $persona->sexo_biologico;
        if ($sb === 1) {
            return 'F';
        }
        if ($sb === 2) {
            return 'M';
        }
        $sexo = strtoupper(trim((string) ($persona->sexo ?? '')));
        if ($sexo === 'M' || $sexo === 'F') {
            return $sexo;
        }

        return '';
    }
}

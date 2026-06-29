<?php

namespace common\components\Domain\Integrations\Mpi;

use common\models\Person\Persona;
use Yii;

/**
 * Consulta domicilio declarado vía endpoint MPI (no renaper?).
 */
final class MpiDomicilioGatewayService
{
    /**
     * @return array<string, mixed>|null Fila de domicilio normalizada
     */
    public function fetchByPersona(Persona $persona): ?array
    {
        $documento = trim((string) $persona->documento);
        if ($documento === '') {
            return null;
        }

        $sexo = $this->resolveSexoQueryParam($persona);
        if ($sexo === '') {
            return null;
        }

        return $this->fetch($documento, $sexo);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(string $documento, string $sexo): ?array
    {
        if (!MpiCapability::isEnabled(MpiCapability::DOMICILIO)) {
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

            return $mpi->getDomicilio($documento, $sexo);
        } catch (\Throwable $e) {
            Yii::error('MPI domicilio gateway: ' . $e->getMessage(), 'mpi');

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

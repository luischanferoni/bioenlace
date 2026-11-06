<?php

namespace common\components\Domain\Person\Identity\Infrastructure\External\Puco;

use common\components\Domain\Person\Identity\Infrastructure\External\Mpi\MpiApiClient;
use common\components\Domain\Person\Identity\Infrastructure\External\Mpi\MpiSeipaDni;

/**
 * Coberturas de P.U.C.O. (SEIPA) para el alta de personas.
 *
 * Reusa el ACL autenticado de SEIPA ({@see MpiApiClient}) — es el mismo endpoint que ya
 * consulta la vista de PUCO, así no hay una segunda puerta al mismo sistema.
 *
 * Contrato:
 * - `lookup()` devuelve el primer registro, o `null` si la persona no tiene cobertura o el
 *   documento no es consultable (SEIPA tipa `dni` como entero).
 * - Falla del proveedor: `PucoUnavailableException`.
 */
final class PucoApiClient
{
    public const LOG_CATEGORY = 'puco';

    private readonly MpiApiClient $mpi;

    public function __construct(?MpiApiClient $mpi = null)
    {
        $this->mpi = $mpi ?? new MpiApiClient();
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws PucoUnavailableException
     */
    public function lookup(string $documento, int $sexo = 1): ?array
    {
        $dni = MpiSeipaDni::toLongQueryParam($documento);
        if ($dni === null) {
            return null;
        }

        $respuesta = $this->mpi->call(
            'coberturas?dni=' . rawurlencode($dni) . '&sexo=' . rawurlencode((string) $sexo)
        );

        if (!is_array($respuesta)) {
            throw new PucoUnavailableException('P.U.C.O.: sin respuesta del servicio provincial.');
        }

        $statusCode = (int) ($respuesta['statusCode'] ?? 200);
        if (($respuesta['successful'] ?? true) === false || $statusCode >= 400) {
            throw new PucoUnavailableException(
                'P.U.C.O. respondió ' . $statusCode . ': ' . (string) ($respuesta['message'] ?? 'error')
            );
        }

        $rows = $respuesta['data'] ?? null;
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $first = $rows[0] ?? null;

        return is_array($first) ? $first : null;
    }
}

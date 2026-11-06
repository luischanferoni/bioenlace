<?php

namespace common\components\Domain\Person\Identity\Infrastructure\External\Puco;

/**
 * La consulta a P.U.C.O. no se pudo completar (servicio caído o respuesta inválida).
 *
 * El llamador decide qué mostrar; `null` de {@see PucoApiClient::lookup()} significa
 * "consultado y sin cobertura", no un error.
 */
class PucoUnavailableException extends \RuntimeException
{
}

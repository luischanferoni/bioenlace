<?php

namespace common\components\Domain\Clinical\Capture\Infrastructure\CareRequest;

use common\components\Domain\Clinical\Capture\Domain\Port\DerivacionRowSupportPort;
use common\components\Domain\Clinical\CareRequest\Application\Service\CareRequestActCodingService;
use common\components\Domain\Clinical\CareRequest\Application\Service\CareRequestMetadata;
use common\components\Domain\Clinical\CareRequest\Application\Service\CareRequestService;
use common\components\Domain\Clinical\CareRequest\Domain\Model\CareRequest;
use common\components\Domain\Clinical\CareRequest\Domain\CareRequestActCoderInterface;
use common\models\Organization\Servicio;

/**
 * Adapter: Servicio AR + CareRequest Application para derivación en captura.
 */
final class YiiDerivacionRowSupportAdapter implements DerivacionRowSupportPort
{
    private CareRequestService $pedidos;

    private CareRequestActCoderInterface $actoCoder;

    public function __construct(
        ?CareRequestService $pedidos = null,
        ?CareRequestActCoderInterface $actoCoder = null
    ) {
        $this->pedidos = $pedidos ?? new CareRequestService();
        $this->actoCoder = $actoCoder ?? CareRequestActCodingService::defaultService();
    }

    public function resolveLineaIdByName(string $servicioNombre): ?int
    {
        $resolved = Servicio::findByName($servicioNombre);

        return $resolved !== null && $resolved > 0 ? (int) $resolved : null;
    }

    public function resolveLineaNameById(int $idServicio): ?string
    {
        $named = Servicio::findOne(['id_servicio' => $idServicio]);

        return $named !== null ? (string) $named->nombre : null;
    }

    public function resolveLineaBySpecialtyNl(string $nl): ?array
    {
        $tipologia = CareRequestMetadata::resolveLineaSpecialtyFromNl($nl);
        if ($tipologia === null) {
            return null;
        }
        $unique = Servicio::findUniqueBySpecialtyCoding(
            $tipologia['specialty_code'],
            $tipologia['specialty_system']
        );
        if ($unique === null) {
            return null;
        }

        return [
            'id' => (int) $unique->id_servicio,
            'nombre' => (string) $unique->nombre,
        ];
    }

    public function codeActo(string $display, string $modo): array
    {
        return $this->actoCoder->code($display, $modo);
    }

    public function resolvePedidoCompleteness(
        ?int $lineaId,
        ?string $actoCode,
        ?string $actoSystem,
        string $modo,
        ?string $indicaciones,
        ?int $efectorId,
        ?string $actoDisplay
    ): array {
        $pedido = new CareRequest(
            $lineaId,
            $actoCode,
            $actoSystem,
            $modo,
            $indicaciones,
            $efectorId,
            $actoDisplay
        );
        $resolved = $this->pedidos->resolve($pedido);

        return [
            'complete' => $resolved['complete'] === true,
            'missing' => $resolved['missing'],
            'lineas' => $resolved['candidates']['lineas'] ?? [],
            'actos' => $resolved['candidates']['actos'] ?? [],
        ];
    }

    public function servicioOptions(): array
    {
        $out = [];
        foreach (Servicio::getServiciosConTurnos() as $s) {
            if (!$s instanceof Servicio || !$s->esOfertaAsistencial()) {
                continue;
            }
            $id = (int) ($s->id_servicio ?? 0);
            $nombre = trim((string) ($s->nombre ?? ''));
            if ($id <= 0 || $nombre === '') {
                continue;
            }
            $out[] = ['value' => $id, 'label' => $nombre];
        }

        return $out;
    }
}

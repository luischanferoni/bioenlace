# Fase 1 — `Clinical/Access` → `Clinical/PedidoAtencion/Service`

## Por qué

`Access/` era un rol técnico opaco; el bounded context real es **PedidoAtencion** (línea × acto, coding, capacity ECL).

## Destino

```text
Domain/Clinical/PedidoAtencion/Service/*.php
namespace common\components\Domain\Clinical\PedidoAtencion\Service
```

## Checklist

- [x] Mover 16 clases; actualizar `namespace` y `use`
- [ ] Tests unitarios PedidoAtencion* (correr en entorno local)
- [x] Docs: `decisions/pedido-atencion-linea-acto.md`, Clinical README
- [x] Sin alias en `Clinical/Access/`

## Criterio de hecho

`rg Clinical\\\\Access` → 0 en `web/` — cumplido.

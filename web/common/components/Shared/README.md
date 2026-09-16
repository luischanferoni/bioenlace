# `Shared/` — infra y tipos transversales

Namespace base: `common\components\Shared\…`

Hermana de `Domain/` y `Platform/`. **No** es un bounded context ni un motor.

| Carpeta | Contenido |
|---------|-----------|
| **`Infrastructure/`** | Utilidades técnicas compartidas (log resiliente, helpers de migración, deduplicación de requests, bases HTTP/messaging cuando existan) |
| **`Domain/`** | Tipos/value objects transversales mínimos — **sin** reglas clínicas ni catálogos SNOMED |

## Qué no va aquí

- Conectores LIS / receta / HC / MPI / agenda → `Domain/<BC|Modulo>/Infrastructure/External/`
- Motores del asistente, IA, UI JSON → `../Platform/`
- Negocio clínico, turnos, personas → `../Domain/…`

## Referencias

- ADR: [shared-top-level-infrastructure.md](../../../docs/decisions/shared-top-level-infrastructure.md)
- [../README.md](../README.md)

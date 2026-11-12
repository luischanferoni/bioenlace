# Fase 01 — Servicio y consultas

## Objetivo

Implementar el servicio que compone las secciones del panel y las consultas de lectura, sin tocar vistas.

## Diseño

- `EfectorPanelService` recibe `id_efector`, período y clase opcional; devuelve un arreglo por sección.
- Cada sección tiene su método privado de consulta y un contrato de salida estable.
- Reusa `EfectorEncounterEntitlementService`, `EfectorResumenMetricsService`, `EmergencyIndicatorsService` e `InpatientIndicatorsService`; no duplica sus cálculos.
- Los precios y COGS salen del catálogo; el servicio no contiene valores en USD.
- Si una sección falla, devuelve el error acotado y las demás siguen.

## Consultas mínimas

| Sección | Consulta |
|---------|----------|
| Ficha | `Efector::findOne` + resumen existente |
| Contrato | `contractSummary` + cuenta de billing |
| Uso | Turnos por `id_efector` y rango; atenciones unidas por PES |
| Costos | COGS de catálogo × volumen; consumo medido de las tablas de canal e IA |
| Operación | Resúmenes de guardia e internación |
| Identidad | Conteos por estado de vínculo y por método/resultado de verificación |
| Staff | Conteo de PES por servicio |

## Tests

- Unitarios del servicio con datos de prueba: período, clase, efector sin contrato, efector sin datos.
- Un test por sección que verifique que no se devuelven datos personales.
- Test de que el servicio no hace consultas de escritura.

## Tareas

- [ ] Crear `EfectorPanelService` y el contrato de salida.
- [ ] Implementar las consultas por sección.
- [ ] Resolver el alcance del usuario antes de consultar.
- [ ] Cubrir con tests los casos borde.

## Criterio de salida

El servicio devuelve todas las secciones del MVP para un efector y período, con tests en verde y sin dependencia de la vista.

# Fase 00 — Métricas y fuentes

## Objetivo

Cerrar el inventario de datos del panel y decidir qué entra en el MVP, qué requiere persistencia nueva y qué se descarta.

## Entregable

Una tabla por sección con: métrica, definición, fuente, estado y dueño. El estado es «existe», «existe pero no por efector» o «falta».

## Inventario inicial

| Sección | Métrica | Fuente | Estado |
|---------|---------|--------|--------|
| Ficha | Profesionales activos, servicios | `EfectorResumenMetricsService` | existe |
| Contrato | Clases, tope y usado de PES, dictado, videollamada, WhatsApp | `EfectorEncounterEntitlementService::contractSummary` | existe |
| Contrato | Baja pendiente y fecha efectiva | `BillingAccountEncounterEntitlement` | existe |
| Uso | Turnos del período | `Turno` | existe |
| Uso | Atenciones por clase | `Encounter` + `ProfesionalEfectorServicio` | existe, sin `id_efector` directo |
| Uso | Cancelaciones y ausencias | `Turno` y auditoría de resolución | a confirmar |
| Costos | COGS por atención según catálogo | `PricingPesByEncounterClassCatalog` | existe |
| Costos | IA por efector | `AICostTracker` | falta persistencia |
| Costos | WhatsApp: mensajes y `billable` | `AsistenteWhatsappMensajeEstado` | existe, sin `id_efector` |
| Costos | Didit: verificaciones y resultado | `AsistenteWhatsappVerificacion` | existe, con `id_persona` |
| Costos | Videollamada: minutos o sesiones | — | falta definir fuente |
| Operación | Guardia: activos, sin triage, SLA | `EmergencyIndicatorsService` | existe en vivo; histórico a confirmar |
| Operación | Internación: camas, estada | `InpatientIndicatorsService` | existe en vivo; histórico a confirmar |
| Identidad | Vínculos activos, pendientes y bloqueados | `AsistenteWhatsappVinculo` | existe |
| Staff | PES por servicio y roles de gestión | `ProfesionalEfectorServicio`, RBAC | existe |

## Decisiones de la fase

1. Confirmar si «uso» se mide por turno, por atención o por ambos; el panel no debe mezclar unidades.
2. Confirmar la fuente de videollamada. Si no existe, el MVP muestra «sin datos» y no estima.
3. Confirmar si los indicadores de guardia e internación se muestran en vivo o por período. Si es por período, se extienden las consultas.
4. Decidir el umbral de supresión de celdas pequeñas.
5. Confirmar el alcance de admin: superadmin y administrador con alcance de efector; AdminEfector queda fuera.

## Tareas

- [ ] Relevar cada métrica contra la base y el código; marcar las que no tienen fuente.
- [ ] Definir la unidad de cada sección y el período por defecto.
- [ ] Acordar el MVP con producto y operación.
- [ ] Registrar las brechas que pasan a la fase 03.

## Criterio de salida

No hay métricas del MVP sin fuente definida; las que faltan están asignadas a una fase con dueño.

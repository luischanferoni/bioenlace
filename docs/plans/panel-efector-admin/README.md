# Plan — Panel de efector en admin

| Campo | Valor |
|-------|--------|
| Slug | `panel-efector-admin` |
| Estado | Diseño |
| Dueño | Producto / Operación / Billing |
| Relación | Complementa [paneles-web-por-rol](../paneles-web-por-rol/README.md): ese plan cubre el panel de inicio del staff; este cubre el backoffice de administración por efector. |

## Contexto

Hoy el admin tiene la ficha del efector (`EfectoresController::actionView`) y el resumen de licencia (`actionLicencia`), pero la información está repartida: el contrato en `BillingAccountController`, los costos de IA en `CostosController` (solo superadmin y sin desagregado por efector) y el consumo de WhatsApp en tablas propias del canal. No hay una vista única que responda «qué contrató este efector, cuánto usa y cuánto cuesta».

## Alcance

- Vista **de solo lectura** por efector, en admin, con ficha, contrato, uso, costos, operación e identidad.
- Sin datos personales: solo agregados y conteos.
- Sin edición: los cambios siguen en las pantallas existentes de efectores, billing y licencia.
- Un período seleccionable (mes actual, últimos tres meses o rango).
- Navegación desde la ficha y la licencia del efector.

## Índice

- [overview.md](overview.md) — preguntas que responde, secciones y permisos
- [design.md](design.md) — arquitectura, fuentes de datos, costos y privacidad
- [phases/00-metricas-y-fuentes.md](phases/00-metricas-y-fuentes.md) — inventario de métricas y brechas
- [phases/01-servicio-y-consultas.md](phases/01-servicio-y-consultas.md) — servicio de resumen y consultas
- [phases/02-vista-admin.md](phases/02-vista-admin.md) — controlador, vista y navegación
- [phases/03-costos-ia-y-whatsapp.md](phases/03-costos-ia-y-whatsapp.md) — persistencia de uso de IA y costos del canal
- [phases/04-qa-y-cierre.md](phases/04-qa-y-cierre.md) — verificación y cierre

## Dependencias

- Contrato y entitlement: `EfectorEncounterEntitlementService` y `BillingAccountEncounterEntitlement`.
- Métricas existentes: `EfectorResumenMetricsService`, `EmergencyIndicatorsService`, `InpatientIndicatorsService`.
- Atenciones: `Encounter` (clase `encounter_class`, vínculo por `ProfesionalEfectorServicio`) y `Turno`.
- Costos: `PricingPesByEncounterClassCatalog` como fuente de precios y COGS.
- WhatsApp: `AsistenteWhatsappMensajeEstado`, `AsistenteWhatsappVinculo` y `AsistenteWhatsappVerificacion`.
- IA: `AICostTracker`, que hoy acumula en memoria y log, sin histórico por efector.
- Acceso: `BioenlaceAdminAccessControl` y el patrón de `EfectoresController`.

## Criterio de cierre

El admin puede abrir un efector y ver, en una sola pantalla y para un período, su ficha, contrato vigente, uso, costos estimados y operación, sin datos personales ni consultas manuales a la base. Los costos de IA y WhatsApp salen de datos persistidos, no de logs. La vista no duplica cálculos de precios: consume el catálogo y los servicios de dominio.

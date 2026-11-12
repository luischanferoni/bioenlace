# Overview — Panel de efector en admin

## Objetivo

Dar al equipo de operación y a administración una vista única del efector: qué contrató, cuánto usa, cuánto cuesta y cómo está operando. Hoy esa información se arma a mano entre billing, la ficha del efector y las tablas del canal.

## Preguntas que responde

- ¿Qué clases de atención tiene contratadas y con qué límite de PES?
- ¿Qué add-ons paga: videollamada, WhatsApp?
- ¿Cuántas atenciones y turnos tuvo en el período?
- ¿Cuánto costó la IA, WhatsApp, Didit y videollamada?
- ¿Cómo está la operación de guardia e internación?
- ¿Cómo está la identidad del canal WhatsApp: vínculos activos, verificaciones y fallos?
- ¿Quiénes operan el efector: profesionales activos, servicios y roles de gestión?

## Secciones del MVP

| Sección | Contenido | Fuente principal |
|---------|-----------|------------------|
| Ficha | Nombre, CUIT, dirección, estado, profesionales activos, servicios | `Efector`, `EfectorResumenMetricsService` |
| Contrato | Cuenta de billing, rol de membresía, clases, PES usados y tope, add-ons, baja pendiente | `EfectorEncounterEntitlementService`, `BillingAccountEncounterEntitlement` |
| Uso | Turnos y atenciones del período por clase, cancelaciones y ausencias | `Turno`, `Encounter` |
| Costos | IA, WhatsApp, Didit, videollamada y total estimado | Catálogo de precios + tablas de consumo |
| Operación | Guardia: activos, sin triage, SLA; internación: camas y días de estada | `EmergencyIndicatorsService`, `InpatientIndicatorsService` |
| Identidad y WhatsApp | Vínculos activos, pendientes y bloqueados; verificaciones por método y resultado; mensajes y costo Meta | `AsistenteWhatsappVinculo`, `AsistenteWhatsappVerificacion`, `AsistenteWhatsappMensajeEstado` |
| Staff y accesos | Conteo de profesionales, PES por servicio y roles de gestión | `ProfesionalEfectorServicio`, RBAC |

## Filtros

- **Período:** mes actual, últimos tres meses o rango explícito. Por defecto, mes actual.
- **Clase de atención:** todas, AMB, EMER o IMP. Aplica a uso y costos.
- **Moneda:** USD para costos de infraestructura y proveedores; ARS solo si existe una cotización explícita. No convertir implícitamente.

## Navegación

- Desde `EfectoresController::actionView` y `actionLicencia`, con un botón «Panel del efector».
- URL de admin con el `id_efector` como parámetro.
- La vista no reemplaza las pantallas de edición: los enlaces llevan a billing, licencia, profesionales y servicios.

## Permisos

- **Superadmin:** todos los efectores.
- **Administrador con alcance de efector:** solo los efectores que su rol habilita. El alcance se resuelve con el RBAC existente, no con una lista en la vista.
- **AdminEfector:** fuera del MVP. Su panel es el de inicio del staff, no el backoffice. Si se habilita, será una fase posterior con permisos de lectura acotados.

## Fuera de alcance

- Edición de contrato, entitlement o add-ons.
- Facturación, cobros o conciliación con ARCA.
- Datos personales de pacientes o profesionales.
- Exportación a planillas (fase posterior).
- Panel para el paciente.

## Métricas y definiciones

- **Atención:** `Encounter` del período, contada una vez por `id` y asignada al efector por `ProfesionalEfectorServicio`.
- **Turno:** fila de `Turno` del período, asignada por `id_efector`.
- **Costo estimado:** COGS de catálogo por atención/turno más consumo medido del período. Se muestra separado de lo facturado.
- **Vínculo activo:** `AsistenteWhatsappVinculo` con estado `ACTIVO` y verificación vigente.
- **Verificación:** fila de `AsistenteWhatsappVerificacion` con método y resultado.
- **Mensaje WhatsApp:** fila de `AsistenteWhatsappMensajeEstado`; `billable` indica si Meta lo cobra.

## Alternativa descartada

Un tablero nuevo en el frontend del staff. El panel es de administración y debe vivir en admin, con los permisos y el layout existentes. El panel de inicio del staff ya está cubierto por [paneles-web-por-rol](../paneles-web-por-rol/README.md).

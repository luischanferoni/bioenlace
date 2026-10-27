# Fase 04 — Secciones por rol y encounter

## Objetivo

Que cada rol vea, en cada encounter, lo que necesita para operar. MVP: se acepta panel sin información y se reutiliza lo existente antes de crear providers.

## Definiciones acordadas (producto)

| Rol | AMB | EMER | IMP | VR |
|-----|-----|------|-----|----|
| Enfermería | Listado de pacientes para cargar | Sin definir | Listado de internados con cama + indicaciones/rondas | Sin definir |
| Administrativo | Administra la cola: cuántos en espera, si el médico admite más | Administra la cola de guardia | Sin definir | Sin definir |
| AdminEfector | Sólo KPIs | Sólo KPIs | Sólo KPIs | Sólo KPIs |
| AdminMinisterio | Sólo KPIs | Sólo KPIs | Sólo KPIs | Sólo KPIs |

## Qué se reutiliza y qué falta

| Necesidad | Estado en el código | Acción |
|-----------|---------------------|--------|
| Enfermería IMP: pacientes internados con cama | `panels.staff.IMP.imp_floor` ya incluye `inpatients`, y `InfraestructuraPiso::getInternadosPorEfector()` devuelve `cama`, `sala`, `piso` | Reusar. **Ojo**: `InpatientsSectionProvider` exige horario activo del profesional en IMP; una enfermera sin agenda vería `requires_cobertura` en vez del listado. Revisar si ese gate aplica a enfermería |
| Enfermería IMP: indicaciones y rondas | No existe planificación de rondas; enfermería sólo tiene modelos de captura (`EncounterNursingCare`) | Fuera de v1 |
| Administrativo EMER: cola de guardia | `panels.staff.EMER` ya incluye `emergency_board`, con cola activa y capacidades (`puede_triage`, `puede_ingresar`, …) | Reusar. Mismo **ojo** con el gate de horario activo EMER |
| Enfermería AMB / Administrativo AMB: listado del servicio | `appointments_day` es la agenda **del profesional** (valida que el PES sea del usuario) | Único dato faltante del MVP: listado de turnos del **servicio** en sesión |
| Administrativo AMB: «si el médico admite más» | No hay métrica única (¿cupo de licencia, disponibilidad de agenda?) | Definir con producto antes de codificar |
| AdminEfector: KPIs | `staff_efector_resumen` (profesionales activos, servicios) entregado | Sumar KPIs cuando producto los pida |
| AdminMinisterio: KPIs | No hay métrica definida | Definir con producto |

## Reglas

- Una sección puede devolver vacío; el panel no se rompe y el cliente no la muestra.
- Se reutiliza antes de crear. Un overlay que sólo cambia el título no necesita provider.
- Provider nuevo sólo cuando el recorte (rol/servicio) o el dato no existen, y siempre con su test.

## Criterio de salida

- Cada rol y encounter de la matriz tiene una decisión: sección reutilizada, sección nueva o «sin overlay en v1» con motivo escrito.
- Los providers nuevos están registrados en `product-registries.php` y tienen test.

## Estado

En curso.

Entregado:

1. **Resumen del efector** (`staff_efector_resumen`, AdminEfector) — KPIs de profesionales activos y servicios, vía `EfectorResumenMetricsService`.
2. **Cola del servicio en AMB** (`service_pending_appointments`, enfermería y administrativo) — `StaffSchedulingDayListService::turnosPendientesDelServicio()`: turnos pendientes del día del efector+servicio en sesión, con `kind: appointments_day` para que web y app lo rendericen sin cambios. Se aplica por overlay de rol (`staff_by_role.enfermeria.AMB` y `staff_by_role.administrativo.AMB`).
3. **Indicadores de red** (`red_solicitudes`, AdminMinisterio / nivelCentral) — solicitudes de alta pendientes (`BillingSignupRequest::STATUS_PENDING`), agregado a `staff_operations` por overlay.
4. **Candado de horario por rol**: `roles.yaml` declara `requiere_horario_activo`; administración no lo exige para ver la cola de guardia (`EmergencyBoardSectionProvider`), enfermería y médico sí.
5. **Paneles de gestión independientes del encounter**: `staff_by_role.admin_efector.""` y los roles de red declaran `layout: staff_dashboard` con sus KPIs. Como la clave es `""`, el overlay gana aunque la sesión conserve un encounter class al cambiar de servicio (era el caso real: cambiar a «ADMINISTRADOR DE EFECTOR» seguía mostrando el panel AMB).
6. **Etiqueta del menú lateral**: `OperationalRoleManifest::homeNavLabel()` lee `home_nav_label` de `roles.yaml`; `MainLayoutPageBuilder` la expone y `main.php` ya no dice «Pacientes» fijo.

Tests: `StaffEfectorResumenSectionProviderTest`, `StaffRedSolicitudesSectionProviderTest`, `HomePanelManifestTest` (overlays), `OperationalRoleManifestTest` (flags de rol).

Pendiente (bloqueado por definición de producto):

- Administrativo AMB: «si el médico admite más pacientes» — se dejó fuera de v1.
- Enfermería EMER y administrativo IMP: sin overlay propio en v1 (el panel por encounter ya muestra el tablero/lista clínica).
- Enfermería IMP: rondas e indicaciones (no hay planificación de rondas en el modelo).
- AdminMinisterio: sumar más KPIs cuando producto los defina (efectores de la cuenta, vencimientos).

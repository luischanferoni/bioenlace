# Fase 04 — Secciones por dominio

## Objetivo

Construir las secciones nuevas con datos reales, una por una, cada una en su dominio y con su test.

## Reglas

- Ubicación: `common/components/Domain/<BoundedContext>/Home/Sections/` (patrón dominante: `Domain/Clinical/Home/Sections`, `Domain/Scheduling/Home/Sections`). `Platform/Ui/Home/Service/Sections/` queda sólo para secciones genéricas de plataforma.
- Registro obligatorio en `homePanelSectionProviders` de `common/config/product-registries.php`. Sin registro, el provider no existe.
- Ningún provider nuevo expone datos que el rol no puede ver: la autorización se resuelve con capabilities/dominio, no con el rol del panel.
- Primero verificar la fuente de datos; si no hay query/use case, la sección no se promete en el manifest.

## Orden propuesto (menor a mayor riesgo)

| # | Sección | Rol | Fuente a verificar antes de empezar |
|---|---------|-----|--------------------------------------|
| 1 | `staff_internacion_kpis` / `inpatients` reutilizados en overlay de enfermería | enfermeria | Ya existen |
| 2 | Signos vitales pendientes | enfermeria | Existe `EncounterNursingCare`; falta definir «pendiente» |
| 3 | Turnos del día / pendientes de confirmación | administrativo | `appointments_day` + estado de turno |
| 4 | Admisiones pendientes | administrativo | Cola de guardia (`GuardiaEpisode.view_board`) o registro |
| 5 | Resumen del efector | admin_efector | Presenters existentes (p. ej. `ProfesionalesConteoInfoPresenter`) + `ActionCardsSectionProvider` |
| 6 | Profesionales activos | admin_efector | `RbacRoleQueryService::listPesRolesByEfectorForUser` / PES |
| 7 | Supervisión de red ministerial | admin_ministerio | `BillingAccount` (tipo ministerio) y efectores asociados |

Antes de escribir un provider nuevo, evaluar si alcanza con `action_cards` + `HomeUiActionCatalog` (secciones por acciones disponibles, sin dato agregado nuevo).

## Criterio de salida

Por cada sección: provider registrado, test de forma mínima (`id`, `kind`, `data`), y entrada en el manifest del rol correspondiente. Documentar en el design la definición de cada métrica («pendiente», «activo»).

## Estado

En curso. Primera sección entregada: **resumen del efector** (`staff_efector_resumen`, AdminEfector).

- `EfectorResumenMetricsService` (`Organization/Efector/Application/Service`): profesionales activos (PES vigentes) y servicios del efector. Devuelve `[]` si el efector no existe, sin excepciones con texto.
- `StaffEfectorResumenSectionProvider` (`Organization/Efector/Application/Presentation`, porque `Organization` no admite `Home/` como plugin en el shape test): se autolimita con `usuarioEsAdminEfectorOperativo()` y devuelve sección vacía para el resto de los roles.
- Registrado en `homePanelSectionProviders` y agregado a `panels.staff_operations` con `kind: staff_kpi_group`, que la web (`pacientes-listado.js`) y la app (`home_panel_kpi_widgets.dart`) ya renderizan sin cambios.
- Test: `StaffEfectorResumenSectionProviderTest` (guest con y sin `id_efector`, efector inválido en el servicio).

Pendiente de la fase: admisiones pendientes, signos vitales pendientes y supervisión de red; cada una necesita definición funcional de «pendiente» (ver [design.md](../design.md#pendientes-de-decidir)).

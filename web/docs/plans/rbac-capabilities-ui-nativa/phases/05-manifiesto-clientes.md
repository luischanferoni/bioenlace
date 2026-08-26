# Fase 5 — Manifiesto panel y clientes web/móvil

## Objetivo

Una sola fuente para «quién ve qué CTA» alineada con RBAC; reducir lógica duplicada por rol en Dart/JS.

## Tareas

### 5.1 `home-panel-manifest.yaml`

- [ ] Reemplazar listas sueltas (`triage_roles`, `ingreso_roles`, …) por referencias:
  ```yaml
  capabilities:
    triage: guardia.triage
    ingreso: guardia.ingreso
    atender: guardia.atender
    documentar: encounter.documentar_nota
  atender_exclude_roles: [Administrativo, AdminEfector]
  ```
- [ ] `GuardiaBoardCapabilityService`: resolver `puede_*` vía `CapabilityAccessService` + exclude del manifiesto.
- [ ] `HomePanelService` / providers: incluir en respuesta `capabilities` resueltas (opcional, para clientes).

### 5.2 `/api/home/panel`

- [ ] Sacar `/api/home/panel` de `$authenticatedOnlyRoutes` cuando hay secciones clínicas.
- [ ] Exigir `panel.staff_clinical` o capabilities por sección solicitada en query `sections=`.
- [ ] Paciente / panel sin EMER: mantener auth-only o capability paciente separada.

### 5.3 Web

- [ ] `pacientes-listado.js`: usar flags `puede_*` del API; eliminar checks de rol hardcodeados nuevos (retiro, ingreso).
- [ ] Modal admisión: no asumir permiso si el botón visible (confiar en panel + manejo 403).

### 5.4 Móvil (`personalsalud`)

- [ ] `home_screen.dart`, `emergency_guardia_actions.dart`: flags del panel primero.
- [ ] Reducir `User.hasRole` local salvo offline UX.
- [ ] Opcional: constantes capability en shared package para headers debug.

## Criterios de aceptación

- [ ] Cambiar `default_roles` en YAML + sync actualiza CTAs sin editar Dart/JS.
- [ ] Panel EMER devuelve 403 para rol sin `guardia.view_board` (no solo lista vacía opaca).

## PR sugerido

`feat(ui): panel capabilities drive guardia CTAs web and mobile`

## Dependencias

- Fases 2–4 desplegadas en staging.

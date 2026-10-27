# Overview — Paneles web personalizados por rol

## Objetivo

Que el panel de inicio web y el top menu se compongan según el rol operativo del usuario (médico, enfermería, administrativo, admin de efector, admin de red/ministerio), manteniendo el menú lateral simple (Inicio + Asistente).

## Situación actual (verificada en código)

- **Resolución del panel**: `HomePanelManifest::resolveForStaff()` resuelve **sólo** por `encounter_class` (EMER, AMB, VR, IMP con slices `imp_surgical`/`imp_floor`); sin encounter va a `panels.staff_operations`. No hay eje de rol.
- **Audiencia**: `HomePanelAudienceResolver` devuelve `staff` / `patient` / `fallback`. `audience.staff_roles` incluye `Medico`, `enfermeria`, `Administrativo`, `AdminEfector`, `facturista`, `nivelCentral`; **no** incluye `AdminMinisterio`.
- **Roles**: el usuario elige el servicio al iniciar sesión (ese servicio trae el rol en `servicios.item_name`) y puede cambiarlo desde el top menu; el encounter type se elige igual y también cambia desde el top menu. Ambos son estado de sesión, no atributos fijos de la cuenta. Ya hay piezas reutilizables (`RbacRoleQueryService`, `EstablishOperativeSession`).
- **Sesión sin encounter**: para el servicio `AdminEfector` la sesión operativa se considera completa sin encounter class. Los roles de gestión nunca resuelven por encounter.
- **Top menu**: `main.php` muestra Efector → Servicio → Encounter Type, con supresión por metadata (`ClientContextMetadata::shouldHideServicioInNavbar()` sobre `client-context.yaml`), que hoy sólo aplica a `Administrativo`.
- **Contrato compartido**: `home-panel-manifest.yaml` lo consume también `GET /api/v1/home/panel` en las dos apps móviles.
- **RBAC**: la capability `panel.staff_clinical` cubre `/api/home/panel` para los seis roles del manifest; sin `AdminMinisterio`.

## Necesidad

- Un home por rol, con secciones relevantes y fuente de datos definida.
- Un top menu que no ofrezca Servicio/Encounter Type a quien no los usa.
- Sin `if` por nombre de rol en vistas ni page builders.
- Sin romper las apps móviles ni los paneles actuales.

## Alcance

| Rol | Panel | Top menu |
|-----|-------|----------|
| Médico | Por encounter (sin cambios) | Efector → Servicio → Encounter |
| Enfermería | Por encounter + overlay (internación, signos vitales) | Efector → Servicio → Encounter |
| Administrativo | Por encounter + overlay (gestión de turnos/admisión) | Efector → Servicio → Encounter |
| AdminEfector | Por encounter + overlay de gestión del efector | Efector (a definir si además Servicio/Encounter) |
| AdminMinisterio / nivelCentral | Por encounter + overlay de red ministerial | Sólo Efector |

Todo rol de personal de salud se cruza con encounter class; sólo `paciente` no lo está. El usuario elige servicio (rol) y encounter al iniciar sesión, y puede cambiarlos desde el top menu. Hoy AdminEfector saltea la elección de encounter (`EstablishOperativeSession` la omite para ese servicio): la fase 03 la habilita como para el resto.

La matriz completa, con el inventario de los 14 roles RBAC y los que quedan fuera, está en [phases/00-inventario-roles.md](./phases/00-inventario-roles.md) y se cierra en [design.md](./design.md).

## No objetivos (verificables)

- No se modifica el panel de médico ni su resolución por encounter.
- No se toca el asistente.
- No se crea superficie de paciente en web.
- No se agregan roles de informes (`InformesParchesMamarios`, `ReportesLaboratorio`) ni `edicionPersonas` al panel.
- No hay requisito de retrocompatibilidad: si el contrato de `/api/v1/home/panel` cambia, se actualizan web y apps en el mismo programa.
- No se introduce lógica de rol en `frontend/views/`.

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Roles de gestión sin encounter quedan fuera del fallback | Fase 02 define la cadena de los dos ejes; fase 03 los mete en audiencia y RBAC |
| `AdminMinisterio` hoy recibe `fallback` o 403 | Fase 03 explícita, con migración de capabilities |
| Explosión combinatoria rol × encounter en el YAML | Matriz rala: cada rol declara sólo los encounter donde agrega o cambia algo; clave `""` para sesión sin encounter en transición |
| Usuarios multicrol con panel inesperado | Precedencia declarada en el manifest, testeada |
| Paneles nuevos rompen las apps móviles | Los paneles se muestran también en las apps; sin retrocompatibilidad, los clientes se actualizan en el mismo programa (fase 06) |
| Providers sin fuente de datos | Fase 04 exige verificar la fuente antes de prometer la sección |

## Fases

Ver [phases/](./phases/).

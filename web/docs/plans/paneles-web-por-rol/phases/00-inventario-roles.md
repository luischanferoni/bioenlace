# Fase 00 — Inventario de roles y matriz de paneles

## Objetivo

Cerrar la lista real de roles y decidir, para cada uno, qué panel y qué top menu le corresponde. Sin esta matriz aprobada no se escribe YAML ni providers.

## Tareas

1. Inventariar roles desde la base (`auth_item` type=1) y desde `servicios.item_name` (rol operativo del PES). Fuente usada: `u257309594_bioenlace.sql`.
2. Marcar cuáles se asignan hoy y cuáles son roles de plataforma o de informes que quedan fuera del home.
3. Llenar la matriz `rol → audiencia → panel → top menu` en [design.md](../design.md).
4. Confirmar con producto/UX el contenido de cada panel (qué secciones y con qué datos).
5. Definir el orden de precedencia para usuarios con más de un rol.

## Inventario de partida (RBAC type=1, del dump)

| Rol RBAC | Descripción | Grupo propuesto | Panel propuesto |
|----------|-------------|-----------------|-----------------|
| `Medico` | Medico | Staff clínico | Por encounter (actual) |
| `enfermeria` | Enfermería | Staff clínico | Por encounter + overlay |
| `Administrativo` | Administrativo | Staff administrativo | Por encounter + overlay |
| `facturista` | Facturista | Staff administrativo | Por encounter (sin overlay en v1) |
| `AdminEfector` | Admin Efector | Gestión de efector | Por encounter + overlay de efector (a definir si elige encounter) |
| `AdminMinisterio` | Administrador de cuenta ministerio / red pública | Gestión de red | Por encounter + overlay de red |
| `nivelCentral` | Nivel Central | Gestión de red | Por encounter + overlay de red |
| `_sin_efector_CEAMM` | CEAMM | Gestión de red | A confirmar con producto |
| `_x_efector_aditoria` | Auditoría | Auditoría (lectura) | A confirmar con producto |
| `InformesParchesMamarios` | Informes de encuestas | Informes | Fuera de alcance v1 |
| `ReportesLaboratorio` | Reportes de laboratorio | Informes | Fuera de alcance v1 |
| `edicionPersonas` | Edición de personas | Administración de datos | Fuera de alcance v1 |
| `_x_efector_admin_bioenlace` | Superadmin plataforma | Plataforma | Panel admin (`/admin`), fuera del frontend |
| `paciente` | Paciente | Paciente | App móvil, único rol sin eje encounter |

Notas verificadas en código:

- `AdminMinisterio` existe como rol pero **no** está en `audience.staff_roles` del manifest ni en `default_roles` de la capability `panel.staff_clinical`. Hoy un usuario sólo con ese rol cae en `fallback`.
- `nivelCentral` sí está en ambos lugares, pero el plan original no lo contemplaba.
- La sesión operativa se considera completa sin encounter cuando el servicio es `AdminEfector` (`EstablishOperativeSession::isSesionOperativaCompleta()`), por lo que AdminEfector nunca resuelve por `encounter_class`.

## Criterio de salida

- Tabla de [design.md](../design.md) completa y aprobada, sin filas «a confirmar».
- Roles fuera de alcance v1, con motivo.
- Lista de valores por defecto para el wizard post-login (cuando el usuario todavía no eligió servicio).

## Estado

Pendiente. Confirmado con producto: todo rol de personal de salud (AdminEfector incluido) se cruza con encounter; sólo `paciente` no lo está. Los paneles de todos los roles se muestran también en las apps y no hay requisito de retrocompatibilidad.

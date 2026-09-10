# Fase 2 — Modelos por dominio

## Objetivo

Que `common/models/` tenga un solo eje: dominio (o `Platform`). Hoy hay 143 archivos planos y 15 carpetas de ejes mezclados.

## Tareas

### 2.1 Clasificar

- [ ] Asignar dominio dueño a los 143 modelos planos (criterio: quién **persiste** la entidad)
- [ ] Repartir las carpetas que no son dominios:
  - `busquedas`, `search`, `forms`, `fullcalendar`, `file` → `Platform/` o el dominio que las usa
  - `rbac` → `Platform/Permission/`
  - `sumar` → dominio correspondiente (programa de salud) o `Platform/`
  - `DataAccess`, `Emergency` → `Platform/DataAccess`, `Clinical/Emergency` según quién es dueño
- [ ] `Integration/` → `Integrations/` (grafía única)

### 2.2 Mover

- [ ] Mover en tandas por dominio (un PR por dominio si el diff es grande)
- [ ] Actualizar `namespace` + `use` en todo el repo
- [ ] Los `*Input` de captura clínica se mueven sin tocar `rules()`
- [ ] Al mover un modelo `Consulta*` que es hijo del encounter, renombrar la clase a `Encounter*` (`design.md` §10); **no** renombrar la tabla
- [ ] No cruzarse con los archivos que está tocando el plan `captura-actor-enfermeria`

### 2.3 Dependencias cruzadas

- [ ] Listar los casos donde un dominio lee el AR de otro
- [ ] Para cada uno: dejar nota en `design.md` (aceptado) o abrir tarea para pasar por servicio
- [ ] No introducir nuevas lecturas cruzadas en esta fase

### 2.4 Tests

- [ ] Mover `tests/unit/models/` a `tests/unit/<dominio>/`
- [ ] Suite completa verde

## Fuera de esta fase

- Cambiar nombres de tabla o migraciones.
- Refactor de dependencias cruzadas (solo se inventarían).

## Criterios de aceptación

- [ ] `common/models/` sin archivos planos
- [ ] Toda carpeta de `models/` es un dominio de `components/Domain/` o `Platform`
- [ ] Suite unitaria verde sin cambios de comportamiento

## PR sugerido

`refactor(models): un eje por dominio en common/models`

# Overview

## Problema

1. `ConsultasConfiguracion` es un alias de `EncounterDefinition`. Getters `pasos_json` / `id_servicio`, menú MVC y CRUD admin con nombres viejos. No hay retrocompatibilidad: se elimina.
2. La definición del encounter se elige por **`service_id` + `encounter_class`**, no por rol RBAC. El enfermero en internación documenta **la misma nota** que el médico; lo requerido/sugerido debe variar según el **actor** (PES `servicios.item_name`) y el **CarePlan inpatient** que indicó el médico.
3. La app Personal de Salud ya es de todo el staff, pero el primer triage en móvil muestra un snack «hacelo en web».

## Fuera de alcance de este plan

- CTA de ingreso a guardia (Administrativo) en tablero — decisión de producto ya cerrada; UI aparte.
- App distinta para enfermería.
- Kardex / pestañas MVC de internación.

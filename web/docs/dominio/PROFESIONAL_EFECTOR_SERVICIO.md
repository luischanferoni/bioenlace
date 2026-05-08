# Dominio: profesional–efector–servicio (+ agenda)

Este documento describe el modelo recomendado para representar profesionales trabajando en efectores, sus servicios, condición laboral y agenda, sin romper la cadena de identidad.

## Objetivo

- Mantener **identidad global** (persona y, futuro, padrón nacional) separada del **contexto operativo** (efector + servicio).
- Asegurar que una agenda siempre cuelgue de una asignación estable (**profesional–efector–servicio**).
- Impedir estados inválidos: **no puede existir agenda si el servicio no acepta turnos**.

## Tablas nuevas (migración)

### `profesional_efector_servicio`

Asignación operacional.

- `id_persona` (**obligatorio**): identidad actual del sistema.
- `id_profesional_salud` (**nullable**): futura asociación a padrón nacional (`profesional_salud`).
- `id_efector`, `id_servicio` (**obligatorios**).
- `legacy_rrhh_servicio_id` (**nullable**): mapping desde el esquema viejo (solo migración/compat).

Unique recomendado (soft delete): `(id_persona, id_efector, id_servicio, deleted_at)`.

### `profesional_efector_servicio_agenda`

Agenda por asignación.

- `id_profesional_efector_servicio` (**obligatorio**).
- `id_efector` (**obligatorio**, redundante pero útil para consultas/validación).
- Campos espejo de agenda (`formas_atencion`, `cupo_pacientes`, días `*_2`, etc.).

Unique recomendado (soft delete): `(id_profesional_efector_servicio, deleted_at)`.

### `profesional_efector_servicio_condicion_laboral`

Condición laboral por asignación (histórico).

## Trigger (MySQL): impedir agenda si `servicios.acepta_turnos != 'SI'`

> Se ejecuta manualmente en el entorno (no está en la migración).

```sql
DELIMITER $$

CREATE TRIGGER trg_pes_agenda_no_acepta_turnos_ins
BEFORE INSERT ON profesional_efector_servicio_agenda
FOR EACH ROW
BEGIN
  DECLARE v_acepta VARCHAR(8);

  SELECT s.acepta_turnos
    INTO v_acepta
    FROM profesional_efector_servicio pes
    JOIN servicios s ON s.id_servicio = pes.id_servicio
   WHERE pes.id = NEW.id_profesional_efector_servicio
   LIMIT 1;

  IF v_acepta IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asignación profesional-efector-servicio inválida.';
  END IF;

  IF UPPER(TRIM(v_acepta)) <> 'SI' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede crear agenda: el servicio no acepta turnos.';
  END IF;
END$$

CREATE TRIGGER trg_pes_agenda_no_acepta_turnos_upd
BEFORE UPDATE ON profesional_efector_servicio_agenda
FOR EACH ROW
BEGIN
  DECLARE v_acepta VARCHAR(8);

  SELECT s.acepta_turnos
    INTO v_acepta
    FROM profesional_efector_servicio pes
    JOIN servicios s ON s.id_servicio = pes.id_servicio
   WHERE pes.id = NEW.id_profesional_efector_servicio
   LIMIT 1;

  IF v_acepta IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asignación profesional-efector-servicio inválida.';
  END IF;

  IF UPPER(TRIM(v_acepta)) <> 'SI' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede mantener agenda: el servicio no acepta turnos.';
  END IF;
END$$

DELIMITER ;
```

## Estado actual de transición

Mientras haya consumidores de `agenda_rrhh`/`rrhh_servicio`, se puede mantener una etapa de transición donde el submit escribe en ambos modelos y se migra consumo por etapas.

- **RBAC (webvimark) tras renombrar rutas API** a `/api/profesional-agenda/*` y `/api/recurso-humano/*`: script en `web/docs/sql/2026_migrate_webvimark_routes_profesional_agenda_recurso_humano.sql` (actualiza `auth_item.name`; `auth_item_child` / `auth_assignment` suelen seguir vía `ON UPDATE CASCADE`; `auth_item_group` y `auth_rule` no son paths HTTP). Al final del mismo archivo hay un bloque **opcional comentado** para `DROP` de `agenda_rrhh` / `rrhh_*` y quitar `legacy_rrhh_servicio_id` cuando no se quiera retrocompatibilidad con el esquema viejo.

### Fase consumidores (datos)

1. **Turnos**: migración Yii `m260508_000002_turnos_id_profesional_efector_servicio` añade `turnos.id_profesional_efector_servicio`, índice y backfill por `legacy_rrhh_servicio_id`. El modelo `Turno` sincroniza esa columna al crear/actualizar vía ActiveRecord cuando cambia `id_rrhh_servicio_asignado`; los `UPDATE` directos deben mantener ambas columnas o repetir el backfill SQL.
2. **Consumidores adicionales**: migración Yii `m260508_000003_consumidores_id_profesional_efector_servicio` agrega `id_profesional_efector_servicio` y backfill en `consultas`, `consultas_derivaciones`, `documentos_externos`, `guardia` (los modelos sincronizan PES al guardar). Resto del inventario: mismo SQL de webvimark + `2026_migracion_datos_consumidores_pes.sql`.


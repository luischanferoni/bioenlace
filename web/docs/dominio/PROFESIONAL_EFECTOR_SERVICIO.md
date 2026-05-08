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


# Solicitudes entre médicos (`solicitud_rrhh`)

## Modos (`efector_turnos_config.modo_comunicacion_medicos`)

| Valor | Comportamiento |
|--------|----------------|
| `deshabilitado` | No se listan ni crean solicitudes. |
| `directo` | Requiere `id_destinatario_rr_hh` al crear. |
| `intermediario` | Sin destinatario fijo al crear (gestión manual posterior). |
| `auto_asignacion` | Asigna otro RRHH del mismo efector (heurística simple). |

## API

- `GET api/v1/solicitud-rrhh`
- `POST api/v1/solicitud-rrhh` — `mensaje`, opcional `tipo`, `id_destinatario_rr_hh` (modo directo)

## Admin

Listado reciente en backend: Efector → **Config. turnos**.

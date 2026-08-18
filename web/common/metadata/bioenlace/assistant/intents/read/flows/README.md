# Lecturas que no caben en DataAccess

YAML de **pantallas / flows** de consulta (listados paciente, tableros, indicadores con wizard, lookup provincial, ABM admin). Siguen siendo categoría CRUD `read` (permiso por `intent_id`).

No uses esta carpeta para métricas staff: esas van en `read/` con `metric_id`.

Candidato a migrar al motor genérico cuando exista métrica: `turnos.ver-ultimo-en-oferta-como-paciente` (filtros `alcance`, `id_servicio`, `limit`).

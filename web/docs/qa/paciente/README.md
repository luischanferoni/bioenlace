# QA — Paciente (app móvil)

[← Índice general](../README.md)

**Pruebas integrales por caso clínico:** [escenarios/](../escenarios/README.md) (ambulatorio, urgencia, seguimiento).

## Flujos paso a paso

| Archivo | De qué habla |
|---------|----------------|
| [contexto-registro.md](./contexto-registro.md) | Sector, provincia, registro, recurso provincial |
| [turnos.md](./turnos.md) | Sacar, cancelar, reprogramar, sin cupo / adelanto, **motivos pre-turno** |
| [laboratorio-receta-planes.md](./laboratorio-receta-planes.md) | Resultados, recetas, tratamientos, resúmenes |
| [asistente.md](./asistente.md) | Frases y atajos del chat |
| [asistente-consultas.md](./asistente-consultas.md) | Catálogo: tipos, ejemplo, cobertura; runner CLI `php yii qa/asistente-consultas` |
| [asistente-whatsapp.md](./asistente-whatsapp.md) | Smoke MVP canal WhatsApp (Cloud API) |

## Checklist

| Archivo | Prefijos |
|---------|----------|
| [checklist.md](./checklist.md) | TRN-05, CTX, TUR (app), MOT (motivos pre-turno), LAB/RX/PLN (paciente), AST (app) |

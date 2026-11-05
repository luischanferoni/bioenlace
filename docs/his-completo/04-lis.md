# Laboratorio (LIS)

**Madurez orientativa:** 2,7 / 4 (~68 %) — integración externa operativa; no es un LIS de planta.

## Lo que tenemos

- [x] Traer resultados de laboratorios externos (informes y analitos normalizados).
- [x] Persistencia local y consulta por paciente y por atención.
- [x] Sincronización programada (lote o por persona).
- [x] PDF de informe para el paciente; listado y detalle (también en el asistente).
- [x] Pedidos de estudio en la atención con estado pendiente / con resultado en el resumen.
- [x] Clasificación al ingresar un informe final (normal / control / crítico) y **aviso al paciente**; si es crítico, también al profesional.
- [x] Vinculación automática informe ↔ atención cuando el match es claro; si no, bandeja para el equipo.

## Lo que falta para un LIS hospitalario completo

- [ ] Orden de laboratorio de punta a punta en planta (pedido → muestra → validación bioquímica).
- [ ] Workflow por sector de laboratorio y roles de validación.
- [ ] Catálogo analítico de planta (perfiles, rangos) gestionado en el efector.
- [ ] Conectores “plug and play” con cualquier LIS sin proyecto por proveedor.

## Documentación de producto

[laboratorio.md](../producto/laboratorio.md) · [agentes-autonomos.md](../producto/agentes-autonomos.md)

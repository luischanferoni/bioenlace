# Laboratorio (LIS)

**Madurez orientativa:** 2,5/4 (integración externa operativa; no LIS propio en planta)

## Lo que tenemos

- [x] Traer resultados de laboratorios externos (informes y analitos normalizados).
- [x] Persistencia local y consulta por paciente y por atención (encounter).
- [x] Sincronización programada (cron / lote por persona o volumen).
- [x] PDF de informe para el paciente.
- [x] Listado y detalle en Bioenlace (incluido flujo conversacional).
- [x] Enlace desde informe hacia la atención donde se solicitó el estudio (cuando aplica).
- [x] Pedidos de estudio en atención con estado “pendiente / con resultado” en resumen de atención.

## Lo que falta para un LIS hospitalario completo

- [ ] Orden de laboratorio end-to-end dentro del HIS (pedido → toma de muestra → validación en laboratorio propio).
- [ ] Workflow por sector de laboratorio y roles de validación.
- [ ] Catálogo analítico operativo (perfiles, rangos, LOINC) gestionado en planta.
- [ ] Conectores amplios con cualquier LIS del mercado sin proyecto por proveedor.
- [ ] Push al paciente al liberar resultado (opcional; hoy la consulta es pull).

## En producto hoy

[producto/laboratorio.md](../producto/laboratorio.md)

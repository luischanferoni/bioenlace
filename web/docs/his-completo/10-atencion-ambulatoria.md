# Atención ambulatoria (encounter FHIR)

**Madurez orientativa:** 3/4

Núcleo clínico actual de Bioenlace para consulta externa: modelo **encounter** ambulatorio finalizado, captura con IA, pedidos, recetas y resumen al paciente.

## Lo que tenemos

- [x] Encounter ambulatorio (`AMB`) con ciclo de vida (incluye finalizado).
- [x] Captura por texto o audio, análisis y guardado con nota clínica (salida IA en nota al cerrar).
- [x] Condiciones, pedidos (`service_request`), medicación y receta electrónica vinculados al encounter.
- [x] Resultados de laboratorio consultables en contexto de la atención.
- [x] **Resumen al paciente**: publicación automática tras finalizar (cola + notificación), listado y detalle en Bioenlace.
- [x] Grafo paciente: desde resumen → receta, laboratorio, pedidos con estado.
- [x] **Expediente legal amplio** solo staff: solicitud async, PDF en cola, descarga auditada (no disponible para paciente).

## Lo que falta

- [ ] Historia clínica longitudinal única “tipo expediente” para staff sin export PDF (timeline unificado en UI).
- [ ] Internación y urgencias en el mismo modelo FHIR con la misma profundidad que AMB.
- [ ] Derivaciones estructuradas (encounter hijo / turno futuro) en producto.
- [ ] Interoperabilidad saliente (HL7/FHIR bundle) a redes de salud.

## En producto hoy

[producto/captura-clinica.md](../producto/captura-clinica.md) · [producto/resumen-atencion-paciente.md](../producto/resumen-atencion-paciente.md)

# Atención ambulatoria (encounter)

**Madurez orientativa:** 3,4 / 4 (~85 %)

Núcleo clínico de consultorio: un **registro unificado de la atención**, captura con IA, pedidos, recetas, resumen al paciente y canales digitales alrededor del encuentro.

## Lo que tenemos

- [x] Encounter ambulatorio con ciclo de vida (incluido cierre).
- [x] Captura por texto o audio, análisis y guardado; misma superficie que guardia e internación.
- [x] Codificación automática de diagnósticos al guardar (provisional; el profesional documenta).
- [x] Condiciones, pedidos, medicación y receta electrónica ligados a esa atención.
- [x] Resultados de laboratorio en contexto; motivos e intake **antes** del dictado.
- [x] Recorrido pre/post consulta (intake, chat de motivos, packs de cohorte).
- [x] Solicitar Atención: malestar, estudio/práctica, control/seguimiento o urgencia (sin diagnosticar).
- [x] Teleconsulta y consulta clínica por mensaje cuando el caso y el servicio lo permiten.
- [x] Resumen al paciente al finalizar (publicación + aviso); listado de atenciones previas.
- [x] Expediente legal amplio solo para staff (PDF en cola, descarga auditada).
- [x] Export FHIR de atención finalizada hacia red / Estado (homologación nacional pendiente).
- [x] Representación: tutela de menor y delegación para operar por otro paciente.
- [x] Asistente (app y WhatsApp reactivo) para acciones del paciente.

## Lo que falta

- [ ] Historia clínica longitudinal única en pantalla para el médico (sin depender del PDF).
- [ ] Misma profundidad de plantillas y cierre en **toda** internación/guardia que en ambulatorio (el pipeline es el mismo; faltan unificar vistas).
- [ ] Derivación a especialista como flujo explícito “tengo una derivación” (hoy el hub filtra; falta el atajo dedicado).
- [ ] Homologación del intercambio FHIR con redes nacionales.

## Documentación de producto

[captura-clinica.md](../producto/captura-clinica.md) · [solicitar-atencion.md](../producto/solicitar-atencion.md) · [recorrido-pre-post-consulta.md](../producto/recorrido-pre-post-consulta.md) · [resumen-atencion-paciente.md](../producto/resumen-atencion-paciente.md) · [atencion-remota-async.md](../producto/atencion-remota-async.md) · [interoperabilidad-historia-clinica.md](../producto/interoperabilidad-historia-clinica.md) · [representacion-paciente.md](../producto/representacion-paciente.md)

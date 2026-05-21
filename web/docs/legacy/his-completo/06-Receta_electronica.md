# Receta electrónica

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **3/4** (Intermedio).
- **Modelos/bundles de prescripción (enfoque interoperable)**:
  - `web/common/models/bundles/MedicationRequest.php`
  - `web/common/models/bundles/Prescripcion.php`
- **Prescripción en consultas**:
  - `web/common/models/ConsultaMedicamentos.php`
  - Vistas de prescripción en `web/frontend/views/consultas/prescripciones_medicas.php`
- **Recetas específicas (ej. oftalmología)**:
  - `web/common/models/ConsultasRecetaLentes.php`
  - `web/frontend/views/consultas-receta-lentes/*`
- **Terminología/nomencladores**:
  - `web/common/models/snomed/SnomedMedicamentos.php`
  - `web/common/models/NomencladorSumar.php` (y vistas de nomenclador)

## Qué parece cubrir hoy

- Prescripción estructurada en el contexto clínico (consulta / internación).
- Representación interoperable para intercambio (estilo FHIR).
- Casos específicos (receta de lentes) ya modelados y con UI.

## Brechas típicas para “receta electrónica completa” (nivel regulatorio)

- **Firma y validez legal**
  - Firma digital / sello / mecanismos de autenticación fuerte para prescriptor.
- **Interoperabilidad normativa**
  - Integración con plataformas nacionales/provinciales (si aplica), validaciones y estados.
- **Ciclo completo**
  - Emisión → validación → dispensación (farmacia) → auditoría → anulaciones/rectificaciones.
- **Seguridad y auditoría**
  - Registro de quién accede/dispensa, prevención de abuso, trazabilidad.

## Próximos pasos recomendados

- Definir el “contrato” de receta electrónica objetivo:
  - **Standalone** (funciona por sí sola) vs **integrada** a un sistema oficial.
- Implementar firma/autenticación fuerte del prescriptor (y auditoría).
- Completar estados del documento (borrador, emitida, dispensada, anulada) y su trazabilidad.
- Integrar con farmacia (dispensación) para cerrar el circuito.


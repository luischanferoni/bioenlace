# Internación

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **3–4/4** (Intermedio/Avanzado).
- **Modelo central de internación**:
  - `web/common/models/SegNivelInternacion.php`
- **Entidades hijas (clínica y consumos)**:
  - Diagnósticos: `SegNivelInternacionDiagnostico.php`
  - Medicación: `SegNivelInternacionMedicamento.php`
  - Suministro de medicación: `SegNivelInternacionSuministroMedicamento.php`
  - Prácticas: `SegNivelInternacionPractica.php`
  - Consumos: `SegNivelInternacionConsumo.php`
- **Infraestructura (camas/pisos)**:
  - `web/common/models/InfraestructuraPiso.php`
  - `web/common/models/InfraestructuraCama.php`
- **Controladores**:
  - `web/frontend/controllers/InternacionController.php`
  - `InternacionMedicamentoController.php`
  - `InternacionDiagnosticoController.php`
  - `InternacionAtencionesEnfermeriaController.php`
  - `ConsultaInternacionSuministroMedicamentoController.php`
- **Vistas**:
  - `web/frontend/views/internacion/*`
  - `internacion-medicamento/*`, `internacion-diagnostico/*`, `internacion-atenciones-enfermeria/*`, `internacion-suministro-medicamento/*`
- **Orquestación conversacional**:
  - `web/common/components/intent_handlers/InternacionHandler.php`

## Qué parece cubrir hoy

- Admisión y seguimiento de internación.
- Registro de diagnósticos durante la estancia.
- Medicación indicada y suministro/administración.
- Registro de consumos y prácticas asociadas.
- Soporte de enfermería (vía controlador/vistas específicas).
- Asociación a infraestructura (camas/pisos), habilitando gestión de ocupación.

## Brechas típicas hacia internación “4/4” (HIS completo)

- **Órdenes médicas estructuradas** (más allá de prácticas/medicación sueltas)
  - Catálogo de órdenes, estados (pendiente/en curso/completada/cancelada), priorización.
- **Hoja de enfermería avanzada**
  - Plan de cuidados, escalas, registros de signos vitales, balance hídrico integrado.
- **Pases y continuidad**
  - Transferencias entre servicios, cambios de cama con trazabilidad completa, interconsultas.
- **Alta y documentación final**
  - Epicrisis estructurada, resumen de egreso, medicación al alta, turnos de control.
- **Integración con logística/facturación**
  - Consumos valorizados, trazabilidad de insumos (especialmente si se extiende a quirófanos).

## Próximos pasos recomendados

- Consolidar “órdenes” como entidad de primer nivel (medicación + prácticas + estudios) con workflow.
- Fortalecer tablero de camas/ocupación y reportes operativos.
- Cerrar el circuito de alta (epicrisis) e integración de medicación al alta con receta electrónica.
- Integrar consumos con módulo de materiales/logística (stock y trazabilidad).


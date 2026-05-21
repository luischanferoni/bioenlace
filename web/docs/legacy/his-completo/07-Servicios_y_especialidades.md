# Servicios y especialidades (cardiología, oftalmología, etc.)

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **3/4** (Intermedio).
- **Modelos estructurales**:
  - `web/common/models/Servicio.php` (servicios/especialidades)
  - `web/common/models/Efector.php` (efectores)
- **Consulta como núcleo multiespecialidad**
  - `web/common/models/Consulta.php` + entidades asociadas (diagnósticos, prácticas, medicamentos, evoluciones).
- **Especialidades con soporte explícito**
  - Oftalmología:
    - Prácticas/estudios: `web/common/models/ConsultaPracticasOftalmologiaEstudios.php`
    - Receta lentes: `web/common/models/ConsultasRecetaLentes.php` + vistas `consultas-receta-lentes/*`
  - Odontología:
    - `web/common/models/ConsultaOdontologiaPracticas.php`
    - Controlador: `web/frontend/controllers/ConsultaOdontologiaController.php`
  - Obstetricia:
    - Controlador: `web/frontend/controllers/ConsultaObstetriciaController.php`
  - Evoluciones/otros:
    - `web/common/models/ConsultaEvolucion.php`, controladores de evolución, etc.
- **UI longitudinal**
  - Timeline del paciente: `web/frontend/views/paciente/timeline/*`
- **Orquestación de intents**
  - Handlers por dominios clínicos (`ConsultaMedicaHandler`, `PracticasHandler`, `ProfesionalesHandler`, etc.) permiten navegación/acciones transversales.

## Qué parece habilitar hoy

- Un **core de consulta** reutilizable para múltiples especialidades con extensiones por módulo/vista.
- Normalización terminológica (SNOMED) para diagnósticos y medicamentos, clave para “unificar” especialidades.
- Capacidad de sumar nuevos formularios/plantillas por servicio sin reescribir el núcleo.

## Brechas para “todas las especialidades” al nivel de un HIS completo

- **Verticales complejas** (ejemplos típicos)
  - UCI (ventilación, bombas, scores, curvas), hemodinamia, oncología (esquemas), neonatología, diálisis, etc.
- **Integración con dispositivos**
  - Captura automática de signos, monitores, equipos.
- **Plantillas y estandarización**
  - Librería de formularios por especialidad con versionado, firmas, auditoría, y medidas de calidad.
- **KPIs y calidad**
  - Indicadores por servicio, auditoría clínica, guías/protocolos.

## Próximos pasos recomendados

- Definir un “framework” de plantillas por servicio:
  - Campos estructurados + narrativa clínica + terminología (SNOMED/LOINC).
- Priorizar 3–5 especialidades de alto impacto para llevar a 4/4 (según efector).
- Consolidar interoperabilidad (FHIR Resources) para que cada especialidad exporte datos clínicos de forma estándar.


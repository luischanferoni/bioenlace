# Urgencias (Guardia)

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **3/4** (Intermedio).
- **Modelos / controladores evidentes**:
  - `web/common/models/Guardia.php`
  - `web/frontend/controllers/GuardiaController.php`
  - `web/frontend/views/guardia/*`
- **Orquestación conversacional**:
  - Handler: `web/common/components/intent_handlers/EmergenciasHandler.php`
  - Router: (pendiente) unificación en `web/common/components/IntentEngine/IntentEngine.php` si se decide disparar UIs desde el asistente para urgencias.

## Qué parece cubrir hoy

- Registro de episodios de guardia/urgencia asociados a paciente/persona y efector.
- Flujos UI dedicados (vistas de guardia).
- Base para derivación a internación o continuidad en consulta (dependiendo de cómo se enlace en controladores).

## Brechas típicas hacia un módulo HIS “completo” de urgencias

- **Triage estructurado**
  - Escala (ESI/Manchester u otra), categorización de riesgo, motivos estandarizados, tiempos.
- **Tablero operativo**
  - Cola de espera, tiempos “door-to-…”, estados (en espera, en atención, observación, alta, derivación).
- **Órdenes y resultados integrados**
  - Pedidos de laboratorio/imagen desde guardia (workflow completo) y seguimiento de estados.
- **Derivación / internación**
  - Flujo “cama caliente” y asignación de cama/servicio, con trazabilidad (quién, cuándo, por qué).
- **Indicadores y auditoría**
  - KPIs, trazabilidad de acciones y accesos.

## Próximos pasos recomendados

- Completar triage como entidad primera clase (modelo + vistas + reportes).
- Integrar mejor el episodio de guardia con:
  - Internación (`SegNivelInternacion`) cuando se interna.
  - Consulta ambulatoria (`Consulta`) cuando se resuelve sin internación.
- Agregar tablero/estado de pacientes con workflows claros.
- Extender intents para: “admitir guardia”, “clasificar triage”, “ver cola”, “derivar a internación”.


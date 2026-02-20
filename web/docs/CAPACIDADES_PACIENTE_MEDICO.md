# Capacidades paciente–médico y experiencia de uso

Este documento describe capacidades clave de la plataforma Bioenlace que mejoran la experiencia del paciente antes y durante la atención, y apoyan el trabajo diario de médicos e instituciones.

---

## 1. Conversación continua con el paciente (pre-consulta y acompañamiento)

### Descripción

Se permite al paciente mantener una **conversación continua** con el sistema (chat con IA y/o con el equipo de salud) para:

- **Despejar dudas** sobre su estado, medicación o preparación para la consulta.
- **Recibir guía** sobre qué hacer antes del turno (ayuno, estudios, documentación).
- **Informarse** sobre todo lo que está pasando: estado del turno, recordatorios, resultados disponibles, próximos pasos.
- **Reducir ansiedad** y llegar mejor preparado a la consulta con el médico.

### Consideraciones técnicas y de producto

- El flujo puede combinar respuestas automáticas (IA), respuestas predefinidas y, cuando corresponda, derivación a un humano o al médico.
- La conversación puede estar asociada a un turno concreto (pre-consulta) o ser un hilo más general de seguimiento.
- Conviene definir límites de uso (por ejemplo, ventana de tiempo pre-consulta) para alinear expectativas y costos de IA.
- Ver estimación de costos en [COSTOS.md - Costos por capacidades adicionales](./COSTOS.md#costos-por-capacidades-adicionales).

---

## 2. Agente de IA para onboarding y tareas del día a día

### Descripción

Un **agente de IA** acompaña a los usuarios (pacientes, médicos, administrativos) en:

- **Onboarding**: explicación del sistema, primeros pasos, configuración de perfil, cómo pedir turnos o cómo atender por videollamada/chat.
- **Tareas del día a día**: recordatorios, respuestas a preguntas frecuentes (horarios, trámites, dónde ver resultados), guía para completar formularios o pasos dentro de la plataforma.

El agente actúa como asistente contextual dentro de la aplicación, reduciendo carga de soporte y mejorando la adopción.

### Consideraciones técnicas y de producto

- Puede reutilizar el mismo orquestador de intents y handlers que el chat médico, con categorías específicas para “soporte” y “onboarding”.
- Las respuestas pueden priorizar respuestas predefinidas o flujos guiados y usar IA solo cuando sea necesario (igual que en corrección de texto o clasificación de intents).
- Métricas útiles: número de interacciones por usuario/mes, tasa de resolución sin derivar a humano, tiempo hasta primer uso completo.
- Ver estimación de costos en [COSTOS.md - Costos por capacidades adicionales](./COSTOS.md#costos-por-capacidades-adicionales).

---

## 3. Intercambio de audios, fotos y videos entre médico y paciente

### Descripción

La plataforma permite el **intercambio de medios** (audios, fotos, videos) entre el médico y el paciente en el contexto de la atención (chat de consulta, seguimiento):

- **Antes o durante la consulta**: el paciente envía fotos de lesiones, videos cortos de movilidad o audios describiendo síntomas.
- **El médico y el personal médico** los reciben, ven y escuchan **directamente** en la aplicación; no se almacenan en cloud storage.
- Solo cuando se requiere que la **IA analice** el contenido (p. ej. transcripción de un audio, análisis de una imagen), se envía ese medio al servicio correspondiente (Speech-to-Text, Vision API). El resto del flujo es visualización/escucha directa por el profesional.
- El médico puede responder con audios o mensajes con medios; igualmente son consumidos directamente por el paciente, sin almacenamiento en la nube salvo si se invoca IA sobre ellos.

### Consideraciones técnicas y de producto

- **Sin almacenamiento en cloud**: los audios, fotos y videos no se persisten en Google Cloud Storage ni en otro objeto storage; se transmiten para visualización/escucha en el momento (y pueden quedar en dispositivo o en almacenamiento local/institucional si se define así).
- **Envío a IA solo bajo demanda**: transcripción (STT) o análisis de imagen (Vision) solo cuando aporte valor; con consentimiento y uso conforme a normativa.
- **Seguridad y privacidad**: acceso restringido por rol, encriptación en tránsito para el envío entre paciente y médico; si en algún flujo se envía a un servicio de IA, cumplir políticas del proveedor y normativa de datos de salud.
- Ver estimación de costos (solo STT/Vision cuando se usen) en [COSTOS.md - Costos por capacidades adicionales](./COSTOS.md#costos-por-capacidades-adicionales).

---

## 4. Videollamadas entre paciente y médico

### Descripción

Se permiten **videollamadas** entre el paciente y el médico en el marco de la consulta (turno virtual):

- Sustituyen o complementan la atención presencial cuando la distancia o la situación lo requieren.
- Pueden integrarse en el mismo flujo de turnos: el paciente elige “videollamada”, recibe enlace o acceso desde la app, y se conecta a la hora del turno.
- La plataforma puede ofrecer solo conectividad (WebRTC o proveedor CPaaS) o además grabación/consentimiento según normativa.

### Consideraciones técnicas y de producto

- **Infraestructura**: uso de servicio de videollamada (Twilio, Daily.co, Jitsi, etc.) o despliegue propio con WebRTC (TURN/STUN), con impacto directo en costos y mantenimiento.
- **Calidad y conectividad**: especialmente relevante en territorios con limitaciones de conectividad; considerar degradación elegante (solo audio, calidad adaptable).
- **Cumplimiento**: consentimiento del paciente para videoconsulta, grabación (si aplica) y almacenamiento según normativa vigente.
- Ver estimación de costos (minutos o asientos, ancho de banda) en [COSTOS.md - Costos por capacidades adicionales](./COSTOS.md#costos-por-capacidades-adicionales).

---

## Resumen y relación con otros documentos

| Capacidad | Objetivo principal | Documento de costos |
|-----------|--------------------|----------------------|
| Conversación pre-consulta | Guiar e informar al paciente antes del turno | [COSTOS.md](./COSTOS.md#costos-por-capacidades-adicionales) |
| Agente IA onboarding/día a día | Reducir fricción y soporte, mejorar adopción | [COSTOS.md](./COSTOS.md#costos-por-capacidades-adicionales) |
| Audios, fotos, videos | Enriquecer la comunicación médico–paciente | [COSTOS.md](./COSTOS.md#costos-por-capacidades-adicionales) |
| Videollamadas | Atención remota integrada al flujo de turnos | [COSTOS.md](./COSTOS.md#costos-por-capacidades-adicionales) |

Para flujos del chat y orquestación de intents, ver [FLUJO_CHAT_ORQUESTADOR.md](./FLUJO_CHAT_ORQUESTADOR.md). Para configuración de IA y proveedores, ver [GOOGLE_CLOUD_SETUP.md](./GOOGLE_CLOUD_SETUP.md) y `params.php`.

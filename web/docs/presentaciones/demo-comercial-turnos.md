---
marp: true
theme: bioenlace
paginate: true
size: 16:9
html: true
footer: "Bioenlace · Demo comercial · Turnos"
title: "Bioenlace — Turnos que conectan toda la atención"
description: "Presentación comercial centrada en el recorrido integral de turnos de Bioenlace."
---

## 1. El paciente cuenta qué necesita

<div class="grid-2">
  <div>
    <p class="eyebrow">Reserva asistida</p>
    <h3>Lenguaje simple, sin conocer la estructura del hospital</h3>
    <ul>
      <li>Motivo y señales de alarma.</li>
      <li>Zona y evolución cuando corresponde.</li>
      <li>Servicio sugerido por reglas clínicas declarativas.</li>
      <li>Reserva desde conversación o pantalla directa.</li>
      <li>Consulta por mensaje y seguimiento de tratamiento en un flujo dedicado.</li>
    </ul>
    <div class="callout callout--green">No diagnostica: organiza el acceso y protege el circuito.</div>
  </div>
  <div class="phone">
    <div class="chat chat--user">Necesito atención por un dolor que empezó ayer.</div>
    <div class="chat">Antes de buscar un turno, necesito hacerte unas preguntas breves.</div>
    <div class="option">¿Apareció alguna señal de alarma?</div>
    <div class="option">¿En qué zona sentís el dolor?</div>
  </div>
</div>

---

## 2. Seguridad antes de disponibilidad

<div class="grid-2">
  <div class="card card--accent">
    <span class="metric">Banda A</span>
    <h3>Alarma actual</h3>
    <p>La app detiene la reserva y orienta al circuito de urgencia / 107.</p>
  </div>
  <div class="card card--green">
    <span class="metric">B–D</span>
    <h3>Atención programable</h3>
    <p>Continúa según prioridad, servicio, política local y modalidad permitida.</p>
  </div>
</div>

<div class="callout">La disponibilidad nunca debe imponerse sobre una señal clínica de alarma.</div>

<p class="small"><span class="status status--ready">Implementado</span> Catálogo versionado, persistencia del recorrido y validación al crear el turno.</p>

---

## 3. La modalidad se ofrece cuando corresponde

<div class="grid-3">
  <div class="card card--green">
    <h3>Presencial</h3>
    <p>Camino base para atención ambulatoria programable.</p>
  </div>
  <div class="card card--blue">
    <h3>Videollamada con turno</h3>
    <p>Solo si lo permiten el motivo, el servicio y la agenda profesional.</p>
  </div>
  <div class="card card--accent">
    <h3>Consulta por mensaje</h3>
    <p>Atención asincrónica sin turno ni video; responde un profesional real.</p>
  </div>
</div>

<div class="callout callout--green">Si solo aplica presencial, Bioenlace omite el paso de modalidad: menos decisiones innecesarias.</div>

<p class="small"><span class="status status--config">Gobernado</span> Política por servicio + elegibilidad de triage + agenda que acepta remoto. Especialistas con derivación vigente del clínico.</p>

---

## 4. Agenda operativa para cada actor

<div class="grid-2">
  <div class="card card--blue">
    <h3>Paciente y representante</h3>
    <ul>
      <li>Reservar, cancelar y reprogramar.</li>
      <li>Gestionar a un menor o adulto que delegó.</li>
      <li>Recibir avisos y resolver cambios desde la app.</li>
    </ul>
  </div>
  <div class="card card--green">
    <h3>Profesional y efector</h3>
    <ul>
      <li>Grilla, cupos, sobreturnos y agenda del día.</li>
      <li>Alta para terceros y cancelación masiva.</li>
      <li>Políticas locales con permisos por rol y recurso.</li>
    </ul>
  </div>
</div>

<p class="small">Ambulatorio usa cupos y turnos. Guardia e internación usan cobertura de personal: Bioenlace no fuerza la misma agenda para procesos diferentes.</p>

---

## 5. El turno prepara la consulta

<div class="timeline">
  <div class="timeline-time">Al reservar</div>
  <div class="timeline-item">Se conserva triage, servicio, modalidad y contexto de agenda.</div>
  <div class="timeline-time">Ventana previa</div>
  <div class="timeline-item">El paciente completa intake, motivos conversacionales y asistencia preconsulta cuando aplica.</div>
  <div class="timeline-time">Al abrir la historia</div>
  <div class="timeline-item">El equipo ve preguntas previas, resumen de motivos e información de cohorte.</div>
  <div class="timeline-time">Después</div>
  <div class="timeline-item">Touchpoints de seguimiento y acceso a planes de tratamiento.</div>
</div>

<div class="callout">Menos tiempo reconstruyendo el motivo. Más tiempo para la decisión clínica.</div>

---

## 6. Cuando cambia la agenda, el turno entra “en resolución”

<div class="grid-3">
  <div class="card card--accent">
    <h3>Opciones concretas</h3>
    <p>Bioenlace puede adjuntar hasta tres horarios vecinos y compatibles en el aviso.</p>
  </div>
  <div class="card card--blue">
    <h3>Escalada</h3>
    <p>Aviso en la app y, si no hay respuesta, email y SMS con enlace seguro.</p>
  </div>
  <div class="card card--green">
    <h3>Preferencias</h3>
    <p>Si el paciente lo autorizó, puede reubicarse automáticamente cuando hay una alternativa inequívoca.</p>
  </div>
</div>

<p class="small"><span class="status status--ready">Implementado v1</span> Reubicación, opciones alternativas, escalada multicanal y auditoría. <span class="status status--config">Configurable</span> Reubicación automática por paciente y efector. Tras 72 h sin respuesta: cierre con cancelación o escalada a staff según reglas.</p>

---

## 7. Un cupo cancelado puede volver a utilizarse

<div class="flow">
  <div class="flow-step">Cancelación<br>con anticipación</div>
  <div class="flow-arrow">→</div>
  <div class="flow-step">Slot libre<br>compatible</div>
  <div class="flow-arrow">→</div>
  <div class="flow-step">Oferta al turno<br>posterior más cercano</div>
  <div class="flow-arrow">→</div>
  <div class="flow-step">El paciente<br>decide</div>
</div>

<div class="grid-2" style="margin-top: 34px">
  <div class="card card--green">
    <h3>Sin “lista de espera” opaca</h3>
    <p>La oferta es secuencial, trazable y sujeta a disponibilidad.</p>
  </div>
  <div class="card card--blue">
    <h3>Sin crear otra cita</h3>
    <p>Al aceptar, se reprograma el turno existente bajo lock del slot.</p>
  </div>
</div>

<p class="small"><span class="status status--ready">Implementado</span> Agente de reglas A03; el perfil histórico no excluye ni ordena candidatos.</p>

---

## 8. Medir acceso sin planillas

<div class="grid-3">
  <div class="card">
    <span class="metric">Inasistencias</span>
    <p>Ausencias atribuibles al paciente sobre turnos cerrados.</p>
  </div>
  <div class="card">
    <span class="metric">Tiempo hasta la cita</span>
    <p>Días entre reserva y consulta: promedio y mediana.</p>
  </div>
  <div class="card">
    <span class="metric">Remoto</span>
    <p>Demanda presencial que podría resolverse con otra modalidad.</p>
  </div>
</div>

<div class="callout callout--green">La dirección consulta indicadores desde Bioenlace, con filtros por período y recurso. Recordatorios y confirmación proactiva antes de liberar cupos en casos de alto riesgo.</div>

---

## Transparencia antes que un “score” opaco

<div class="grid-2">
  <div class="card card--green">
    <h3>Perfil factual</h3>
    <ul>
      <li>Hechos atribuibles y versionados.</li>
      <li>Asistencia, cancelación, reprogramación y confirmación.</li>
      <li>Explicación y solicitud de corrección.</li>
    </ul>
  </div>
  <div class="card card--accent">
    <h3>Límites explícitos</h3>
    <ul>
      <li>No es reputación ni prioridad clínica.</li>
      <li>Datos insuficientes no significan riesgo.</li>
      <li>No se liberan cupos automáticamente durante shadow.</li>
    </ul>
  </div>
</div>

<p><span class="status status--pilot">Shadow / piloto pendiente</span> La infraestructura de transparencia existe; las acciones de alto impacto requieren evidencia, equidad, aprobación y rollback.</p>

---

## Qué obtiene cada organización

<div class="grid-3">
  <div class="card card--accent">
    <h3>Paciente</h3>
    <p>Menos llamadas, orientación clara, autogestión y continuidad desde el celular.</p>
  </div>
  <div class="card card--green">
    <h3>Equipo de salud</h3>
    <p>Agenda conectada con contexto clínico, menos tareas repetitivas y decisiones confirmables.</p>
  </div>
  <div class="card card--blue">
    <h3>Dirección / red</h3>
    <p>Políticas por efector, trazabilidad, indicadores e interoperabilidad vía API/FHIR.</p>
  </div>
</div>

<div class="callout">Bioenlace no agrega otro canal: conecta los canales alrededor del mismo turno y del mismo encounter.</div>

---

## Guion de demo en vivo

<div class="demo-script">
  <div><strong>Paciente:</strong> iniciar “Necesito atención” y recorrer motivo + triage.</div>
  <div><strong>Reserva:</strong> mostrar cuándo aparece modalidad, elegir centro y horario.</div>
  <div><strong>Preparación:</strong> abrir el journey y cargar motivos previos.</div>
  <div><strong>Personal:</strong> abrir la historia con contexto y simular captura clínica.</div>
  <div><strong>Operación:</strong> mostrar reprogramación, opciones de reubicación u oferta de adelantamiento.</div>
  <div><strong>Dirección:</strong> cerrar con indicadores de agenda y políticas configurables.</div>
</div>

<p class="small">Duración recomendada: 12–15 minutos. Usar datos ficticios y aclarar las capacidades en shadow.</p>

---

## Implementación gradual, valor desde la primera etapa

<div class="grid-3">
  <div class="card card--green">
    <span class="metric">1</span>
    <h3>Agenda y autogestión</h3>
    <p>Reserva, avisos, cancelación y reprogramación.</p>
  </div>
  <div class="card card--blue">
    <span class="metric">2</span>
    <h3>Journey clínico</h3>
    <p>Triage, motivos, preconsulta, captura y seguimiento.</p>
  </div>
  <div class="card card--accent">
    <span class="metric">3</span>
    <h3>Optimización</h3>
    <p>Resolución, reocupación, indicadores y agentes auditables.</p>
  </div>
</div>

<p class="small">Licencia por profesionales y clase de atención; módulos y políticas se habilitan según alcance del efector.</p>

---

<!-- _class: closing -->
<!-- _paginate: false -->
<!-- _footer: "" -->

<div class="wordmark" aria-label="Bioenlace">bio<span>enlace</span></div>

# Un turno puede ser el inicio de una atención mejor conectada

<p class="lead">Orientar, reservar, preparar, atender y seguir — en un único ecosistema, con reglas claras y control humano.</p>

<div style="margin-top: 30px"><strong class="accent">bioenlace.io</strong> · info@bioenlace.io</div>

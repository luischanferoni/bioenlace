# Overview — Perfil persistido de comportamiento en turnos

## Problema

Bioenlace ya calcula riesgo de no-show, aplica políticas por cancelaciones reiteradas y publica KPIs agregados. Esos cálculos consultan fuentes y alcances diferentes, no comparten un contrato factual único y no permiten reproducir completamente una decisión histórica.

También faltan eventos suficientes para distinguir:

- cancelación iniciada por paciente, representante, staff o sistema;
- reprogramación de una cancelación;
- ausencia registrada y posteriormente corregida;
- notificación intentada de notificación efectivamente entregada;
- falta de respuesta de imposibilidad técnica para responder.

Persistir solamente una etiqueta `low`, `medium` o `high` consolidaría esas ambigüedades. El programa persiste hechos y métricas versionadas; las políticas siguen siendo declarativas y contextuales.

## Objetivo

Construir una proyección longitudinal de turnos que sea:

- **factual:** derivada de eventos atribuibles;
- **persistida:** disponible sin recalcular todo el historial en cada decisión;
- **versionada:** reproducible frente a cambios de contrato;
- **explicable:** cada métrica conserva numerador, denominador, ventana y alcance;
- **corregible:** una rectificación genera un evento compensatorio y un nuevo snapshot;
- **segura:** minimiza datos y separa información individual de reportes agregados;
- **reutilizable:** una misma definición alimenta anti no-show, cancelaciones y métricas.

## Alcance

### Dimensiones iniciales

- asistencia y no-show;
- cancelación temprana y tardía;
- reprogramación;
- confirmación de asistencia;
- cobertura y calidad del historial.

### Alcances

- global;
- por efector;
- por servicio;
- por modalidad presencial o remota.

### Ventanas

- 90 días;
- 180 días;
- 365 días;
- histórico total solo como dato informativo.

## Fuera de alcance inicial

- aprendizaje automático;
- diagnósticos, tratamientos o texto clínico como predictores;
- geolocalización, dispositivo o nivel socioeconómico;
- una reputación o score comercial del paciente;
- bloquear atención o reducir prioridad clínica;
- compartir perfiles individuales entre instituciones sin base jurídica;
- puntualidad hasta contar con registro confiable de llegada.

## Resultados esperados

1. Eventos de turnos completos, idempotentes y atribuibles.
2. Backfill histórico marcado como inferido.
3. Snapshots de perfil reconstruibles.
4. Contrato común de métricas.
5. Políticas que registran versión, perfil y evidencia consumidos.
6. Explicación accesible para la persona.
7. Reportes agregados con protección de cohortes pequeñas.

## Métricas de éxito del programa

- porcentaje de outcomes con actor y origen conocidos;
- porcentaje del historial cubierto por eventos nativos frente a inferidos;
- coincidencia entre reconstrucción completa e incremental;
- diferencias explicadas entre servicios actuales y perfil nuevo;
- falsos positivos de acciones anti no-show;
- variación de no-show y cancelación tardía frente al baseline;
- impacto en primera visita y continuidad de cuidados;
- solicitudes de corrección y tiempo de resolución;
- disparidad agregada entre grupos de evaluación autorizados.

## Criterio de producto

Un perfil con muestra insuficiente responde `insufficient_data`; nunca se convierte por defecto en riesgo medio. Una política puede adoptar una intervención conservadora frente a incertidumbre, pero debe identificarla como falta de datos y no como conducta negativa.

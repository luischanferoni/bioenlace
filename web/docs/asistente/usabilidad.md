# Usabilidad — Heurísticas de Jakob Nielsen (aplicadas al asistente)

Este documento lista las 10 heurísticas de usabilidad de Jakob Nielsen (Nielsen Norman Group) y una evaluación breve de si las cumplimos en el **Asistente** (Web SPA + flujos conversacionales + mini-UIs `ui_json`).

## 1) Visibilidad del estado del sistema

El sistema debe mantener informados a los usuarios sobre lo que está pasando, con feedback apropiado y en tiempo razonable.

- **Estado actual**: **Parcialmente**
  - **Cumplimos**: hay indicadores de carga (spinner en botón Enviar) y la UI del chat avanza por mensajes.
  - **Falta/mejorable**: feedback consistente en submits de mini-UIs (Confirmar) y estados intermedios (p. ej. cargando lista, cargando autocompletes) con mensajes claros.

## 2) Coincidencia entre el sistema y el mundo real

El sistema debe hablar el idioma del usuario (palabras, frases y conceptos familiares) y seguir convenciones del mundo real.

- **Estado actual**: **Parcialmente**
  - **Cumplimos**: los flows usan lenguaje conversacional (“Elegí…”, “Ahora…”) y nomenclatura del dominio (efector, servicio, profesional, turno).
  - **Falta/mejorable**: evitar términos internos en UI/errores (p. ej. `id_rr_hh`, “ui_definition”, “draft”) y normalizar mensajes de validación para que suenen naturales.

## 3) Control y libertad del usuario

Los usuarios suelen equivocarse y necesitan una “salida de emergencia” (deshacer, cancelar, volver atrás) sin pasar por procesos largos.

- **Estado actual**: **Parcialmente / No**
  - **Cumplimos**: en listados bloqueamos re-selección luego de confirmar (evita accidentes).
  - **Falta/mejorable**: falta una acción clara de “Cancelar flow” / “Reiniciar” / “Volver al paso anterior” a nivel conversacional y UI.

## 4) Consistencia y estándares

Los usuarios no deberían preguntarse si diferentes palabras, situaciones o acciones significan lo mismo. Seguir convenciones de plataforma.

- **Estado actual**: **Parcialmente**
  - **Cumplimos**: contrato unificado `ui_json + blocks` en backend y renderers (web/Flutter) alinea comportamiento.
  - **Falta/mejorable**: estandarizar UI/labels (Confirmar vs Continuar), estilos de mensajes de error, y consistencia entre web y móvil en autocompletes/filtros.

## 5) Prevención de errores

Mejor que buenos mensajes de error es un diseño cuidadoso que prevenga el problema.

- **Estado actual**: **Parcialmente**
  - **Cumplimos**: bloqueo de selección post-confirmación en listas; flujos guiados reducen inputs libres.
  - **Falta/mejorable**: validaciones en cliente (p. ej. requeridos), defaults razonables, deshabilitar Confirmar hasta que haya datos válidos en formularios.

## 6) Reconocimiento mejor que recuerdo

Minimizar la carga de memoria mostrando opciones, contexto y acciones visibles.

- **Estado actual**: **Sí (mayormente)**
  - **Cumplimos**: listas de selección, “Atajos”, chips/tabs en pasos del flow, historial de chat preservado.
  - **Falta/mejorable**: resúmenes del “draft” visible para el usuario (qué eligió) antes de confirmar acciones críticas.

## 7) Flexibilidad y eficiencia de uso

Aceleradores para usuarios expertos sin perjudicar a principiantes.

- **Estado actual**: **Parcialmente**
  - **Cumplimos**: “Atajos” por RBAC para iniciar flows; el chat permite lenguaje natural.
  - **Falta/mejorable**: comandos rápidos (p. ej. “/cancelar”, “/reiniciar”), autocompletado de texto, y reuso de selecciones previas.

## 8) Diseño estético y minimalista

Los diálogos no deben contener información irrelevante o raramente necesaria.

- **Estado actual**: **Sí (mayormente)**
  - **Cumplimos**: se removieron mensajes de éxito redundantes; se prioriza texto conversacional y UIs inline.
  - **Falta/mejorable**: asegurar que “Atajos” no sature; curar categorías y títulos para evitar ruido.

## 9) Ayudar a los usuarios a reconocer, diagnosticar y recuperarse de errores

Los mensajes deben ser claros, en lenguaje simple, indicar el problema y sugerir solución.

- **Estado actual**: **Parcialmente**
  - **Cumplimos**: se muestra `message` del backend en varios casos y se evitó el prefijo “Exception”.
  - **Falta/mejorable**: mensajes por campo y no solo `_error`, resaltar inputs con error, y sugerir acción (“Elegí una fecha de inicio”).

## 10) Ayuda y documentación

Aunque idealmente el sistema sea usable sin documentación, puede ser necesario ofrecer ayuda.

- **Estado actual**: **Parcialmente**
  - **Cumplimos**: hay documentación técnica de contratos (`ui_json`, YAML intents, atajos).
  - **Falta/mejorable**: ayuda orientada a usuario final dentro del asistente (ejemplos, “qué puedo hacer”, tips contextuales por paso).


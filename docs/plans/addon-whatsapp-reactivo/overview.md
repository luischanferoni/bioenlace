# Overview — Add-on WhatsApp reactivo

## Objetivo

Habilitar un canal de WhatsApp **solo reactivo** para que el paciente consulte turnos e información y ejecute trámites simples con el asistente, con identidad verificada y step-up biométrico antes de cualquier escritura. El add-on se contrata opcionalmente por efector.

## Flujo objetivo

1. El paciente escribe al número de Bioenlace.
2. Si el `wa_id` no está vinculado, el sistema responde con un enlace opaco a una página de verificación. No revela nombre ni datos del paciente.
3. En la página, el paciente se verifica con el método disponible: huella local de la app, passkey o Didit.
4. Si la identidad coincide con una Persona registrada, el número queda vinculado. Si no coincide o no existe cuenta, se explica que use su propio número o instale la app.
5. Las consultas de lectura (turnos, información, estado de trámite) se responden por el asistente.
6. Antes de un alta, baja o modificación, el asistente pide un step-up. Se abre un enlace, el paciente verifica con huella, passkey o Didit, y vuelve a WhatsApp con un token de un solo uso.
7. El sistema compara la identidad verificada con la Persona vinculada. Si no coincide, no ejecuta y explica el motivo.
8. La operación se ejecuta una sola vez, con auditoría y TTL corto.

## Alcance

- Vinculación de número con verificación fuerte.
- Lecturas por asistente: turnos, información, estado de trámites.
- Escrituras: reservar, cancelar, reprogramar y trámites equivalentes del paciente.
- Step-up con passkey, huella local de la app o Didit.
- Add-on opcional con costo de Meta y Didit.
- Número compartido de Bioenlace.
- Auditoría, rate limit, expiración de vínculos y consentimiento.

## Fuera de alcance

- Plantillas utility, marketing o authentication. No hay mensajes proactivos.
- Datos clínicos: resultados, diagnósticos, medicación, resumen de atención.
- Alta de paciente nuevo desde WhatsApp.
- Atención de terceros o representación legal por WhatsApp.
- Chat con personal de salud: el paciente solo habla con el asistente.
- Sustituir el login de la app o de la web.

## Decisiones de producto

| Tema | Decisión |
|------|----------|
| Alcance del canal | Solo reactivo; el paciente inicia la conversación |
| Plantillas | No se usan; todo ocurre dentro de la ventana de 24 h |
| Contenido | Sin datos clínicos; solo turnos, información y trámites |
| Identidad inicial | Verificación fuerte con huella, passkey o Didit; nunca "SI" |
| Escrituras | Step-up inmediatamente antes de ejecutar; token de un solo uso |
| TTL | Corto: 10 minutos o consumo, lo que ocurra primero |
| Método preferido | Huella local de la app, luego passkey, luego Didit |
| Número | Uno compartido de Bioenlace |
| Cobro | Add-on opcional por efector, con Meta y Didit incluidos |
| Chat del paciente | Es del asistente, no del personal de salud; no depende de la clase de atención |

## Glosario mínimo

| Término | Qué es |
|---------|--------|
| **Plantilla utility** | Mensaje pre-aprobado por Meta para avisos transaccionales que la empresa envía sin que el paciente escriba primero. No se usa en este plan. |
| **Ventana de 24 h** | Período que abre cada mensaje del paciente y habilita respuestas libres del asistente. |
| **Step-up** | Verificación adicional inmediatamente antes de una operación sensible. |
| **TTL** | Tiempo de validez de la verificación; al vencer se pide de nuevo. |
| **Assurance** | Nivel de confianza de la sesión: lectura simple o escritura verificada. |

## Riesgos principales

| Riesgo | Mitigación |
|--------|------------|
| Número reciclado o compartido | Verificación inicial + step-up antes de escribir |
| Suplantación | Comparar documento/referencia verificada con la Persona vinculada |
| Fuga de datos en el prompt | No revelar nombre ni datos antes de verificar |
| Costo de Didit | Passkey y huella local primero; Didit como fallback |
| Costo de Meta | Solo reactivo, sin plantillas; medir mensajes service |
| Vínculos viejos creados con "SI" | Mantener lecturas y exigir step-up en la primera escritura; migración gradual |

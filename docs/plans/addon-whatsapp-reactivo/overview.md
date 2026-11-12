# Overview — Add-on WhatsApp reactivo

## Objetivo

Habilitar un canal de WhatsApp **solo reactivo** para que el paciente consulte turnos e información con el asistente, con identidad verificada al vincular el número. Las escrituras se completan en la app, donde la huella local o la passkey hacen el step-up justo antes de ejecutar. El add-on se contrata opcionalmente por efector.

## Flujo objetivo

1. El paciente escribe al número de Bioenlace.
2. Si el `wa_id` no está vinculado, el sistema responde con un enlace opaco a una página de verificación. No revela nombre ni datos del paciente.
3. En la página, el paciente se verifica con Didit.
4. Si la identidad coincide con una Persona registrada, el número queda vinculado. Si no coincide o no existe cuenta, se explica que use su propio número o instale la app.
5. Las consultas de lectura permitidas (turnos, información) se responden por el asistente.
6. Si el pedido es un alta, baja o modificación, el canal **no ejecuta**: envía a la app, donde la huella/passkey hace el step-up y la operación se completa.

## Alcance

- Vinculación de número con verificación fuerte.
- Lecturas por asistente: turnos, información, estado de trámites.
- Escrituras: reservar, cancelar, reprogramar y trámites equivalentes. WhatsApp las deriva a la app, que hace el step-up.
- Step-up con huella local o passkey en la app; Didit solo para vincular el número.
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
| Identidad inicial | Didit KYC contra una Persona existente; nunca "SI" |
| Escrituras | No se ejecutan en WhatsApp: se abren en la app, que exige huella/passkey |
| TTL | No aplica dentro de WhatsApp; la app maneja su sesión biométrica |
| Método preferido | Huella local o passkey en la app; Didit solo para el vínculo inicial |
| Número | Uno compartido de Bioenlace |
| Cobro | Add-on opcional por efector, con Meta y Didit incluidos. **El bloqueo de efectores sin add-on queda pendiente.** |
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

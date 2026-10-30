# Overview — Alta de cuenta móvil con identidad Didit

## Problema

El alta institucional actual es un formulario web self-service (`institucional/alta.html`) que mezcla tres cosas que son distintas: la identidad de una persona, la creación de una organización/efector y el pago. Además:

- el email no se verifica (`email_confirmed = 1` automático en el alta de efector);
- el documento solo se valida por longitud;
- la ubicación no queda estructurada (el front no envía `id_localidad`);
- el teléfono no se valida ni se detecta país;
- se exige contraseña, en contra del objetivo de login biométrico;
- el profesional que trabaja en una organización ajena no tiene un camino claro: tras el login debe "seleccionar efector" y, si no tiene ninguno, queda bloqueado.

## Objetivo

Reemplazar el alta web por un modelo **identidad-primero** en la app Personal de Salud:

- la identidad se verifica con Didit (KYC compartido con paciente);
- la cuenta de identidad no requiere contraseña ni pago;
- el efector es una capa posterior: lo agrega un AdminEfector (pool) o lo crea el propio usuario pagando (consultorio/clínica);
- el ministerio es asistido por admin, no self-service;
- el login es huella/FaceID local, con re-verificación Didit en dispositivo nuevo.

## Alcance

### Entradas de alta

- **Clínica/centro** (privado o público): identidad + efector + plan + pago (privado) o cobertura ministerial (público).
- **Consultorio profesional**: identidad + validación REFEPS/SISA + efector + plan ambulatorio + pago.
- **Profesional de una organización**: identidad + alta por el AdminEfector de la organización (sin pago).
- **Ministerio**: alta asistida por admin + token link + KYC en la app (sin pago).

### Decisiones de producto cerradas

- Login biométrico local únicamente; se elimina "Activar cuenta por código" y no hay magic link de login.
- Dispositivo nuevo/reinstalación: re-verificación de identidad con Didit y vínculo del nuevo dispositivo.
- Teléfono opcional validado E.164.
- Geo-gating AR + UY.
- Ubicación con mapa (país/provincia/departamento/localidad + pin).
- Workflow KYC Didit compartido entre paciente y personal de salud.

## Fuera de alcance inicial

- Pasarela de pago real (hoy es simulada; se sustituye sin cambiar el contrato de alta).
- Autenticación WebAuthn/passkey del dispositivo (el "Face ID" es huella local del dispositivo + Didit para re-verificación).
- Backfill de efectores existentes al nuevo modelo.
- App paciente (no cambia su flujo de registro).

## Resultados esperados

1. Un único paso de identidad reutilizado por paciente, profesional y admin.
2. Alta de efector sin exigir contraseña ni verificar email en caliente.
3. Cuenta de identidad que puede existir sin efector y sin pago.
4. Pantalla de selección de efector con salida clara para el profesional sin organización.
5. Alta de ministerio asistida, trazable y sin pago.
6. Alta web desarmada y navegación institucional actualizada.

## Métricas de éxito del programa

- Altas que completan KYC sin fricción de contraseña.
- Profesionales que llegan a operar sin quedar bloqueados en la selección de efector.
- Altas de consultorio con pago diferido respecto de la creación de identidad.
- Errores de ubicación (efector con localidad default en vez de la real).
- Solicitudes de ministerio tramitadas por admin.

## Criterio de producto

Ningún usuario puede operar sin identidad verificada. La identidad nunca se pierde ni se bloquea por no tener efector. El pago solo existe en el camino "creo mi propio efector"; unirse a una organización nunca exige pagar.

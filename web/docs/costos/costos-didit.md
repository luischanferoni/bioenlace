# Costos — Didit (identidad y biometría)

**Tipo:** costos · API externa (identidad)  
**Última actualización:** 2026-07-02  
**Alcance:** verificación de identidad (KYC) y autenticación biométrica remota en app paciente, alta de paciente por staff y login biométrico vía API. **No** incluye huella/Face ID **local** del dispositivo (costo cero).

Didit es **COGS de identidad**: se factura **por sesión exitosa**, no por profesional ni por consulta. No está mezclado con las tablas por médico de [costos-api.md](./costos-api.md); conviene presupuestarlo aparte según **altas de pacientes** y **reingresos** con Didit.

---

## Tarifas de referencia (julio 2026)

Fuente pública: [didit.me/pricing](https://didit.me/pricing). Validar cada 6–12 meses; Enterprise puede tener tarifas distintas.

| Concepto | Precio (USD) | Notas |
|----------|--------------|--------|
| **Cupo mensual gratis** | **500 sesiones / workspace / mes** | Permanente; no acumula; reset día 1 (UTC) |
| **Full KYC bundle** | **0,33** por sesión exitosa | ID + liveness pasivo + face match + IP/dispositivo |
| **Biometric Authentication** | **0,10** por sesión exitosa | Selfie + liveness + face match (reingreso, sin re-KYC documento) |
| Sandbox / pruebas | **0** | No consume el cupo de producción |
| Sesiones abandonadas o rechazadas | **0** | Según documentación Didit (pay-per-success) |

Módulos sueltos (referencia): ID Verification 0,15 · Passive Liveness 0,10 · Face Match 1:1 0,05 · Device & IP 0,03. El **bundle KYC** (0,33) suele ser más barato que sumar módulos por separado para onboarding completo.

---

## Flujos en Bioenlace que consumen Didit

| Flujo | Producto | Workflow / API | Tipo Didit | Frecuencia típica |
|-------|----------|----------------|------------|-------------------|
| Autoregistro paciente (app) | App paciente | KYC (`didit_paciente_kyc_workflow_id`) | Full KYC | **1 vez** por paciente nuevo |
| Alta paciente por staff (modo Didit) | Asistente / web | KYC | Full KYC | **1 vez** por alta |
| Reingreso tras cerrar sesión o dispositivo nuevo | App paciente (previsto) | `POST /api/v1/auth/login-biometrico` | Biometric Auth | **Cada reingreso** que use Didit |
| Login diario / bloqueo por inactividad | App paciente / Personal | Huella local del teléfono | — | **Sin Didit** |
| Registro médico (si aplica) | App / API | KYC médico | Full KYC | 1 vez por médico nuevo |

Hoy en producción móvil paciente: **solo el registro** dispara Didit de forma sistemática. El login biométrico remoto está en backend y en `LoginScreen` compartido, pero la app paciente lo tiene desactivado (`diditBiometricWorkflowId: null`).

---

## Supuestos de las proyecciones

Las tablas usan escenarios **ilustrativos** para planificar; calibrar con contador de sesiones en Didit Business Console.

| Parámetro | Valores usados |
|-----------|----------------|
| Cupo gratis | 500 sesiones / mes |
| KYC (registro / alta) | 0,33 USD por sesión por encima del cupo |
| Reingreso Didit | 0,10 USD por sesión por encima del cupo |
| Tasa de cierre de sesión | **5 %** de pacientes activos / mes (conservador) |
| Tasa alternativa «alta rotación» | **15 %** de pacientes activos / mes |
| Altas staff con Didit | **10 %** de las altas mensuales de pacientes (orden de magnitud) |

**Importante:** registro KYC y reingreso biométrico **comparten el mismo cupo** de 500 sesiones en el workspace Didit.

---

## Escenario A — Solo KYC (diseño recomendado para costo)

Registro y altas con Didit; uso diario con **huella local**; reingreso tras logout con **usuario/contraseña** o Didit solo si no hay biometría en el dispositivo.

### Altas mensuales (KYC)

| Pacientes nuevos / mes (app + staff Didit) | Sesiones KYC | Dentro de 500 gratis | Costo Didit (USD/mes) |
|------------------------------------------|--------------|----------------------|------------------------|
| 200 | 200 | 200 | **0** |
| 500 | 500 | 500 | **0** |
| 750 | 750 | 500 + 250 pagas | **~83** |
| 1.000 | 1.000 | 500 + 500 pagas | **~165** |
| 2.000 | 2.000 | 500 + 1.500 pagas | **~495** |
| 5.000 | 5.000 | 500 + 4.500 pagas | **~1.485** |
| 10.000 | 10.000 | 500 + 9.500 pagas | **~3.135** |

Fórmula: `max(0, altas_kyc − 500) × 0,33`.

### Costo por paciente nuevo (solo KYC, margen)

| Volumen mensual de altas | Costo medio por alta (USD) |
|--------------------------|----------------------------|
| ≤ 500 | **0** |
| 1.000 | **~0,17** |
| 2.000 | **~0,25** |
| 5.000 | **~0,30** |
| 10.000 | **~0,31** |

---

## Escenario B — KYC + reingreso Didit tras cerrar sesión

Supuesto: quien cierra sesión vuelve a entrar con **Biometric Authentication** (0,10), no con KYC completo.

### Por tamaño de base activa (5 % cierran sesión / mes)

| Pacientes activos | Reingresos Didit / mes (5 %) | Costo reingreso solo (USD/mes)* |
|-------------------|------------------------------|----------------------------------|
| 2.000 | 100 | **0** |
| 10.000 | 500 | **0** |
| 20.000 | 1.000 | **~50** |
| 50.000 | 2.500 | **~200** |
| 100.000 | 5.000 | **~450** |
| 200.000 | 10.000 | **~950** |

\* `max(0, reingresos − remanente_del_cupo_gratis) × 0,10`. Si en el mismo mes ya se consumieron las 500 gratis en KYC, el reingreso paga desde la sesión 501.

### Tasa alta de cierre de sesión (15 % / mes, 50.000 activos)

| Concepto | Sesiones / mes | Costo (USD/mes) aprox. |
|----------|----------------|-------------------------|
| Reingresos Didit (15 %) | 7.500 | **~700** |
| + 2.000 altas KYC en el mismo mes | 2.000 KYC + 7.500 bio | Ver tabla combinada abajo |

---

## Escenario C — Totales combinados (KYC + reingreso)

Ejemplos **mensuales** con cupo compartido de 500 sesiones gratis.

| Altas KYC / mes | Pacientes activos | Reingresos Didit (5 %) | Total sesiones | Costo Didit (USD/mes) |
|-----------------|-------------------|------------------------|----------------|------------------------|
| 300 | 5.000 | 250 | 550 | **~17** |
| 500 | 10.000 | 500 | 1.000 | **~50** |
| 800 | 15.000 | 750 | 1.550 | **~113** |
| 1.000 | 20.000 | 1.000 | 2.000 | **~155** |
| 2.000 | 50.000 | 2.500 | 4.500 | **~470** |
| 3.000 | 100.000 | 5.000 | 8.000 | **~865** |
| 5.000 | 150.000 | 7.500 | 12.500 | **~1.490** |

Cálculo: las primeras **500** sesiones del mes (KYC + biométrico mezclados) a **0**; el resto a **0,33** si es KYC y **0,10** si es reingreso biométrico. La tabla asume orden típico «altas primero, luego reingresos»; el costo real puede variar ±10 % según el mix diario.

---

## Escenario D — Didit en cada apertura de app (no recomendado)

Si cada paciente abriera la app **20 veces / mes** con Didit en lugar de huella local:

| Pacientes activos | Sesiones / mes | Costo (USD/mes) aprox. |
|-------------------|----------------|-------------------------|
| 1.000 | 20.000 | **~1.950** |
| 5.000 | 100.000 | **~9.950** |
| 10.000 | 200.000 | **~19.950** |

Escala mal frente a huella local (0 USD). Este escenario sirve como **techo** a evitar en diseño de producto.

---

## Comparación con COGS IA (referencia)

[resumen-costos-bioenlace.md](./resumen-costos-bioenlace.md): **~USD 5.400–6.900 / mes** para **5.000 profesionales** (IA + STT, sin videollamada).

| Métrica | IA (5.000 prof.) | Didit (ejemplo) |
|---------|------------------|-----------------|
| Unidad de cobro | Por profesional / mes | Por verificación exitosa |
| Orden de magnitud «arranque» | Miles USD/mes a escala | **0 USD** si &lt; 500 verificaciones/mes |
| Orden de magnitud «muchas altas» | Sigue ~USD 1,4/prof | **~USD 1.500/mes** con ~5.000 altas KYC |

Didit no compite en la misma curva que Gemini: conviene **línea de presupuesto separada** «identidad / onboarding».

---

## Palancas para mantener el costo bajo

| Palanca | Efecto |
|---------|--------|
| KYC **solo en registro** (y alta staff cuando haga falta) | Una sesión 0,33 por vida de cuenta en ese dispositivo |
| Huella local obligatoria para sesión activa | 0 USD Didit en el día a día |
| Reingreso tras logout con **DNI + contraseña** | 0 USD Didit; requiere definir contraseña en onboarding |
| Reingreso Didit solo sin biometría en dispositivo | 0,10 por evento; acotado |
| No usar Didit en cada cold start de la app | Evita escenario D |
| Monitorear Business Console Didit | Alertas al acercarse a 500 sesiones/mes |
| Reusable KYC (red Didit) | Posible ahorro futuro si el paciente ya verificó en otro cliente Didit (módulo publicado como free en red; validar elegibilidad) |

Detalle de producto: [registro-paciente.md](../producto/registro-paciente.md), [apps-paciente-personalsalud.md](../producto/apps-paciente-personalsalud.md).

---

## Resumen ejecutivo

| Pregunta | Respuesta orientativa |
|----------|------------------------|
| ¿Los 500 gratis alcanzan? | Sí para **piloto y primeros meses** (&lt; 500 altas + pocos reingresos Didit / mes) |
| ¿Cuánto cuesta cada paciente nuevo? | **0 USD** dentro del cupo; después **~0,17–0,33 USD** según volumen mensual |
| ¿Cuánto cuesta reingreso con Didit? | **~0,10 USD** por sesión por encima del cupo |
| ¿Riesgo principal? | Usar Didit como **login habitual** en lugar de huella local |
| ¿Diseño más económico? | KYC una vez + huella local + contraseña como respaldo |

---

## Referencias

- [costos-api.md](./costos-api.md) — COGS IA, STT, Vision, videollamada
- [resumen-costos-bioenlace.md](./resumen-costos-bioenlace.md) — totales IA sin jerga técnica
- [impuestos-argentina.md](./impuestos-argentina.md) — IVA en compras a proveedor extranjero
- [registro-paciente.md](../producto/registro-paciente.md) — flujos de alta paciente
- [Didit pricing](https://didit.me/pricing) — tarifas públicas

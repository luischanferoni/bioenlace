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
| Reingreso tras cerrar sesión o dispositivo nuevo | App paciente | `POST /api/v1/auth/login-biometrico` | Biometric Auth | **Cada reingreso** que use Didit |
| Login diario / bloqueo por inactividad | App paciente / Personal | Huella local del teléfono | — | **Sin Didit** |
| Registro médico (si aplica) | App / API | KYC médico | Full KYC | 1 vez por médico nuevo |

En producción móvil paciente: **registro** usa KYC; **reingreso tras cerrar sesión** usa Didit remoto (`diditRemoteLoginAfterLogout` en `LoginScreen`). El workflow biométrico se resuelve vía `GET /api/v1/registro/config-movil`.

### Diseño de producto (no es login diario)

| Momento | ¿Didit? |
|---------|---------|
| Abrir la app cada día | **No** — huella local del teléfono |
| Bloqueo por inactividad (~5 min) | **No** — huella local |
| Registro (primera vez) | **Sí** — KYC completo |
| Volver tras **cerrar sesión** o **teléfono nuevo** | **Sí** — biometría remota (o contraseña si está implementada) |

Didit **no** debe usarse en cada apertura de la app. El escenario D de este doc es solo un **techo** a evitar, no el diseño previsto.

---

## Supuestos de las proyecciones

Las tablas usan escenarios para planificar; calibrar con contador de sesiones en Didit Business Console y, cuando exista, telemetría de «Cerrar sesión» en la app.

| Parámetro | Valor |
|-----------|--------|
| Cupo gratis | 500 sesiones / mes (KYC + reingreso **comparten** cupo) |
| KYC (registro / alta) | 0,33 USD por sesión por encima del cupo |
| Reingreso Didit (tras logout) | 0,10 USD por sesión por encima del cupo |
| Altas staff con Didit | ~10 % de las altas mensuales de pacientes (orden de magnitud) |

### Tasa de «cerrar sesión» (por mes)

**Qué significa:** del total de pacientes que **usaron la app ese mes**, qué porcentaje tocó **Cerrar sesión** al menos una vez en ese mes. No es uso diario ni aperturas de la app.

Quien cierra sesión y vuelve a entrar puede usar Didit (biometría remota) o contraseña; en las tablas de reingreso se asume **Didit** para estimar el techo de costo.

| Escenario | % activos que cierran sesión / mes | Cuándo usarlo |
|-----------|-------------------------------------|---------------|
| **Realista (base)** | **0,5 % – 1 %** | Presupuesto habitual: la mayoría no cierra sesión; lo hace quien cambia de teléfono, desinstala, comparte el dispositivo o es muy cauteloso |
| **Conservador** | **3 %** | Margen de planificación si el producto empuja más el logout o hay mucha rotación de dispositivos |
| **Estrés (no típico)** | **5 % – 15 %** | Solo para stress-test; no refleja el diseño esperado de Bioenlace |

**Ejemplo (realista):** 10.000 pacientes activos en el mes × **1 %** cierra sesión → **100 reingresos Didit / mes** (no 100 × 30 días).

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

## Escenario B — Reingreso Didit solo tras cerrar sesión

Supuesto: quien **cerró sesión** vuelve a entrar con **Biometric Authentication** (0,10 USD), no con KYC completo. **No** incluye aperturas diarias de la app (esas van con huella local).

### Tasa realista (1 % de activos cierran sesión / mes)

| Pacientes activos / mes | Reingresos Didit / mes | Costo reingreso solo (USD/mes)* |
|-------------------------|------------------------|----------------------------------|
| 5.000 | 50 | **0** |
| 10.000 | 100 | **0** |
| 25.000 | 250 | **0** |
| 50.000 | 500 | **0** |
| 100.000 | 1.000 | **~50** |
| 200.000 | 2.000 | **~150** |

### Tasa realista baja (0,5 % / mes)

| Pacientes activos / mes | Reingresos Didit / mes | Costo reingreso solo (USD/mes)* |
|-------------------------|------------------------|----------------------------------|
| 50.000 | 250 | **0** |
| 100.000 | 500 | **0** |
| 200.000 | 1.000 | **~50** |

### Margen conservador (3 % / mes) — planificación, no lo habitual

| Pacientes activos / mes | Reingresos Didit / mes | Costo reingreso solo (USD/mes)* |
|-------------------------|------------------------|----------------------------------|
| 10.000 | 300 | **0** |
| 50.000 | 1.500 | **~100** |
| 100.000 | 3.000 | **~250** |

\* `max(0, reingresos − remanente_del_cupo_gratis) × 0,10`. Si en el mismo mes las **altas KYC** ya consumieron las 500 gratis, cada reingreso adicional paga 0,10 desde la primera sesión de reingreso que supere el cupo.

**Lectura:** con **1 % o menos** de cierres de sesión y bases hasta ~50.000 activos, el reingreso Didit suele quedar en **0 USD** o ser marginal frente al costo de las **altas KYC**.

---

## Escenario C — Totales combinados (KYC + reingreso tras logout)

Ejemplos **mensuales** con cupo compartido de 500 sesiones gratis. Reingreso calculado con **1 %** de activos que cierran sesión / mes (realista).

| Altas KYC / mes | Pacientes activos | Reingresos Didit (1 %) | Total sesiones | Costo Didit (USD/mes) |
|-----------------|-------------------|------------------------|----------------|------------------------|
| 200 | 5.000 | 50 | 250 | **0** |
| 400 | 10.000 | 100 | 500 | **0** |
| 600 | 15.000 | 150 | 750 | **~83** |
| 800 | 20.000 | 200 | 1.000 | **~165** |
| 1.500 | 50.000 | 500 | 2.000 | **~363** |
| 2.500 | 100.000 | 1.000 | 3.500 | **~790** |

Cálculo: las primeras **500** sesiones del mes a **0**; el excedente de **altas** a **0,33** y el de **reingresos** a **0,10** (se asume que las altas consumen primero el cupo gratis). Variación real ±10 % según el orden de las sesiones en el mes.

### Misma tabla con 0,5 % de cierres de sesión (más optimista)

| Altas KYC / mes | Pacientes activos | Reingresos (0,5 %) | Total sesiones | Costo (USD/mes) |
|-----------------|-------------------|--------------------|----------------|-----------------|
| 400 | 10.000 | 50 | 450 | **0** |
| 800 | 50.000 | 250 | 1.050 | **~182** |
| 1.500 | 100.000 | 500 | 2.000 | **~495** |

En la práctica, **las altas KYC dominan el presupuesto**; el reingreso tras logout aporta poco mientras la tasa de cierre de sesión sea baja (0,5–1 % / mes).

---

## Escenario D — Didit en cada apertura de app (anti‑patrón)

**No es el diseño de Bioenlace.** Si cada paciente abriera la app **20 veces / mes** con Didit en lugar de huella local:

| Pacientes activos | Sesiones / mes | Costo (USD/mes) aprox. |
|-------------------|----------------|-------------------------|
| 1.000 | 20.000 | **~1.950** |
| 5.000 | 100.000 | **~9.950** |
| 10.000 | 200.000 | **~19.950** |

Escala mal frente a huella local (0 USD). Documentado solo como **techo** si el producto se desviara del diseño acordado.

### Anexo estrés — 5 % cierran sesión / mes (no típico)

Útil solo para preguntar «¿qué pasa si muchísima gente cierra sesión?». Con 50.000 activos y **5 %** → 2.500 reingresos/mes → **~200 USD/mes** solo en reingreso (sin contar altas). Con el diseño real (huella diaria + Didit solo tras logout), **no se espera** esta tasa.

---

## Comparación con COGS IA (referencia)

[resumen-costos-bioenlace.md](./resumen-costos-bioenlace.md): **~USD 11.800–18.250 / mes** para **5.000 profesionales** (IA + STT, sin videollamada).

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

Detalle de producto: [registro-paciente.md](../producto/registro-paciente.md), [sesion-paciente-app.md](../producto/sesion-paciente-app.md), [apps-paciente-personalsalud.md](../producto/apps-paciente-personalsalud.md).

---

## Resumen ejecutivo

| Pregunta | Respuesta orientativa |
|----------|------------------------|
| ¿Didit en el día a día? | **No** — huella local; Didit solo en **registro** y **reingreso tras cerrar sesión** (o sin biometría en el dispositivo) |
| ¿Los 500 gratis alcanzan? | Sí en piloto y etapa inicial: pocas altas + pocos cierres de sesión (**0,5–1 % / mes** de activos) |
| ¿Qué cuesta cada paciente nuevo? | **0 USD** dentro del cupo; después **~0,17–0,33 USD** según volumen mensual de altas |
| ¿Qué cuesta reingreso tras logout? | **~0,10 USD** por sesión por encima del cupo; con **1 %** de cierres/mes suele ser **marginal** |
| ¿Qué domina el presupuesto? | **Altas KYC**, no los reingresos |
| ¿Diseño más económico? | KYC una vez + huella local + contraseña como respaldo al logout |

---

## Referencias

- [costos-api.md](./costos-api.md) — COGS IA, STT, Vision, videollamada
- [resumen-costos-bioenlace.md](./resumen-costos-bioenlace.md) — totales IA sin jerga técnica
- [impuestos-argentina.md](./impuestos-argentina.md) — IVA en compras a proveedor extranjero
- [registro-paciente.md](../producto/registro-paciente.md) — flujos de alta paciente
- [Didit pricing](https://didit.me/pricing) — tarifas públicas

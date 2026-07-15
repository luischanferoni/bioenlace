# Impuestos — Argentina (referencia para costos y pricing)

**Tipo:** costos · fiscal (orientativo)  
**Última actualización:** 2026-07-10  
**Alcance:** impacto fiscal al **operar** Bioenlace en Argentina y al **cotizar** B2B (efector, financiador, Estado). **No es asesoramiento legal ni contable** — validar con contador según forma societaria, provincia y tipo de factura.

Los precios de la [matriz Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) están en USD y **no incluyen IVA**. Fórmula de lista: COGS × (1 + margen sobre costo).

---

## Supuestos de esta referencia

| Supuesto | Valor usado en tablas |
|----------|------------------------|
| Forma societaria | SRL o SA, **Responsable Inscripto** (IVA) |
| Actividad | Servicios de software / SaaS (código AFIP según contrato real) |
| Facturación | Clientes en Argentina (B2B); precios de referencia en **USD** |
| Ingresos brutos (IIBB) | **3%–5%** sobre facturación bruta (promedio ilustrativo; varía por jurisdicción y convenio multilateral) |
| Ganancias (imp. cédulo) | **25%** sobre utilidad impositiva anual (equivalente mensual en ejemplos) |
| IVA | **21%** (alícuota general servicios) |
| Uso de IA (escala 5.000 prof.) | Intensivo, motivos con audio — COGS por prof en [costos-api.md](./costos-api.md#resumen-costo-real-por-api-por-médico-por-mes) |

**No cubre:** monotributo, exportación de servicios con tratamiento especial, retenciones en licitación pública, percepciones aduaneras en detalle, impuesto PAIS en cada operación, ni convenios impositivos provincia por provincia.

---

## Tres capas (no mezclar)

| Capa | Qué es | Dónde impacta |
|------|--------|----------------|
| **1. Costo directo** | IA, APIs, infra, nube, **identidad (Didit)** | [costos-api.md](./costos-api.md), [costos-didit.md](./costos-didit.md), [infra-costos.md](./infra-costos.md) |
| **2. Impuestos sobre compras** | IVA (y a veces percepciones) en facturas de proveedores | Caja y crédito fiscal si sos RI |
| **3. Impuestos sobre ventas y resultado** | IIBB, ganancias; IVA en factura al cliente | Pricing y margen neto |

La documentación de **costos técnicos** (capas 1–2 parcial) no sustituye el estudio de la capa 3 con contador.

---

## IVA (21%)

### En facturación al cliente (venta)

- Factura **B** o **A** con IVA discriminado: el cliente B2B suele tomar el IVA como **crédito fiscal** (no es ingreso para Bioenlace).
- El precio “lista” de la matriz es **neto de IVA**; al cotizar en ARS/USD para un cliente local, aclarar: *más IVA 21%*.

### En compras (API, cloud, servicios)

| Tipo de proveedor | Efecto típico (RI) |
|-------------------|-------------------|
| Proveedor **local** con factura A/B | Pagás IVA; **crédito fiscal** → impacto en caja neto bajo si se compensa en DDJJ |
| Proveedor **exterior** (Google Cloud, etc.) | Puede haber IVA importación de servicios / percepciones según operación; parte **no recuperable** según caso → **sí suma al costo efectivo** |

**Regla práctica para modelos:** separar **costo neto proveedor** y una fila **“IVA / percepciones no recuperables”** solo si el contador estima un % sobre compras en USD.

---

## Ingresos brutos (IIBB)

- Impuesto **provincial** (y convenio multilateral si facturás en varias provincias).
- Base: **ingresos brutos** por la actividad (facturación), no por el costo de IA.
- Para **armar precio** hacia el cliente:  
  `precio neto objetivo × (1 + alícuota IIBB estimada)` o incluir IIBB en el margen deseado.

Alícuota **orientativa** en tablas: **3%–5%** del monto facturado (validar en CABA / provincia del cliente y del domicilio fiscal).

---

## Impuesto a las ganancias

- Aplica sobre **utilidad** (ingresos − costos deducibles − amortizaciones), no sobre facturación bruta.
- Tasa de referencia en ejemplos: **25%** (sociedades; puede variar por régimen y año fiscal).
- En un modelo mensual:  
  `ganancias estimada ≈ max(0, facturación − costo directo − gastos fijos − IIBB − otros) × 25%`

No se prorratea de forma exacta sin balance; las tablas siguientes usan **órdenes de magnitud**.

---

## Tabla ejemplo: 5.000 profesionales (costo de servir)

Tarifas y supuestos por profesional: [costos-api.md](./costos-api.md). Aquí solo la **escala** (5.000 prof.) y el **impacto fiscal**.

### A) Costo operativo documentado (sin impuestos sobre ventas)

| Concepto | Cálculo | USD por mes |
|----------|---------|-------------|
| IA + STT — sin context caching (COGS) | 5.000 x ~1,55 por prof | **~7.750** |
| IA + STT — con context caching (favorable) | 5.000 x ~1,41 por prof | **~7.050** |
| Videollamada §6 (COGS planificado) | 5.000 x 3,00 por prof | **~15.000** |
| **IA + STT + videollamada** — sin caché | suma filas anteriores | **~22.750** |
| **IA + STT + videollamada** — con caché | 7.050 + 15.000 | **~22.050** |
| Aplicación + BD + hosting | *[pendiente presupuesto]* | — |

**Infra app:** clínica de **20 profesionales** → «Infra + soporte» **USD 200–500 por mes** en unit economics ([modelos-pricing](../modelo-de-negocio/business-plan/modelos-pricing-diferenciados.md)); **no escala lineal** a 5.000 usuarios.

**WhatsApp:** solo asistente **iniciado por el paciente** (Meta service ≈ $0; utility **no habilitada**). Detalle: [costos-api §7](./costos-api.md#7-whatsapp-cloud-api-paciente).

**`infra-costos.md`** modela **GPU propia para inferencia**, no hosting Yii/API. Con IA por API (tabla de arriba), **no sumar** GPU de ese doc salvo inferencia on-prem.

### B) Carga fiscal sobre compras (estimación conservadora)

Base: filas **IA + STT** de la tabla A (~**7.750** sin caché · ~**7.050** con caché). Estrategias de [estrategias-reduccion/](./estrategias-reduccion/README.md) no están en estas cifras.

| Concepto | Sin caché (COGS) | Con caché (favorable) | Notas |
|----------|------------------|----------------------|--------|
| Subtotal IA + STT (tabla A) | ~7.750 | ~7.050 | Motivos con audio; §2 incluye batch + insights; §4 incluye STT |
| IVA 21 % compras (crédito pleno) | +1.463 | +1.330 | RI: **caja ≈ 0** a neto |
| IVA / percepciones no recuperables | 0 – 1.131 | 0 – 1.028 | Peor caso sobre subtotal |
| **Costo IA efectivo (peor caso)** | **~7.750 – 8.881** | **~7.050 – 8.078** | Sin crédito o con percepciones |
| **Costo IA efectivo (RI, normal)** | **~7.750** | **~7.050** | |

**Resumen fiscal (compras):** App/BD *[pendiente]*.

### C) Cotización orientativa — variable IA (5.000 prof., uso intensivo)

**Lista comercial vigente:** [matriz Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — `precio = COGS × (1 + margin_on_cost_percent/100)` con margen **233 %** (~70 % bruto). COGS de esta sección = tabla A / [costos-api](./costos-api.md).

#### Qué es cada margen (importante)

| Métrica | Fórmula | ¿Incluye ganancias? |
|---------|---------|---------------------|
| **Margen bruto (*gross margin*)** | `(Precio neto − costo IA) / Precio neto` | **No.** Solo resta el costo directo documentado (IA+STT±video). Es la métrica del business plan (~70 % objetivo en software). |
| **Margen sobre costo (*markup*)** | `(Precio − COGS) / COGS` | **No.** En metadata: `margin_on_cost_percent` (233 % → bruto ~70 %). |
| **Margen después IIBB + ganancias** | `(Precio − IIBB − costo IA − ganancias) / Precio` | **Sí.** IIBB **4 %** s/ facturación neta; ganancias **25 %** s/ utilidad antes de imp. a las ganancias (`utilidad = precio − IIBB − costo IA`). |
| **IVA 21 %** | Sobre factura al cliente | **No entra** en ningún margen: se discrimina; el cliente lo usa como crédito fiscal (B2B). |

#### Tabla — solo IA + STT (sin videollamada)

| Escenario | ~USD por prof sin caché | ~USD por prof con caché | Precio **neto** 5.000 prof sin caché | Precio **neto** 5.000 prof con caché | Factura **+ IVA 21 %** sin caché | Factura **+ IVA 21 %** con caché | Margen bruto | Margen después IIBB + ganancias |
|-----------|-------------------------|-------------------------|--------------------------------------|--------------------------------------|----------------------------------|----------------------------------|--------------|--------------------------------|
| **Solo costo (sin margen)** * | **~1,55** | **~1,41** | **~7.750** | **~7.050** | **~9.378** | **~8.531** | **0 %** · **0 %** | **Pérdida** |
| **Lista matriz (~70 % bruto)** † | **~5,16** | **~4,70** | **~25.800** | **~23.500** | **~31.218** | **~28.435** | **~70 %** | Ver IIBB+ganancias sobre ese neto |
| **Variable IA, margen mínimo (histórico)** | **1,8 – 2,0** | **1,8 – 2,0** | **9.000 – 10.000** | **9.000 – 10.000** | **10.890 – 12.100** | **10.890 – 12.100** | **~42 – 48 %** · **~56 – 61 %** * | **~28 – 33 %** · **~40 – 43 %** * |

\* COGS sin caché **~7.750** · favorable con caché **~7.050** (tabla A). Por prof: [costos-api resumen](./costos-api.md#resumen-costo-real-por-api-por-médico-por-mes), motivos con audio (§2 batch + insights; §4 siempre IA+STT).  
† `1,55 × 3,33 ≈ 5,16` (margen sobre costo 233 %). Lista comercial / metadata usa COGS **con caché**: `1,41 × 3,33 ≈ 4,70` (motivos con audio) o base `0,83 × 3,33 ≈ 2,76`.

#### Tabla — IA + STT + videollamada (COGS planificado §6)

| Escenario | ~USD por prof sin caché | ~USD por prof con caché | Precio **neto** 5.000 prof sin caché | Precio **neto** 5.000 prof con caché | Factura **+ IVA 21 %** sin caché | Factura **+ IVA 21 %** con caché | Margen bruto |
|-----------|-------------------------|-------------------------|--------------------------------------|--------------------------------------|----------------------------------|----------------------------------|--------------|
| **Solo costo (sin margen)** * | **~4,55** | **~4,41** | **~22.750** | **~22.050** | **~27.528** | **~26.681** | **0 %** |
| **Lista matriz (~70 % bruto)** † | **~15,15** | **~14,69** | **~75.750** | **~73.450** | **~91.658** | **~88.875** | **~70 %** |

\* Tabla A + videollamada ([costos-api §6](./costos-api.md#6-videollamadas-pacientemédico)). En matriz comercial el COGS de referencia con audio+video es **4,11** → lista **~13,69**/prof/mes.  
† `4,55 × 3,33 ≈ 15,15` (escala 5.000 con COGS tabla A+video).

La fila «margen mínimo» histórico (1,8–2 por prof) **no cubre** videollamada ni el objetivo de ~70 % bruto; la **lista matriz** sí incluye video como add-on opcional.

#### Detalle aritmético — ejemplo lista matriz, 5.000 prof, solo IA+STT (~5,16/prof)

| Concepto | USD por mes |
|----------|---------|
| Facturación neta | 25.800 |
| IVA 21 % (discriminado en factura) | +5.418 (no es ingreso) |
| Ingresos brutos (4 %) | −1.032 |
| Costo IA + STT | −7.750 |
| Utilidad antes ganancias | 17.018 |
| Ganancias (25 %) | −4.255 |
| **Resultado variable** (antes de fijos) | **12.763** |
| **Margen bruto** | **~70 %** (= (25.800 − 7.750) / 25.800) |
| **Margen después IIBB + ganancias** | **~49 %** (= 12.763 / 25.800) |

#### Detalle aritmético — ejemplo histórico USD 10.000 por mes neto (2 por prof)

| Concepto | USD por mes |
|----------|---------|
| Facturación neta | 10.000 |
| IVA 21 % (discriminado en factura) | +2.100 (no es ingreso) |
| Ingresos brutos (4 %) | −400 |
| Costo IA + STT | −7.750 |
| Utilidad antes ganancias | 2.850 |
| Ganancias (25 %) | −713 |
| **Resultado variable** (antes de fijos) | **2.137** |
| **Margen bruto** | **~28 %** (= 2.850 / 10.000) |
| **Margen después IIBB + ganancias** | **~21 %** (= 2.137 / 10.000) |

---

## Cómo usar esto en pricing

1. **Costo directo** (tabla A o B / [costos-api](./costos-api.md)) → COGS por profesional (base ± audio ± videollamada).  
2. **Aplicar margen sobre costo** (`margin_on_cost_percent` en metadata; hoy **233 %** ≈ 70 % bruto) → precio lista unitario.  
3. **× cantidad de profesionales** por clase contratada (AMB / EMER / IMP).  
4. **+ Gastos fijos** (sueldos, ventas, implementación) → no están en `docs/costos/` de APIs; ajustar margen o fee one-shot si hace falta.  
5. **+ IIBB** sobre precio de venta estimado (o incluirlo en el margen deseado).  
6. **IVA** en factura al cliente local (**+21%** discriminado).  
7. **Ganancias** sobre utilidad anual (reserva en plan financiero, no siempre en precio mensual).

Para **licitación provincial**: sumar retenciones y plazos de pago que exija el pliego (no modelados acá).

Fuente de verdad de cifras de lista: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) y `pricing-pes-by-encounter-class.yaml`.

---

## Relacionado

- [README costos](./README.md)
- [overview.md](./overview.md)
- [Matriz precios Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — precios sin IVA
- [Modelos pricing](../modelo-de-negocio/business-plan/modelos-pricing-diferenciados.md) — unit economics y margen

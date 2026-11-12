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
| Ingresos brutos (IIBB) | **4%** de planificación — **Santiago del Estero**, servicios en general (validar con el contador y con el coeficiente del Convenio Multilateral si se factura fuera) |
| Ganancias (imp. a las ganancias) | **35%** — tramo marginal superior de la escala **25 / 30 / 35%** (Ley 27.743); criterio conservador a escala (con utilidades bajas la efectiva es menor) |
| IDCB (débitos y créditos) | **0,6%** sobre cobranzas y pagos; parte puede computarse como pago a cuenta (se modela completo, conservador) |
| IVA | **21%** (alícuota general servicios; se discrimina, no entra en márgenes) |
| Uso de IA (escala 5.000 prof.) | Intensivo, motivos sin audio (cuestionario) — COGS por prof en [costos-api.md](costos-api.md#resumen-costo-real-por-api-por-médico-por-mes) |

**No cubre:** monotributo, exportación de servicios con tratamiento especial, retenciones en licitación pública, percepciones aduaneras en detalle, impuesto PAIS en cada operación, ni convenios impositivos provincia por provincia.

---

## Tres capas (no mezclar)

| Capa | Qué es | Dónde impacta |
|------|--------|----------------|
| **1. Costo directo** | IA, APIs, infra, nube, **identidad (Didit)** | [costos-api.md](costos-api.md), [costos-didit.md](costos-didit.md), [infra-costos.md](infra-costos.md) |
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

- Impuesto **provincial** (y Convenio Multilateral si facturás en varias provincias).
- Base: **ingresos brutos** por la actividad (facturación neta de IVA), no por el costo de IA.
- **Es deducible de ganancias**: se resta antes de aplicar la tasa.
- Para **armar precio** hacia el cliente:  
  `precio neto objetivo × (1 + alícuota IIBB estimada)` o incluir IIBB en el margen deseado.

Alícuota de planificación en tablas: **4%** — **Santiago del Estero**, servicios en general. No es una alícuota única nacional: depende de la jurisdicción y del coeficiente del Convenio Multilateral. Validar con el contador.

Las **percepciones y retenciones de IIBB** que aplican los clientes al pagar (típicamente 2–3 %) no cambian el impuesto total: son caja y saldo a favor. Si no se computan, se paga dos veces.

---

## Impuesto a las ganancias

- Aplica sobre **utilidad** (ingresos − costos deducibles − IIBB − IDCB − amortizaciones), no sobre facturación bruta.
- Escala vigente (Ley 27.743): **25% / 30% / 35%** por tramos de ganancia neta imponible, con umbrales ajustados por IPC. En tablas se usa **35%** (tramo marginal superior) como criterio conservador a escala.
- En un modelo mensual:  
  `ganancias estimada ≈ max(0, facturación − costo directo − gastos fijos − IIBB − IDCB − otros) × 35%`

No se prorratea de forma exacta sin balance; las tablas siguientes usan **órdenes de magnitud**.

## Impuesto sobre débitos y créditos (IDCB)

- **0,6%** sobre cada crédito y débito en cuentas bancarias.
- Grava tanto las **cobranzas** (sobre ingresos) como los **pagos a proveedores** (sobre costos).
- Parte puede computarse como pago a cuenta de ganancias/IVA según el régimen; en las tablas se modela el **0,6% completo** (conservador).
- Es una de las omisiones más frecuentes en modelos de pricing: no aparece en la factura, pero se paga igual.

---

## Tabla ejemplo: 5.000 profesionales (costo de servir)

Tarifas y supuestos por profesional: [costos-api.md](costos-api.md). Aquí solo la **escala** (5.000 prof.) y el **impacto fiscal**.

### A) Costo operativo documentado (sin impuestos sobre ventas)

| Concepto | Cálculo | USD por mes |
|----------|---------|-------------|
| IA + STT — sin context caching (planificación −30 % STT) | 5.000 x ~4,31 por prof | **~21.550** |
| IA + STT — con context caching (favorable, −30 % STT) | 5.000 x ~3,95 por prof | **~19.750** |
| Videollamada §6 (COGS planificado @ 40 %) | 5.000 x 2,19 por prof | **~10.950** |
| **IA + STT + videollamada** — sin caché | suma filas anteriores | **~32.500** |
| **IA + STT + videollamada** — con caché | 19.750 + 10.950 | **~30.700** |
| Infra app + BD + backups + monitoreo | 5.000 x **0,03** por prof (2–3 VPS KVM2 compartidos; [análisis](analisis-centro-datos-propio.md)) | **~150** |
| **COGS total para pricing** (IA + STT + infra, sin video) | 21.550 + 150 | **~21.700** |

**Infra app:** el catálogo comercial reserva **0,03/prof/mes** y lo reparte entre las atenciones de referencia de cada clase. Base: 2–3 VPS tipo KVM2 (Hostinger ~ARS 44.000/mes c/u) compartidos entre todos los tenants → USD 90–160/mes total, **~150/mes a 5.000 profesionales**. No incluye storage de video (va en el add-on) ni análisis de imágenes (no se ofrece). Medir con la factura real.

**WhatsApp:** solo asistente **iniciado por el paciente**. **Meta no es $0** desde 1/10/2026 (service se cobra después de 1.000 msg/número/mes); el COGS vigente **no lo presupuesta**. Utility **no habilitada**. Detalle: [costos-api §7](costos-api.md#7-whatsapp-cloud-api-paciente).

**`infra-costos.md`** modela **GPU propia para inferencia**, no hosting Yii/API. Con IA por API (tabla de arriba), **no sumar** GPU de ese doc salvo inferencia on-prem.

### B) Carga fiscal sobre compras (estimación conservadora)

Base: filas **IA + STT** de la tabla A (~**21.550** sin caché · ~**19.750** con caché; incluye **−30 %** STT on-device). Estrategias adicionales de [estrategias-reduccion/](estrategias-reduccion/README.md) no están más allá de ese −30 %.

| Concepto | Sin caché (COGS) | Con caché (favorable) | Notas |
|----------|------------------|----------------------|--------|
| Subtotal IA + STT (tabla A) | ~21.550 | ~19.750 | Motivos sin audio; STT planificación (−30 %) |
| IVA 21 % compras (crédito pleno) | +4.526 | +4.148 | RI: **caja ≈ 0** a neto |
| IVA / percepciones no recuperables | 0 – 3.146 | 0 – 2.884 | Peor caso sobre subtotal |
| **Costo IA efectivo (peor caso)** | **~21.550 – 24.696** | **~19.750 – 22.634** | Sin crédito o con percepciones |
| **Costo IA efectivo (RI, normal)** | **~21.550** | **~19.750** | |

**Resumen fiscal (compras):** App/BD *[pendiente]*.

### C) Cotización orientativa — variable IA (5.000 prof., uso intensivo)

**Lista comercial vigente:** [matriz Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — `precio = COGS × (1 + margen%/100)` con margen de lista **233 %** (~70 % bruto ≈ **42,9 %** después de IIBB + ganancias; ~**42,5 %** incluyendo IDCB) y tramos por volumen de atenciones. COGS de esta sección = tabla A / [costos-api](costos-api.md).

#### Qué es cada margen (importante)

| Métrica | Fórmula | ¿Incluye ganancias? |
|---------|---------|---------------------|
| **Margen bruto (*gross margin*)** | `(Precio neto − costo IA) / Precio neto` | **No.** Solo resta el costo directo documentado (IA+STT±video). Es la métrica del business plan (~70 % objetivo en software). |
| **Margen sobre costo (*markup*)** | `(Precio − COGS) / COGS` | **No.** En metadata: `margin_on_cost_percent` (233 % → bruto ~70 %). |
| **Margen después IIBB + ganancias** | `(Precio − IIBB − IDCB − costo IA − ganancias) / Precio` | **Sí.** IIBB **4 %** s/ facturación neta; ganancias **35 %** s/ utilidad antes del impuesto (`utilidad = precio − IIBB − IDCB − costo IA`). IIBB e IDCB se restan antes porque son deducibles. |
| **IVA 21 %** | Sobre factura al cliente | **No entra** en ningún margen: se discrimina; el cliente lo usa como crédito fiscal (B2B). |

#### De dónde sale el 233 %

El markup **no es arbitrario**: es el que produce el margen neto objetivo. La relación es:

```
margen_neto = (1 − IIBB − 1/(1 + markup)) × (1 − ganancias)
markup      = 1 / (1 − IIBB − margen_neto/(1 − ganancias)) − 1
```

Con IIBB 4 % y ganancias 35 % (sin IDCB):

| Margen neto objetivo | Markup necesario |
|---|---:|
| **42,9 %** | **233 %** |
| 45 % | ~274 % |
| 49 % | ~385 % |

El 233 % da ~**49 %** si ganancias fuera **25 %**; con la tasa vigente de **35 %** da **42,9 %** (42,5 % con IDCB). Para volver a 49 % con 35 % habría que **subir** el markup a ~385 %, no bajarlo. Si se baja el markup, el margen neto cae en la misma proporción.

#### Tabla — solo IA + STT (sin videollamada)

| Escenario | ~USD por prof sin caché | ~USD por prof con caché | Precio **neto** 5.000 prof sin caché | Precio **neto** 5.000 prof con caché | Factura **+ IVA 21 %** sin caché | Factura **+ IVA 21 %** con caché | Margen bruto | Margen después IIBB + ganancias |
|-----------|-------------------------|-------------------------|--------------------------------------|--------------------------------------|----------------------------------|----------------------------------|--------------|--------------------------------|
| **Solo costo (sin margen)** * | **~4,31** | **~3,95** | **~21.550** | **~19.750** | **~26.076** | **~23.898** | **0 %** · **0 %** | **Pérdida** |
| **Lista matriz — hipotético solo IA + STT** † | **~14,35** | **~13,15** | **~71.750** | **~65.750** | **~86.818** | **~79.558** | **~70 %** | Ver IIBB+ganancias sobre ese neto |
| **Variable IA, margen mínimo (histórico)** | **1,8 – 2,0** | **1,8 – 2,0** | **9.000 – 10.000** | **9.000 – 10.000** | **10.890 – 12.100** | **10.890 – 12.100** | **bajo / negativo** * | **bajo / negativo** * |

\* COGS planificación (Apartado 1 intensivo **−30 % STT**): sin caché **~21.550** · con caché **~19.750**. Bruto todo-servidor (sin −30 % STT): ~4,84 / ~4,48 por prof.  
† `4,31 × 3,33 ≈ 14,35`. Solo sirve para comparar contra la variable IA + STT; el **precio real** del catálogo usa COGS completo (IA + STT + infra) = **14,40/prof**. Lista comercial / metadata: audio add-on **1,23** (STT del dictado, motivos sin audio).

> **Esta tabla aísla la variable IA + STT.** El precio de lista del catálogo usa el **COGS completo**: IA + STT + **infra app** (0,03/prof/mes) = **4,33/prof** → **14,40/prof**. El **margen % no cambia** (el markup es constante); cambia el monto absoluto. El detalle de abajo usa el COGS completo.

#### Tabla — IA + STT + videollamada (COGS planificado §6)

| Escenario | ~USD por prof sin caché | ~USD por prof con caché | Precio **neto** 5.000 prof sin caché | Precio **neto** 5.000 prof con caché | Factura **+ IVA 21 %** sin caché | Factura **+ IVA 21 %** con caché | Margen bruto |
|-----------|-------------------------|-------------------------|--------------------------------------|--------------------------------------|----------------------------------|----------------------------------|--------------|
| **Solo costo (sin margen)** * | **~6,50** | **~6,14** | **~32.500** | **~30.700** | **~39.325** | **~37.147** | **0 %** |
| **Lista matriz (~70 % bruto)** † | **~21,65** | **~20,45** | **~108.250** | **~102.250** | **~130.983** | **~123.723** | **~70 %** |

\* Tabla A + videollamada ([costos-api §6](costos-api.md#6-videollamadas-pacientemédico), **2,19** @ **40 %** tele = self-host; STT una vez con −30 %).  
† `6,50 × 3,33 ≈ 21,65`.

La fila «margen mínimo» histórico (1,8–2 por prof) **no cubre** STT ni videollamada; la **lista matriz** usa COGS + 233 %.

#### Detalle aritmético — ejemplo lista matriz, 5.000 prof, COGS completo (~14,40/prof)

| Concepto | USD por mes |
|----------|---------|
| Facturación neta (5.000 × 14,40) | 72.000 |
| IVA 21 % (discriminado en factura) | +15.120 (no es ingreso) |
| Ingresos brutos (4 %) | −2.880 |
| IDCB (0,6 % de la cobranza) | −432 |
| Costo IA + STT + infra (5.000 × 4,33) | −21.650 |
| Utilidad antes de ganancias | 47.038 |
| Ganancias (35 %) | −16.463 |
| **Resultado variable** (antes de fijos) | **30.575** |
| **Margen bruto** | **~70 %** (= (72.000 − 21.650) / 72.000) |
| **Margen después IIBB + ganancias** | **~42,9 %** (sin IDCB: 30.856 / 72.000) |
| **Margen después de todos los impuestos** | **~42,5 %** (= 30.575 / 72.000) |

#### Detalle aritmético — ejemplo histórico USD 10.000 por mes neto (2 por prof)

| Concepto | USD por mes |
|----------|---------|
| Facturación neta | 10.000 |
| IVA 21 % (discriminado en factura) | +2.100 (no es ingreso) |
| Ingresos brutos (4 %) | −400 |
| Costo IA + STT + infra (planificación) | −21.700 |
| Utilidad antes ganancias | **pérdida** |
| Ganancias (35 %) | 0 |
| **Resultado variable** (antes de fijos) | **negativo** |
| **Margen bruto** | **negativo** (el precio histórico ya no cubre COGS) |

---

## Cómo usar esto en pricing

1. **Costo directo** (tabla A o B / [costos-api](costos-api.md)) → COGS por profesional (base ± audio ± videollamada).  
2. **Aplicar margen sobre costo** (`volume_discount_tiers` / `margin_on_cost_percent` en metadata; lista **233 %** ≈ 70 % bruto ≈ 42,9 % después de IIBB + ganancias, 42,5 % con IDCB) → precio unitario según atenciones totales. El margen después de impuestos **se deriva** de `tax_assumptions`, no se escribe a mano.  
3. **× cantidad de profesionales** por clase contratada (AMB / EMER / IMP).  
4. **+ Gastos fijos** (sueldos, ventas, implementación) → no están en `docs/costos/` de APIs; ajustar margen o fee one-shot si hace falta.  
5. **+ IIBB** sobre precio de venta estimado (o incluirlo en el margen deseado) y **+ IDCB** sobre cobranzas y pagos.  
6. **IVA** en factura al cliente local (**+21%** discriminado).  
7. **Ganancias** sobre utilidad anual (reserva en plan financiero, no siempre en precio mensual).

Para **licitación provincial**: sumar retenciones y plazos de pago que exija el pliego (no modelados acá).

Fuente de verdad de cifras de lista: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) y el catálogo backend `common/components/Domain/Organization/Pes/Domain/Catalog/PricingPesByEncounterClassCatalog.php`.

---

## Relacionado

- [README costos](README.md)
- [overview.md](overview.md)
- [Matriz precios Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — precios sin IVA
- [Modelos pricing](../modelo-de-negocio/business-plan/modelos-pricing-diferenciados.md) — unit economics y margen

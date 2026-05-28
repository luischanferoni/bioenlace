# Impuestos — Argentina (referencia para costos y pricing)

**Tipo:** costos · fiscal (orientativo)  
**Última actualización:** 2026-05-28  
**Alcance:** impacto fiscal al **operar** Bioenlace en Argentina y al **cotizar** B2B (efector, financiador, Estado). **No es asesoramiento legal ni contable** — validar con contador según forma societaria, provincia y tipo de factura.

Los precios de la [matriz Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) están en USD y **no incluyen IVA**.

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
| Uso de IA (escala 5.000 prof.) | **Intensivo** únicamente: ~USD **7–8/prof/mes** (IA generativa + STT; ver [costos-api.md](./costos-api.md)) |

**No cubre:** monotributo, exportación de servicios con tratamiento especial, retenciones en licitación pública, percepciones aduaneras en detalle, impuesto PAIS en cada operación, ni convenios impositivos provincia por provincia.

---

## Tres capas (no mezclar)

| Capa | Qué es | Dónde impacta |
|------|--------|----------------|
| **1. Costo directo** | IA, APIs, infra, nube | [costos-api.md](./costos-api.md), [infra-costos.md](./infra-costos.md) |
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

Referencia: [costos-api.md](./costos-api.md) — **uso intensivo** = pre-turno, pre-consulta, onboarding, consultas con IA y STT en el volumen documentado (~USD 7,50–8,44/prof/mes según proveedor).

### A) Costo operativo documentado (sin impuestos sobre ventas)

| Concepto | USD/mes (orientativo) | Fuente |
|----------|------------------------|--------|
| **IA + STT vía API** (5.000 × USD 7–8, uso intensivo) | **35.000 – 40.000** | [costos-api.md](./costos-api.md), [modelos-pricing](../modelo-de-negocio/business-plan/modelos-pricing-diferenciados.md) |
| **Aplicación + BD + hosting** (PHP, MySQL, backups, CDN, monitoreo) | *[pendiente presupuesto]* | **No hay cifra a escala 5.000 prof. en el repo** |

**Única referencia interna de “infra” en chico:** clínica de **20 profesionales** → «Infra + soporte» **USD 200–500/mes** en unit economics ([modelos-pricing](../modelo-de-negocio/business-plan/modelos-pricing-diferenciados.md)); mezcla hosting y soporte operativo, **no escala lineal** a 5.000 usuarios.

**`infra-costos.md`** modela **GPU propia para inferencia**, no el costo de servidores de la app Yii/API. Si la IA va por API (como en la tabla de arriba), **no sumar** GPU de ese doc salvo que migren a inferencia on-prem.

| Subtotal en esta tabla | USD/mes |
|------------------------|---------|
| **Solo IA + STT (documentado)** | **35.000 – 40.000** |
| + aplicación/BD (cuando se presupueste) | a sumar |

### B) Carga fiscal sobre compras (estimación conservadora)

Base: **USD 35.000 – 40.000/mes** (solo IA+STT de tabla A).

| Concepto | USD/mes (orientativo) | Notas |
|----------|------------------------|--------|
| Subtotal IA + STT (tabla A) | 35.000 – 40.000 | |
| IVA 21% sobre compras **con crédito pleno** | +7.350 – 8.400 | Si RI: **caja ≈ 0** a neto (crédito fiscal) |
| IVA / percepciones **no recuperables** (servicios exterior) | 0 – 8.000 | Depende facturación del proveedor y tratamiento |
| **Costo IA efectivo (peor caso)** | **35.000 – 48.000** | Sin crédito fiscal o con percepciones |
| **Costo IA efectivo (RI, crédito normal)** | **35.000 – 40.000** | |

**Resumen 5.000 prof. (solo uso intensivo, IA documentada):** **USD 35k–40k/mes**; con impuestos en compras no recuperables, hasta **~USD 48k/mes**. **Sumar aparte** presupuesto de aplicación/BD cuando exista.

### C) Cuánto cobrar al cliente (5.000 prof., pack operativo completo)

Referencia de precio por asiento: [matriz Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) (**USD 8–25/prof/mes**). Supuesto: ambulatorio, agenda, guardia, internación, receta y captura/asistente con **uso intensivo** de IA.

**Costo directo usado en márgenes:** **USD 37.500/mes** (punto medio IA+STT, tabla A). Si el costo real es **USD 40.000**, el margen bruto baja ~2–4 puntos porcentuales (columna «peor COGS»).

#### Qué es cada margen (importante)

| Métrica | Fórmula | ¿Incluye ganancias? |
|---------|---------|---------------------|
| **Margen bruto (*gross margin*)** | `(Precio neto − costo IA) / Precio neto` | **No.** Solo resta el costo directo documentado (IA+STT). Es la métrica del business plan (~70 % objetivo en software). |
| **Margen después IIBB + ganancias** | `(Precio − IIBB − costo IA − ganancias) / Precio` | **Sí.** IIBB **4 %** s/ facturación neta; ganancias **25 %** s/ utilidad antes de imp. a las ganancias (`utilidad = precio − IIBB − costo IA`). |
| **IVA 21 %** | Sobre factura al cliente | **No entra** en ningún margen: se discrimina; el cliente lo usa como crédito fiscal (B2B). |

El **margen bruto no descuenta ganancias** ni IIBB. La **ganancias** se modela aparte en la columna «después IIBB + ganancias». Tampoco están **sueldos, ventas, app/BD** ni implementación.

#### Tabla de cotización orientativa

| Escenario | ~USD/prof/mes | Precio **neto**/mes | Factura **+ IVA 21 %** | Margen bruto (COGS 37,5k) | Margen bruto (COGS 40k) | Margen después IIBB + ganancias |
|-----------|---------------|---------------------|-------------------------|---------------------------|-------------------------|--------------------------------|
| Licitación agresiva (bajo) | 9 | **45.000** | **54.450** | **17 %** | **11 %** | **~10 %** |
| Licitación agresiva (alto) | 10 | **50.000** | **60.500** | **25 %** | **20 %** | **~16 %** |
| Entre agresivo y estándar | 11 | **55.000** | **66.550** | **32 %** | **27 %** | **~21 %** |
| Comercial razonable (bajo) | 12 | **60.000** | **72.600** | **38 %** | **33 %** | **~25 %** |
| Comercial razonable (medio) | 14 | **70.000** | **84.700** | **46 %** | **43 %** | **~32 %** |
| Comercial razonable (alto) | 16 | **80.000** | **96.800** | **53 %** | **50 %** | **~37 %** |
| Lista / margen alto | 20 | **100.000** | **121.000** | **63 %** | **60 %** | **~47 %** |
| Techo matriz | 25 | **125.000** | **151.250** | **70 %** | **68 %** | **~55 %** |

**Lectura rápida para el cliente**

- **Competir con «3 programadores + infra» del ministerio:** **~USD 45k–55k/mes neto** (**~USD 54,5k–66,5k con IVA**). Margen bruto **17–32 %**; margen después de impuestos sobre la venta **~10–21 %** (solo variables; sin equipo propio).
- **Sano para Bioenlace (cubre IA y deja margen):** **~USD 60k–80k/mes neto** (**~USD 73k–97k con IVA**). Margen bruto **38–53 %**; después IIBB + ganancias **~25–37 %**.
- **Precio de lista sin descuento provincial:** hasta **~USD 125k/mes neto** (**~USD 151k con IVA**). Margen bruto **~70 %** (alineado a meta del business plan sobre costo IA únicamente).

#### Detalle aritmético — ejemplo USD 50.000/mes neto

| Concepto | USD/mes |
|----------|---------|
| Facturación neta | 50.000 |
| IVA 21 % (discriminado en factura) | +10.500 (no es ingreso) |
| Ingresos brutos (4 %) | −2.000 |
| Costo IA + STT | −37.500 |
| Utilidad antes ganancias | 10.500 |
| Ganancias (25 %) | −2.625 |
| **Resultado variable** (antes de fijos) | **7.875** |
| **Margen bruto** | **25 %** (= 12.500 / 50.000) |
| **Margen después IIBB + ganancias** | **~16 %** (= 7.875 / 50.000) |

Implementación provincial, integraciones LIS o add-ons por efector (guardia/internación en matriz **por efector**, no por prof.) van **aparte** del mensual recurrente.

---

## Cómo usar esto en pricing

1. **Costo directo** (tabla A o B) → piso variable por escala de usuarios/profesionales.  
2. **+ Gastos fijos** (sueldos, ventas, implementación) → no están en `docs/costos/` de APIs.  
3. **+ IIBB** sobre precio de venta estimado.  
4. **+ Margen objetivo** (ej. gross margin ~70% en software, ver business plan).  
5. **IVA** en factura al cliente local (**+21%** discriminado).  
6. **Ganancias** sobre utilidad anual (reserva en plan financiero, no siempre en precio mensual).

Para **licitación provincial**: sumar retenciones y plazos de pago que exija el pliego (no modelados acá).

---

## Relacionado

- [README costos](./README.md)
- [overview.md](./overview.md)
- [Matriz precios Argentina](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — precios sin IVA
- [Modelos pricing](../modelo-de-negocio/business-plan/modelos-pricing-diferenciados.md) — unit economics y margen

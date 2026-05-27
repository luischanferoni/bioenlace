# China — sistema de salud público y sector privado

**Tipo:** caso de estudio · modelo de negocio  
**Última actualización:** 2026-05-26

---

## Resumen ejecutivo

China construyó en dos décadas un **seguro médico básico casi universal** (*Basic Medical Insurance*, 基本医疗保险 — abreviado **医保**) sobre una red histórica de **hospitales públicos**, y luego aceleró la **digitalización** vía «**hospitales de internet**» (*Internet hospitals*) y plataformas de comercio electrónico.

El Estado regula precios, listas de medicamentos y fraude; los **gigantes digitales privados** capturan valor en **retail farmacéutico, logística y SaaS**, no como prestadores clínicos titulares del presupuesto hospitalario clásico.

---

## Arquitectura del sistema

### Seguro básico (医保)

| Régimen | Población típica | Administrador |
|---------|------------------|---------------|
| **Urban Employee Basic Medical Insurance** | Trabajadores formales urbanos | Pool local + empleador/empleado |
| **Urban-Rural Resident Basic Medical Insurance** | Residentes sin empleo formal urbano / rural | Subsidio estatal + copago individual |
| **Nuevo rural cooperative medical** (histórico, fusionado en resident scheme) | Campo | — |

La **NHSA** (*National Healthcare Security Administration*, 国家医保局) centraliza **negociación de precios**, **reembolsos**, **catálogo de medicamentos** (NRDL) y **auditoría antifraude** con big data.

### Red de prestación

- **Hospitales públicos** por niveles (terciary / secondary / primary) — principal volumen de internación y cirugía.
- **Primary care** en expansión; tradicionalmente el ciudadano iba directo al hospital.
- **Hospitales de internet**: entidad licenciada que ofrece **teleconsulta**, **receta electrónica** y derivación; a menudo **filial digital** de un hospital público existente.
- **Clínicas privadas** y **hospitales privados** en crecimiento en ciudades tier-1/2.

### Pagos y control de costos

- **Fee-for-service** histórico → transición fuerte a **DRG/DIP** (pagos por grupo de diagnóstico) en hospitales públicos.
- **Zero markup** policy en medicamentos hospitalarios en algunos canales → empuja dispensación hacia **retail externo**.
- **Centralized procurement** (VBP, volume-based procurement) para genéricos y dispositivos — comprime márgenes manufactureros, no elimina retail.

---

## Contexto: público vs. privado

| Capa | Rol |
|------|-----|
| **NHSA / 医保** | Financiador regulador; reembolso, auditoría, negociación NRDL |
| **Hospitales públicos** | Prestación pesada; presupuesto + pagos DRG; compran SaaS |
| **Hospitales de internet** | Consulta crónica/aguda leve; receta; integración 医保 para copago |
| **Plataformas (JD Health, AliHealth, Ping An Good Doctor)** | Retail Rx, delivery, telemedicine marketplace |
| **IA médica (iFLYTEK, Infervision, etc.)** | Licencias a hospitales y gobiernos locales |

El paciente con enfermedad crónica puede: teleconsulta cubierta parcialmente → **receta digital** → **compra en app** → **delivery pagado por usuario**.

---

## Cuatro vías de ingreso del sector privado (detalle)

### 1. Ventas minoristas y margen comercial de medicamentos

Motor principal de **JD Health** y **AliHealth**.

- **Margen:** diferencial mayorista farmacéutico vs. precio retail regulado al consumidor.
- **Venta cruzada:** suplementos, dispositivos (tensiómetros, glucómetros), cuidado personal **fuera del catálogo 医保** — márgenes superiores.
- **Integración:** receta del hospital de internet abre checkout en la misma super-app (WeChat/Alipay ecosystem).

### 2. Logística de última milla

**Meituan**, **SF Express**, riders de plataforma.

- Tarifa de envío **pagada por el paciente** (modelo food delivery).
- Estado no subsidia el envío; hospital público no opera flota nacional de última milla Rx.

### 3. Licencias SaaS e IA médica

**iFLYTEK**, **Infervision**, **Yitu**, etc.

- Venta **B2B** a hospitales con presupuesto autónomo de IT.
- Modelos: licencia anual, **pay-per-study** (TC, RM), cloud PACS+AI.
- Licitaciones locales competitivas; ingreso recurrente.

### 4. Compra gubernamental de servicios (excepción con financiamiento central)

**Government Procurement of Services** cuando NHSA necesita:

- Auditoría de fraude en recetas a escala nacional.
- Analítica de claims cross-province.
- Ciberseguridad / procesamiento batch.

Contrato **por resultado**; única vía descrita con **transferencia directa** administración central → empresa privada especializada.

---

## Cuadro comparativo de vías

| Vía | Pagador | ¿Presupuesto estatal directo? | Actores |
|-----|---------|-------------------------------|---------|
| Retail Rx + OTC | Paciente / reembolso parcial 医保 | No en margen retail | JD Health, AliHealth |
| Delivery | Paciente | No | Meituan, SF Express |
| SaaS / IA hospital | Hospital (presupuesto propio) | Indirecto | iFLYTEK, Infervision |
| Auditoría / big data NHSA | NHSA (licitación) | **Sí** | Proveedores IA/seguridad |

---

## Qué lo hace particular en el mundo

1. **Super-apps** como canal de salud (WeChat, Alipay) — no app hospitalaria aislada.
2. **Separación explícita** consulta reembolsable vs. **fulfillment comercial** privado.
3. **Escala** de población + homogeneización regulatoria reciente (NHSA central).
4. **Zero markup + VBP** empujan margen al **canal retail digital**, no al hospital.
5. **DRG/DIP** cambia incentivos hospitalarios → más eficiencia operativa → más demanda de SaaS.

---

## Contraste con Latinoamérica y Argentina

| Eje | China | Argentina |
|-----|-------|-----------|
| Pagador | 医保 central + pools locales | OS + prepagas + provincias |
| Receta digital → farmacia | Super-app integrada | Fragmentado |
| Delivery Rx | Tarifa usuario, escala nacional | Farmacia walk-in / delivery urbano limitado |
| SaaS hospital | Licitación IT autónoma | OS/prepaga + licitación provincial |

---

## Lecturas para Bioenlace

- **Desacoplar captura clínica de fulfillment** (encounter vs. dispensación) encaja con el modelo chino.
- **Wallet del paciente + receta** como puente a tercero comercial — relevante si hubiera integración farmacia.
- **Auditoría antifraude** como producto B2G es nicho de alto valor (similar NHSA en escala menor: auditoría OS).

---

## Referencias

- NHSA, State Council healthcare reform documents `[pendiente]`.
- JD Health / Alibaba Health annual reports `[pendiente]`.
- WHO China health system review `[pendiente]`.
- Material interno [1] — cuatro vías de monetización privada.

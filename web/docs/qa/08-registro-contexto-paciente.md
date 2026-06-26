# Registro de paciente y contexto (sector y provincia)

[← Índice](./README.md) · Checklist: [10-checklist-ejecutable.md](./10-checklist-ejecutable.md)

En la **app paciente**, cada usuario tiene un **contexto**: sector de salud (**Público** o **Privado**) y **provincia**. Eso define qué centros de salida y qué opciones del menú ve al sacar turno, ver el inicio, etc.

---

## Datos de prueba

Pedí al responsable del entorno que confirme si existen estos centros de prueba:

| Centro | Sector | Para probar |
|--------|--------|-------------|
| CAP demo (otra provincia, ej. Santa Fe) | Público | Paciente público de esa provincia |
| Clínica privada demo | Privado | Paciente privado de Santiago del Estero |

Si no están cargados, el equipo de desarrollo puede prepararlos en staging.

---

## Configurar sector y provincia (app paciente)

1. **Vos** entrás a **Configuración** en la app.
2. **Vos** tocás **Sector de salud** y alternás Público / Privado (o el flujo de primera vez que pida elegir).
3. **Vos** tocás **Provincia de contexto** y elegís una provincia.
4. **El sistema** guarda la elección y actualiza qué centros y opciones ves.

### Qué verificar

| ID | Sector | Provincia | Resultado esperado |
|----|--------|-----------|-------------------|
| CTX-01 | Público | Misma que el CAP demo | Al sacar turno aparece el CAP demo; **no** la clínica privada |
| CTX-02 | Privado | Misma que la clínica demo | Aparece la clínica privada; **no** el CAP de otra provincia |
| CTX-03 | Público | Otra provincia (no la del CAP) | No aparecen los centros demo |
| CTX-04 | Cualquiera | Sin provincia elegida | Banner o mensaje pidiendo completar contexto; turnos / inicio limitados |
| CTX-05 | Cambiás de Público a Privado (o al revés) tras haber visto un centro | Al sacar turno, centros del sector anterior **ya no** aplican |

---

## Sugerencia de provincias

1. **Vos** (paciente nuevo o en configuración) pedís sugerencia de provincia si la app lo ofrece.
2. **El sistema** muestra una lista corta de provincias (hasta 5).
3. **CTX-06:** En red local o sin GPS, la lista igual aparece con opciones razonables (no vacía ni error).

---

## Recurso del ministerio de salud (asistente)

1. **Vos** (paciente con provincia configurada) preguntás en el chat algo como “ministerio de salud de mi provincia” o usás el acceso del menú si existe.
2. **El sistema** muestra teléfono / dirección / web del ministerio **de tu provincia**.
3. **CTX-07:** Con provincia Santiago del Estero, datos de SDE (no de otra provincia).
4. **CTX-08:** Cambiando a Santa Fe, datos de Santa Fe.

---

## Registro nuevo en la app (paciente)

1. **Vos** te registrás con validación de identidad (DNI / Didit según el entorno).
2. **El sistema** crea tu cuenta y te pide o asigna sector y provincia.
3. **CTX-09:** Tras el registro podés entrar al inicio; el contexto queda guardado al cerrar y abrir la app.
4. **CTX-10:** Si el domicilio sigue “verificando”, el banner lo indica; puede actualizarse más adelante sin que hagas nada.

---

## Alta de paciente por personal (asistente / web)

1. **Vos** (staff) das de alta un paciente desde el asistente (“registrar paciente”).
2. **Vos** escaneás DNI o completás Didit.
3. **El sistema** confirma el alta en la misma pantalla (nombre, documento).
4. **CTX-11:** Tu sesión de trabajo (efector y servicio que tenías elegidos) **no cambia** al paciente recién creado.
5. **CTX-12:** La búsqueda antigua de candidatos duplicados **ya no existe** — solo el flujo nuevo de registro.

---

## Inicio de la app sin provincia

1. **Vos** (paciente sin provincia) abrís el inicio.
2. **El sistema** oculta o deshabilita bloques que requieren contexto (próximos turnos, consultas asíncronas, etc.).
3. **CTX-13:** Tras elegir provincia, esos bloques **vuelven** a mostrarse.
4. **CTX-14:** “Quiero un turno” en el chat **no avanza** hasta tener provincia (mensaje claro).

---

## Representación (actuar por otro familiar)

1. **Vos** configurás representación (tutor, hijo, etc.) y operás en nombre de otro.
2. **CTX-15:** El sector y provincia que aplican son **los tuyos** (del usuario logueado), no los del familiar representado — anotá el comportamiento al reportar bugs.

---

## Dónde probar en la app

| Pantalla / acción | Qué mirar |
|-------------------|-----------|
| Configuración → Sector / Provincia | Cambio guardado al volver |
| Inicio | Secciones visibles según contexto |
| Chat → sacar turno | Centros ofrecidos |
| Chat → recurso provincial | Datos correctos por provincia |

Más casos numerados: [10-checklist-ejecutable.md](./10-checklist-ejecutable.md) (prefijo **CTX-**).

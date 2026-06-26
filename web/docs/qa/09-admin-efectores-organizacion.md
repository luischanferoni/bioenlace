# Admin: efectores y datos de prueba

[← Índice](./README.md) · Checklist: [10-checklist-ejecutable.md](./10-checklist-ejecutable.md)

Rol **administrador** en la web. Complementa [08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md) cuando probás sector/provincia en la app.

---

## Listado de efectores (`/admin/efectores`)

### Filtros

| Filtro | Qué hace al probar |
|--------|-------------------|
| Nombre | Busca por texto en el nombre del centro |
| Localidad | Filtra por nombre de localidad |
| Departamento | Elige un departamento |
| **Provincia** | Solo centros de esa provincia |
| **Financiamiento** | Busca por texto (ej. “Provincial”, “Privado”) |
| **Sector** | **Público** o **Privado** (misma lógica que en la app paciente) |

### Casos

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| ADM-01 | Abrir listado sin filtros | Carga el listado sin error |
| ADM-02 | Filtrar por provincia (ej. Santa Fe) | Solo centros de esa provincia |
| ADM-03 | Sector **Público** | No aparecen clínicas privadas |
| ADM-04 | Sector **Privado** | Solo centros privados |
| ADM-05 | Provincia + sector juntos | Resultado coherente con ambos filtros |
| ADM-06 | Ordenar por columna Provincia | Orden alfabético |
| ADM-07 | Clic en el nombre de un centro | Abre el detalle sin error |

---

## Datos de prueba (equipo desarrollo / staging)

Si necesitás centros demo para cruzar con la app paciente, pedí que en staging existan:

- Un **CAP público** en otra provincia (ej. Santa Fe), con médico de medicina general.
- Una **clínica privada** en Santiago del Estero, con médico de medicina general.

El desarrollo puede cargarlos con herramientas de consola del servidor; **no es paso obligatorio del tester** salvo que te lo indiquen.

| ID | Qué verificar después de la carga |
|----|-----------------------------------|
| ADM-08 | Paciente público + provincia del CAP → turno en ese centro |
| ADM-09 | Paciente privado + provincia de la clínica → turno en clínica demo |
| ADM-10 | Admin filtro Público → lista el CAP, no la clínica privada |

---

## Localidades (admin)

| ID | Pantalla | Resultado esperado |
|----|----------|-------------------|
| GEO-01 | `/admin/localidades` — filtro provincia | Solo localidades de esa provincia |
| GEO-02 | Tras cargar datos demo | Provincias nuevas visibles en dropdowns |
| GEO-03 | Crear localidad | Valida campos obligatorios y código postal duplicado |

---

## Médico de prueba en un centro

Si te dan usuario de médico demo para un centro:

| ID | Escenario | Resultado esperado |
|----|-----------|-------------------|
| MED-01 | Login médico demo | Entra a la web |
| MED-02 | Elegir efector y servicio medicina general | Puede ver agenda / atender |
| MED-03 | Sacar turno para paciente en ese centro | Turno creado |

Credenciales y pasos exactos: responsable del entorno.

# Internación

[← Índice](./README.md) · Más detalle: [internacion.md](../producto/internacion.md)

Elegí el efector en modo **internación** ([00-transversal](./00-transversal.md)).

---

## Ver el mapa de camas

1. **Vos** abrís Internación / mapa de camas (menú o asistente: “mapa de camas”).
2. **El sistema** muestra pisos, salas y camas con colores: libre, ocupada, bloqueada, aislamiento, etc.
3. **Vos** tocás una cama.
4. **El sistema** te muestra quién está ahí o te deja iniciar ingreso si está libre.

---

## Marcar una cama (bloqueada, aislamiento, libre)

1. **Vos** elegís la acción sobre la cama (bloquear, aislamiento, liberar…).
2. **El sistema** cambia el estado y al refrescar el mapa **se ve** el color nuevo.

---

## Ingresar un paciente

1. **Vos** iniciás ingreso (desde mapa, ficha o llegada desde guardia).
2. **El sistema** te pide datos de internación y una **cama libre**.
3. **Vos** confirmás.
4. **El sistema** ocupa la cama, crea o activa el episodio de internación y **abre** el circuito clínico (episodio FHIR si aplica).

---

## Atender al internado desde el mapa

1. **Vos** entrás a “atender” o a la historia desde la cama ocupada.
2. **El sistema** abre la línea de tiempo / captura del episodio de internación.
3. **Vos** cargás evolución, medicación, estudios (captura clínica).

---

## Cambiar de cama

1. **Vos** (asistente o pantalla de internación) pedís cambio de cama.
2. **El sistema** te muestra camas libres.
3. **Vos** elegís una y confirmás.
4. **El sistema** libera la cama vieja, ocupa la nueva y **mantiene** el mismo episodio de internación.

---

## Alta con epicrisis

1. **Vos** iniciás el alta estructurada.
2. **El sistema** te guía por los pasos (resumen, epicrisis, motivo de egreso…).
3. **Vos** completás y confirmás.
4. **El sistema** da de alta la internación, libera la cama y cierra el episodio clínico según reglas del efector.

---

## Plantillas de epicrisis (administración)

1. **Vos** (con permiso) entrás a administrar plantillas de epicrisis.
2. **El sistema** lista las plantillas del efector.
3. **Vos** creás, editás o desactivás una.
4. Al dar un alta, **podés** elegir una plantilla y el sistema **rellena** parte del texto.

---

## Ficha administrativa de la internación

1. **Vos** abrís la vista de internación (datos de ingreso, obra social, cama, fechas).
2. **El sistema** muestra lo administrativo sin mezclar con la captura clínica pesada en otra pantalla.

---

## Ver medicación, prácticas y balance del internado

1. **Vos** abrís el resumen clínico del episodio (desde API interna o pantallas que consumen el bundle).
2. **El sistema** muestra lo cargado en captura: medicación, pedidos, balance hídrico, régimen — **no** desde pantallas viejas sueltas de “cargar medicación internación”.

---

## Si te quedó un bookmark viejo

1. **Vos** intentás abrir URLs antiguas de medicación/práctica/diagnóstico de internación por separado.
2. **El sistema** te dice que uses la captura desde el mapa o la historia del internado.

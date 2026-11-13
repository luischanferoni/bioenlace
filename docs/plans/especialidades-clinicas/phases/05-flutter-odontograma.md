# Fase 05 — Odontograma en la app Personal de Salud

**Estado:** pendiente.

## Objetivo

Que el profesional vea el mismo odontograma en la app, con la misma información que en
la web.

## Alcance

- Bloque nuevo en la línea de tiempo del paciente, hermano de los bloques de signos
  vitales y motivos.
- Consumo del mismo endpoint que la web; sin lógica clínica en el cliente.
- Formatos, textos y unidades desde la respuesta, sin defaults duplicados.
- Estados vacíos y de error consistentes con el resto de la aplicación.

## Fuera de alcance

- Edición del odontograma en la app.
- Diferencias de comportamiento entre plataformas.

## Criterio de cierre

En la app, la línea de tiempo del paciente con registros odontológicos muestra el
odontograma; sin registros, muestra un estado vacío claro.

## Depende de

[Fase 03](03-api-odontograma.md).

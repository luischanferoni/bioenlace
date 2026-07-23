# Presentaciones comerciales

## Propuestas económicas (CIS Banda)

| Carpeta | Alcance |
|---------|---------|
| [`solo-app-paciente/`](./solo-app-paciente/) | Solo app móvil del paciente (CIS Banda: **9.000** AMB / mes) |
| [`plataforma-clinica/`](./plataforma-clinica/) | Web clínica, app Personal de Salud, captura, tableros AMB+EMER + app paciente (CIS Banda: **9.000** AMB + **19.000** EMER / mes) |

## Demo comercial de turnos

- Fuente Marp: [`demo-comercial-turnos.md`](./demo-comercial-turnos.md)
- Tema: [`bioenlace-marp.css`](./bioenlace-marp.css)
- Salidas generadas: `dist/`

El deck diferencia capacidades **implementadas**, **configurables** y en **shadow / piloto**. Antes de presentarlo, ajustar la diapositiva final y el guion según la institución.

## Exportar

Desde la raíz del repositorio:

```powershell
npx --yes @marp-team/marp-cli@latest `
  --no-stdin `
  web/docs/presentaciones/demo-comercial-turnos.md `
  --theme-set web/docs/presentaciones/bioenlace-marp.css `
  --allow-local-files `
  --output web/docs/presentaciones/dist/demo-comercial-turnos.html
```

PDF:

```powershell
npx --yes @marp-team/marp-cli@latest `
  --no-stdin `
  web/docs/presentaciones/demo-comercial-turnos.md `
  --theme-set web/docs/presentaciones/bioenlace-marp.css `
  --allow-local-files `
  --pdf `
  --output web/docs/presentaciones/dist/demo-comercial-turnos.pdf
```

PowerPoint editable:

```powershell
npx --yes @marp-team/marp-cli@latest `
  --no-stdin `
  web/docs/presentaciones/demo-comercial-turnos.md `
  --theme-set web/docs/presentaciones/bioenlace-marp.css `
  --allow-local-files `
  --pptx `
  --output web/docs/presentaciones/dist/demo-comercial-turnos.pptx
```

La exportación PPTX conserva cada diapositiva como contenido visual de Marp; no todos sus elementos quedan editables como formas nativas de PowerPoint.


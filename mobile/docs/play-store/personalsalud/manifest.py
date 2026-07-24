"""Manifiesto Play Store — app personal de salud.

Rutas `src` / `out` relativas a mobile/docs/ (assets_root por defecto).
Los con_marco* todavía no están: actualizá paths cuando los agregues.
"""
from __future__ import annotations

APP_ID = "personalsalud"

ASSETS_ROOT = "."

LOGO = "packages/shared/assets/branding/logo.png"

FEATURE = {
    "out": "feature-graphic-personalsalud.png",
    "tagline": (
        "Trabajá en tu centro:\n"
        "guardia, consultas e internación"
    ),
    "pillars": ("Guardia", "Consultas", "Internación", "Asistente"),
}

SHOTS = [
    {
        "src": "con_marco screenshot personalsalud inicio-amb.png",
        "out": "play-screenshot-personalsalud-01-inicio.png",
        "phrase": "Tu agenda y consultas del día,\nen el celular",
    },
    {
        "src": "con_marco screenshot personalsalud guardia.png",
        "out": "play-screenshot-personalsalud-02-guardia.png",
        "phrase": "Tablero de urgencias:\ntriage y atención en guardia",
    },
    {
        "src": "con_marco screenshot personalsalud captura.png",
        "out": "play-screenshot-personalsalud-03-captura.png",
        "phrase": "Capturá la consulta\ncon texto o voz",
    },
    {
        "src": "con_marco screenshot personalsalud asistente.png",
        "out": "play-screenshot-personalsalud-04-asistente.png",
        "phrase": "Asistente clínico\npara tareas del efector",
    },
    {
        "src": "con_marco screenshot personalsalud internacion.png",
        "out": "play-screenshot-personalsalud-05-internacion.png",
        "phrase": "Internación a la vista:\nmapa de camas y seguimiento",
    },
]

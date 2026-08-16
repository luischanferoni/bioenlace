"""Manifiesto Play Store — app personal de salud.

Rutas `src` / `out` relativas a `ASSETS_ROOT` (desde mobile/docs/).
"""
from __future__ import annotations

APP_ID = "personalsalud"

ASSETS_ROOT = "play-store/personalsalud"

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
        "src": "con_marco_personalsalud_inicio_turnos.png",
        "out": "play-screenshot-01-inicio-turnos.png",
        "phrase": "Tu agenda y consultas del día,\nen el celular",
    },
    {
        "src": "con_marco_personalsalud_inicio_mensajes.png",
        "out": "play-screenshot-02-inicio-mensajes.png",
        "phrase": "Consultas por mensaje:\nseguí y respondé desde el celular",
    },
    {
        "src": "con_marco_personalsalud_asistente.png",
        "out": "play-screenshot-03-asistente.png",
        "phrase": "Asistente clínico\npara tareas del efector",
    },
]

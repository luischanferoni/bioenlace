"""Manifiesto Play Store — app paciente.

Rutas `src` / `out` relativas a `ASSETS_ROOT` (desde mobile/docs/).
"""
from __future__ import annotations

APP_ID = "paciente"

ASSETS_ROOT = "play-store/paciente"

# Relativo a mobile/ (no a docs): logo compartido del paquete shared.
LOGO = "packages/shared/assets/branding/logo.png"

FEATURE = {
    "out": "feature-graphic-paciente.png",
    "tagline": (
        "Tu asistente para turnos y seguimiento.\n"
        "Consultas presenciales, por videollamada o por chat"
    ),
    "pillars": ("Asistente", "Consultas rápidas", "Tratamientos"),
}

SHOTS = [
    {
        "src": "con_marco screenshot paciente inicio.png",
        "out": "play-screenshot-01-inicio.png",
        "phrase": "Tu salud de un vistazo:\ncondiciones, tratamiento y turnos",
    },
    {
        "src": "con_marco screenshot paciente asistente.png",
        "out": "play-screenshot-02-asistente.png",
        "phrase": "Un asistente que te guía:\nturnos, consultas y seguimientos",
    },
    {
        "src": "con_marco screenshot paciente tratamiento.png",
        "out": "play-screenshot-03-tratamiento.png",
        "phrase": "Gestioná tu tratamiento\ny realiza consultas rápidas",
    },
]

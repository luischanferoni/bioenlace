"""One-shot: compose Play Store feature graphic (1024x500) for app paciente.

Imagen simple: fondo papel, acento primary, logo y frases. Sin screenshots.
"""
from __future__ import annotations

import os

from PIL import Image, ImageDraw, ImageFont

DOCS = os.path.dirname(os.path.abspath(__file__))
LOGO = os.path.join(
    DOCS,
    "..",
    "packages",
    "shared",
    "assets",
    "branding",
    "logo.png",
)
OUT = os.path.join(DOCS, "feature-graphic-paciente.png")

W, H = 1024, 500

# Tokens "papel" + primary (alineados a mobile/packages/shared theme)
PAPER25 = (253, 252, 251, 255)
PAPER50 = (250, 248, 243, 255)
PAPER100 = (242, 239, 232, 255)
PAPER600 = (74, 71, 66, 255)
PAPER500 = (110, 106, 99, 255)
PAPER700 = (46, 44, 40, 255)
PRIMARY = (84, 160, 255, 255)

TAGLINE = (
    "Tu asistente para turnos y seguimiento.\n"
    "Consultas presenciales, por videollamada o por chat"
)
PILLARS = ("Asistente", "Consultas rápidas", "Tratamientos")


def load_font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
    ]
    for path in candidates:
        if os.path.isfile(path):
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def text_size(draw: ImageDraw.ImageDraw, text: str, font: ImageFont.ImageFont) -> tuple[int, int]:
    box = draw.textbbox((0, 0), text, font=font)
    return box[2] - box[0], box[3] - box[1]


def main() -> None:
    canvas = Image.new("RGBA", (W, H), PAPER50)
    draw = ImageDraw.Draw(canvas)

    # Gradiente horizontal suave (papel25 → papel100, con tinte primary a la izquierda)
    for x in range(W):
        t = x / W
        r = int(253 + (242 - 253) * t)
        g = int(252 + (239 - 252) * t)
        b = int(251 + (232 - 251) * t)
        if x < 480:
            u = 1 - x / 480
            r = int(r * (1 - 0.10 * u) + 232 * 0.10 * u)
            g = int(g * (1 - 0.10 * u) + 242 * 0.10 * u)
            b = int(b * (1 - 0.14 * u) + 255 * 0.14 * u)
        draw.line([(x, 0), (x, H)], fill=(r, g, b, 255))

    # Orbes suaves de marca
    orb = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(orb)
    od.ellipse((-120, -160, 360, 280), fill=(84, 160, 255, 32))
    od.ellipse((720, 260, 1140, 580), fill=(84, 160, 255, 22))
    od.ellipse((880, -80, 1180, 180), fill=(84, 160, 255, 16))
    canvas = Image.alpha_composite(canvas, orb)

    logo = Image.open(LOGO).convert("RGBA")
    logo_h = 88
    logo_w = int(logo.width * (logo_h / logo.height))
    logo = logo.resize((logo_w, logo_h), Image.Resampling.LANCZOS)

    # Composición centrada: logo → tagline → acento → pilares
    content_top = 96
    logo_x = (W - logo_w) // 2
    canvas.paste(logo, (logo_x, content_top), logo)

    d = ImageDraw.Draw(canvas)
    font_tag = load_font(24, bold=False)
    font_pillars = load_font(16, bold=False)

    tag_w, tag_h = text_size(d, TAGLINE, font_tag)
    tag_x = (W - tag_w) // 2
    tag_y = content_top + logo_h + 24
    d.multiline_text(
        (tag_x, tag_y),
        TAGLINE,
        font=font_tag,
        fill=PAPER600,
        spacing=6,
        align="center",
    )

    accent_w = 72
    accent_y = tag_y + tag_h + 22
    accent_x0 = (W - accent_w) // 2
    d.rounded_rectangle(
        (accent_x0, accent_y, accent_x0 + accent_w, accent_y + 6),
        radius=3,
        fill=PRIMARY,
    )

    # Pilares en una fila, separados por puntos primary
    pillar_y = accent_y + 24
    gap = 28
    pillar_sizes = [text_size(d, p, font_pillars) for p in PILLARS]
    dot_r = 3
    total_w = sum(w for w, _ in pillar_sizes) + gap * (len(PILLARS) - 1)
    x = (W - total_w) // 2
    for i, pillar in enumerate(PILLARS):
        pw, ph = pillar_sizes[i]
        d.text((x, pillar_y), pillar, font=font_pillars, fill=PAPER500)
        x += pw
        if i < len(PILLARS) - 1:
            cx = x + gap // 2
            cy = pillar_y + ph // 2
            d.ellipse(
                (cx - dot_r, cy - dot_r, cx + dot_r, cy + dot_r),
                fill=PRIMARY,
            )
            x += gap

    out = canvas.convert("RGB")
    out.save(OUT, "PNG", optimize=True)
    print(f"Wrote {OUT} ({out.size[0]}x{out.size[1]})")


if __name__ == "__main__":
    main()

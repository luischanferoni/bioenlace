"""One-shot: Play Store phone screenshots (9:16) for app paciente.

Toma los PNG con_marco*, recorta el teléfono del fondo negro, lo escala sobre
fondo papel y deja una franja superior para la frase de cada pantalla.
Solo teléfono (1080x1920); sin variante tablet.
"""
from __future__ import annotations

import os

from PIL import Image, ImageDraw, ImageFont

DOCS = os.path.dirname(os.path.abspath(__file__))

# Play Store phone: 9:16, lados entre 320 y 3840, < 8 MB
W, H = 1080, 1920

PAPER50 = (250, 248, 243, 255)
PAPER600 = (74, 71, 66, 255)
PRIMARY = (84, 160, 255, 255)

# Franja superior para frase (~16%); el teléfono usa el resto con márgenes
TOP_BAND_RATIO = 0.16
SIDE_PAD = 72
BOTTOM_PAD = 56
ACCENT_W = 80

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


def trim_black_margins(im: Image.Image, threshold: int = 18) -> Image.Image:
    """Recorta franjas negras (o casi) alrededor del teléfono enmarcado."""
    rgb = im.convert("RGB")
    w, h = rgb.size
    px = rgb.load()

    def is_black(c: tuple[int, int, int]) -> bool:
        return c[0] <= threshold and c[1] <= threshold and c[2] <= threshold

    def col_empty(x: int) -> bool:
        step = max(1, h // 200)
        samples = [px[x, y] for y in range(0, h, step)]
        return all(is_black(c) for c in samples)

    def row_empty(y: int) -> bool:
        step = max(1, w // 200)
        samples = [px[x, y] for x in range(0, w, step)]
        return all(is_black(c) for c in samples)

    left = 0
    while left < w - 1 and col_empty(left):
        left += 1
    right = w - 1
    while right > left and col_empty(right):
        right -= 1
    top = 0
    while top < h - 1 and row_empty(top):
        top += 1
    bottom = h - 1
    while bottom > top and row_empty(bottom):
        bottom -= 1

    # Pequeño margen para no comerse el bisel / sombra del marco
    pad = 4
    left = max(0, left - pad)
    top = max(0, top - pad)
    right = min(w - 1, right + pad)
    bottom = min(h - 1, bottom + pad)
    return im.crop((left, top, right + 1, bottom + 1))


def make_background() -> Image.Image:
    canvas = Image.new("RGBA", (W, H), PAPER50)
    draw = ImageDraw.Draw(canvas)
    for x in range(W):
        t = x / W
        r = int(253 + (242 - 253) * t)
        g = int(252 + (239 - 252) * t)
        b = int(251 + (232 - 251) * t)
        if x < 520:
            u = 1 - x / 520
            r = int(r * (1 - 0.10 * u) + 232 * 0.10 * u)
            g = int(g * (1 - 0.10 * u) + 242 * 0.10 * u)
            b = int(b * (1 - 0.14 * u) + 255 * 0.14 * u)
        draw.line([(x, 0), (x, H)], fill=(r, g, b, 255))

    orb = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(orb)
    od.ellipse((-180, -200, 420, 360), fill=(84, 160, 255, 28))
    od.ellipse((680, 1400, 1280, 2100), fill=(84, 160, 255, 22))
    od.ellipse((820, -60, 1200, 280), fill=(84, 160, 255, 14))
    return Image.alpha_composite(canvas, orb)


def compose_one(src_name: str, out_name: str, phrase: str) -> str:
    src_path = os.path.join(DOCS, src_name)
    out_path = os.path.join(DOCS, out_name)

    phone = trim_black_margins(Image.open(src_path).convert("RGBA"))
    top_band = int(H * TOP_BAND_RATIO)
    max_w = W - SIDE_PAD * 2
    max_h = H - top_band - BOTTOM_PAD
    scale = min(max_w / phone.width, max_h / phone.height)
    nw = max(1, int(phone.width * scale))
    nh = max(1, int(phone.height * scale))
    phone = phone.resize((nw, nh), Image.Resampling.LANCZOS)

    canvas = make_background()
    phone_x = (W - nw) // 2
    phone_y = top_band + (max_h - nh) // 2
    canvas.paste(phone, (phone_x, phone_y), phone)

    d = ImageDraw.Draw(canvas)
    font = load_font(40, bold=False)
    tw, th = text_size(d, phrase, font)
    # Si la frase es muy ancha, bajar un poco el tamaño
    if tw > W - 96:
        font = load_font(34, bold=False)
        tw, th = text_size(d, phrase, font)

    phrase_y = (top_band - th - 28) // 2
    phrase_y = max(48, phrase_y)
    d.multiline_text(
        ((W - tw) // 2, phrase_y),
        phrase,
        font=font,
        fill=PAPER600,
        spacing=8,
        align="center",
    )

    accent_y = phrase_y + th + 18
    ax0 = (W - ACCENT_W) // 2
    d.rounded_rectangle(
        (ax0, accent_y, ax0 + ACCENT_W, accent_y + 7),
        radius=3,
        fill=PRIMARY,
    )

    out = canvas.convert("RGB")
    out.save(out_path, "PNG", optimize=True)
    size_kb = os.path.getsize(out_path) // 1024
    return f"{out_name} ({out.size[0]}x{out.size[1]}, {size_kb} KB)"


def main() -> None:
    for shot in SHOTS:
        info = compose_one(shot["src"], shot["out"], shot["phrase"])
        print(f"Wrote {os.path.join(DOCS, shot['out'])} — {info}")


if __name__ == "__main__":
    main()

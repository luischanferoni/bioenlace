"""One-shot: compose Play Store feature graphic (1024x500) for app paciente."""
from __future__ import annotations

import os

from PIL import Image, ImageDraw, ImageFilter, ImageFont

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

PAPER50 = (250, 248, 243, 255)
PAPER700 = (46, 44, 40, 255)
PAPER500 = (110, 106, 99, 255)
PRIMARY = (84, 160, 255, 255)


def load_font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
    ]
    for path in candidates:
        if os.path.isfile(path):
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def rounded_rect_mask(size: tuple[int, int], radius: int) -> Image.Image:
    mask = Image.new("L", size, 0)
    draw = ImageDraw.Draw(mask)
    draw.rounded_rectangle((0, 0, size[0] - 1, size[1] - 1), radius=radius, fill=255)
    return mask


def trim_uniform_margins(
    im: Image.Image,
    tol: int = 18,
    ref: tuple[int, int, int] = (253, 252, 251),
    empty_ratio: float = 0.92,
) -> Image.Image:
    """Recorta franjas laterales/verticales casi vacías del capture."""
    rgb = im.convert("RGB")
    w, h = rgb.size
    px = rgb.load()

    def near_ref(c: tuple[int, int, int]) -> bool:
        # fondos papel / blanco del capture
        if c[0] >= 240 and c[1] >= 238 and c[2] >= 232:
            return True
        return (
            abs(c[0] - ref[0]) <= tol
            and abs(c[1] - ref[1]) <= tol
            and abs(c[2] - ref[2]) <= tol
        )

    def col_is_margin(x: int) -> bool:
        step = max(1, h // 80)
        samples = [px[x, y] for y in range(0, h, step)]
        empty = sum(1 for c in samples if near_ref(c))
        return empty / len(samples) >= empty_ratio

    def row_is_margin(y: int) -> bool:
        step = max(1, w // 80)
        samples = [px[x, y] for x in range(0, w, step)]
        empty = sum(1 for c in samples if near_ref(c))
        return empty / len(samples) >= empty_ratio

    left = 0
    while left < w - 1 and col_is_margin(left):
        left += 1
    right = w - 1
    while right > left and col_is_margin(right):
        right -= 1
    top = 0
    while top < h - 1 and row_is_margin(top):
        top += 1
    bottom = h - 1
    while bottom > top and row_is_margin(bottom):
        bottom -= 1

    if right - left < w * 0.7 or bottom - top < h * 0.7:
        return im
    return im.crop((left, top, right + 1, bottom + 1))


def phone_frame(
    screenshot: Image.Image,
    phone_h: int,
    phone_w: int | None = None,
    bezel: int = 10,
    radius: int = 28,
    fit: str = "contain",
) -> Image.Image:
    """Place screenshot in a phone frame.

    fit:
      - contain: whole shot visible (paper letterbox)
      - width: scale to content width; crop height from top if taller
      - cover: fill entire phone (may crop sides/top)
    """
    if phone_w is None:
        phone_w = int(phone_h * 9 / 16)

    content_w = phone_w
    content_h = phone_h
    sw, sh = screenshot.size

    if fit == "cover":
        scale = max(content_w / sw, content_h / sh)
        nw, nh = max(1, int(sw * scale)), max(1, int(sh * scale))
        shot = screenshot.resize((nw, nh), Image.Resampling.LANCZOS)
        left = max(0, (nw - content_w) // 2)
        top = 0 if nh >= content_h else max(0, (nh - content_h) // 2)
        shot = shot.crop((left, top, left + content_w, top + content_h))
        ox, oy = 0, 0
    elif fit == "width":
        scale = content_w / sw
        nw, nh = content_w, max(1, int(sh * scale))
        shot = screenshot.resize((nw, nh), Image.Resampling.LANCZOS)
        if nh >= content_h:
            shot = shot.crop((0, 0, content_w, content_h))
            ox, oy = 0, 0
        else:
            ox, oy = 0, (content_h - nh) // 2
    else:
        scale = min(content_w / sw, content_h / sh)
        nw, nh = max(1, int(sw * scale)), max(1, int(sh * scale))
        shot = screenshot.resize((nw, nh), Image.Resampling.LANCZOS)
        ox = (content_w - nw) // 2
        oy = (content_h - nh) // 2

    screen = Image.new("RGBA", (content_w, content_h), (250, 248, 243, 255))
    screen.paste(shot, (ox, oy), shot if shot.mode == "RGBA" else None)
    screen.putalpha(rounded_rect_mask((content_w, content_h), max(12, radius - 8)))

    outer_w = content_w + bezel * 2
    outer_h = content_h + bezel * 2
    frame = Image.new("RGBA", (outer_w, outer_h), (0, 0, 0, 0))
    body = Image.new("RGBA", (outer_w, outer_h), (30, 30, 28, 255))
    body.putalpha(rounded_rect_mask((outer_w, outer_h), radius))
    frame = Image.alpha_composite(frame, body)
    frame.paste(screen, (bezel, bezel), screen)
    return frame


def drop_shadow(
    img: Image.Image,
    offset: tuple[int, int] = (0, 10),
    blur: int = 18,
    opacity: int = 90,
) -> Image.Image:
    pad = blur * 2
    shadow = Image.new("RGBA", (img.width + pad * 2, img.height + pad * 2), (0, 0, 0, 0))
    alpha = img.split()[-1].point(lambda a: min(a, opacity))
    s = Image.new("RGBA", img.size, (26, 25, 22, opacity))
    s.putalpha(alpha)
    shadow.paste(s, (pad + offset[0], pad + offset[1]), s)
    shadow = shadow.filter(ImageFilter.GaussianBlur(blur))
    out = Image.new("RGBA", shadow.size, (0, 0, 0, 0))
    out = Image.alpha_composite(out, shadow)
    out.paste(img, (pad, pad), img)
    return out


def main() -> None:
    canvas = Image.new("RGBA", (W, H), PAPER50)
    draw = ImageDraw.Draw(canvas)

    for x in range(W):
        t = x / W
        r = int(250 + (242 - 250) * t)
        g = int(248 + (239 - 248) * t)
        b = int(243 + (232 - 243) * t)
        if x < 420:
            u = 1 - x / 420
            r = int(r * (1 - 0.08 * u) + 232 * 0.08 * u)
            g = int(g * (1 - 0.08 * u) + 242 * 0.08 * u)
            b = int(b * (1 - 0.12 * u) + 255 * 0.12 * u)
        draw.line([(x, 0), (x, H)], fill=(r, g, b, 255))

    orb = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(orb)
    od.ellipse((-80, -120, 280, 240), fill=(84, 160, 255, 28))
    od.ellipse((780, 280, 1100, 560), fill=(84, 160, 255, 20))
    canvas = Image.alpha_composite(canvas, orb)

    inicio = Image.open(os.path.join(DOCS, "screenshot paciente inicio.png")).convert("RGBA")
    asistente = Image.open(
        os.path.join(DOCS, "screenshot paciente asistente.png")
    ).convert("RGBA")
    asistente = trim_uniform_margins(asistente)
    # Zoom horizontal leve: el chat deja padding visual; acercamos al contenido
    aw, ah = asistente.size
    inset = max(1, int(aw * 0.04))
    asistente = asistente.crop((inset, 0, aw - inset, ah))
    preparar = Image.open(
        os.path.join(DOCS, "screenshot paciente preparar consulta.png")
    ).convert("RGBA")

    phone_h_main = 400
    phone_h_side = 360
    # Near-square screenshots → slightly wider than classic phone so UI is readable
    phone_w_main = 250
    phone_w_side = 225

    p_prep = drop_shadow(
        phone_frame(preparar, phone_h_side, phone_w_side, bezel=9, radius=26),
        blur=16,
        opacity=70,
    )
    # Teléfono 3 (asistente): mismo ancho de marco que el otro lateral;
    # fit=width para que el PNG (más angosto) ocupe todo el ancho.
    p_asi = drop_shadow(
        phone_frame(
            asistente,
            phone_h_side,
            phone_w_side,
            bezel=9,
            radius=26,
            fit="width",
        ),
        blur=16,
        opacity=70,
    )
    p_ini = drop_shadow(
        phone_frame(inicio, phone_h_main, phone_w_main, bezel=10, radius=28),
        blur=20,
        opacity=85,
    )

    phones = Image.new("RGBA", (W, H), (0, 0, 0, 0))

    def paste_y(layer: Image.Image, img: Image.Image, x: int) -> None:
        y = (H - img.height) // 2 + 6
        layer.paste(img, (x, y), img)

    # Back: preparar | asistente — Front: inicio
    paste_y(phones, p_prep, 270)
    paste_y(phones, p_asi, 680)  # más a la derecha para que se vea el ancho lleno
    paste_y(phones, p_ini, 420)
    canvas = Image.alpha_composite(canvas, phones)

    logo = Image.open(LOGO).convert("RGBA")
    logo_h = 56
    logo_w = int(logo.width * (logo_h / logo.height))
    logo = logo.resize((logo_w, logo_h), Image.Resampling.LANCZOS)
    canvas.paste(logo, (44, 150), logo)

    d = ImageDraw.Draw(canvas)
    # Logo already includes wordmark — only tagline below
    d.multiline_text(
        (48, 230),
        "Tu salud, turnos y\nseguimiento en el celular",
        font=load_font(20),
        fill=PAPER500,
        spacing=6,
    )
    d.rounded_rectangle((48, 300, 128, 306), radius=3, fill=PRIMARY)

    out = canvas.convert("RGB")
    out.save(OUT, "PNG", optimize=True)
    print(f"Wrote {OUT} ({out.size[0]}x{out.size[1]})")


if __name__ == "__main__":
    main()

"""Motor compartido: feature graphic y screenshots Play Store (papel + primary)."""
from __future__ import annotations

import os
from typing import Sequence

from PIL import Image, ImageDraw, ImageFont

# Play Store phone: 9:16
SHOT_W, SHOT_H = 1080, 1920
# Feature graphic
FEATURE_W, FEATURE_H = 1024, 500

PAPER50 = (250, 248, 243, 255)
PAPER500 = (110, 106, 99, 255)
PAPER600 = (74, 71, 66, 255)
PRIMARY = (84, 160, 255, 255)

TOP_BAND_RATIO = 0.16
SIDE_PAD = 72
BOTTOM_PAD = 56
ACCENT_W_SHOT = 80
ACCENT_W_FEATURE = 72


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

    pad = 4
    left = max(0, left - pad)
    top = max(0, top - pad)
    right = min(w - 1, right + pad)
    bottom = min(h - 1, bottom + pad)
    return im.crop((left, top, right + 1, bottom + 1))


def make_background(width: int, height: int) -> Image.Image:
    canvas = Image.new("RGBA", (width, height), PAPER50)
    draw = ImageDraw.Draw(canvas)
    tint_until = min(width, max(1, int(width * 0.48)))
    for x in range(width):
        t = x / width
        r = int(253 + (242 - 253) * t)
        g = int(252 + (239 - 252) * t)
        b = int(251 + (232 - 251) * t)
        if x < tint_until:
            u = 1 - x / tint_until
            r = int(r * (1 - 0.10 * u) + 232 * 0.10 * u)
            g = int(g * (1 - 0.10 * u) + 242 * 0.10 * u)
            b = int(b * (1 - 0.14 * u) + 255 * 0.14 * u)
        draw.line([(x, 0), (x, height)], fill=(r, g, b, 255))

    orb = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    od = ImageDraw.Draw(orb)
    od.ellipse((-180, -200, 420, 360), fill=(84, 160, 255, 28))
    od.ellipse(
        (int(width * 0.63), int(height * 0.73), int(width * 1.18), int(height * 1.09)),
        fill=(84, 160, 255, 22),
    )
    od.ellipse(
        (int(width * 0.76), -60, int(width * 1.11), 280),
        fill=(84, 160, 255, 14),
    )
    return Image.alpha_composite(canvas, orb)


def resolve_path(assets_root: str, rel: str) -> str:
    if os.path.isabs(rel):
        return rel
    return os.path.normpath(os.path.join(assets_root, rel))


def compose_screenshot(
    *,
    assets_root: str,
    src: str,
    out: str,
    phrase: str,
) -> str:
    src_path = resolve_path(assets_root, src)
    out_path = resolve_path(assets_root, out)
    if not os.path.isfile(src_path):
        raise FileNotFoundError(f"No está el src: {src_path}")

    phone = trim_black_margins(Image.open(src_path).convert("RGBA"))
    top_band = int(SHOT_H * TOP_BAND_RATIO)
    max_w = SHOT_W - SIDE_PAD * 2
    max_h = SHOT_H - top_band - BOTTOM_PAD
    scale = min(max_w / phone.width, max_h / phone.height)
    nw = max(1, int(phone.width * scale))
    nh = max(1, int(phone.height * scale))
    phone = phone.resize((nw, nh), Image.Resampling.LANCZOS)

    canvas = make_background(SHOT_W, SHOT_H)
    phone_x = (SHOT_W - nw) // 2
    phone_y = top_band + (max_h - nh) // 2
    canvas.paste(phone, (phone_x, phone_y), phone)

    d = ImageDraw.Draw(canvas)
    font = load_font(40, bold=False)
    tw, th = text_size(d, phrase, font)
    if tw > SHOT_W - 96:
        font = load_font(34, bold=False)
        tw, th = text_size(d, phrase, font)

    phrase_y = max(48, (top_band - th - 28) // 2)
    d.multiline_text(
        ((SHOT_W - tw) // 2, phrase_y),
        phrase,
        font=font,
        fill=PAPER600,
        spacing=8,
        align="center",
    )

    accent_y = phrase_y + th + 18
    ax0 = (SHOT_W - ACCENT_W_SHOT) // 2
    d.rounded_rectangle(
        (ax0, accent_y, ax0 + ACCENT_W_SHOT, accent_y + 7),
        radius=3,
        fill=PRIMARY,
    )

    os.makedirs(os.path.dirname(out_path) or ".", exist_ok=True)
    rgb = canvas.convert("RGB")
    rgb.save(out_path, "PNG", optimize=True)
    size_kb = os.path.getsize(out_path) // 1024
    return f"{out} ({rgb.size[0]}x{rgb.size[1]}, {size_kb} KB)"


def compose_feature_graphic(
    *,
    assets_root: str,
    logo_path: str,
    out: str,
    tagline: str,
    pillars: Sequence[str],
) -> str:
    out_path = resolve_path(assets_root, out)
    logo_abs = resolve_path(assets_root, logo_path) if not os.path.isabs(logo_path) else logo_path
    if not os.path.isfile(logo_abs):
        raise FileNotFoundError(f"No está el logo: {logo_abs}")

    canvas = make_background(FEATURE_W, FEATURE_H)

    logo = Image.open(logo_abs).convert("RGBA")
    logo_h = 88
    logo_w = int(logo.width * (logo_h / logo.height))
    logo = logo.resize((logo_w, logo_h), Image.Resampling.LANCZOS)

    content_top = 96
    canvas.paste(logo, ((FEATURE_W - logo_w) // 2, content_top), logo)

    d = ImageDraw.Draw(canvas)
    font_tag = load_font(24, bold=False)
    font_pillars = load_font(16, bold=False)

    tag_w, tag_h = text_size(d, tagline, font_tag)
    tag_y = content_top + logo_h + 24
    d.multiline_text(
        ((FEATURE_W - tag_w) // 2, tag_y),
        tagline,
        font=font_tag,
        fill=PAPER600,
        spacing=6,
        align="center",
    )

    accent_y = tag_y + tag_h + 22
    ax0 = (FEATURE_W - ACCENT_W_FEATURE) // 2
    d.rounded_rectangle(
        (ax0, accent_y, ax0 + ACCENT_W_FEATURE, accent_y + 6),
        radius=3,
        fill=PRIMARY,
    )

    pillar_y = accent_y + 24
    gap = 28
    pillar_sizes = [text_size(d, p, font_pillars) for p in pillars]
    total_w = sum(w for w, _ in pillar_sizes) + gap * max(0, len(pillars) - 1)
    x = (FEATURE_W - total_w) // 2
    dot_r = 3
    for i, pillar in enumerate(pillars):
        pw, ph = pillar_sizes[i]
        d.text((x, pillar_y), pillar, font=font_pillars, fill=PAPER500)
        x += pw
        if i < len(pillars) - 1:
            cx = x + gap // 2
            cy = pillar_y + ph // 2
            d.ellipse(
                (cx - dot_r, cy - dot_r, cx + dot_r, cy + dot_r),
                fill=PRIMARY,
            )
            x += gap

    os.makedirs(os.path.dirname(out_path) or ".", exist_ok=True)
    rgb = canvas.convert("RGB")
    rgb.save(out_path, "PNG", optimize=True)
    return f"{out} ({rgb.size[0]}x{rgb.size[1]})"

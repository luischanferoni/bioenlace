"""CLI: genera feature graphic y/o screenshots Play Store por app.

Uso (desde cualquier cwd):
  python mobile/docs/play-store/compose.py paciente
  python mobile/docs/play-store/compose.py paciente --kind feature
  python mobile/docs/play-store/compose.py paciente --kind screenshots
  python mobile/docs/play-store/compose.py personalsalud --kind all
"""
from __future__ import annotations

import argparse
import importlib.util
import os
import sys
import types

PLAY_STORE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.dirname(PLAY_STORE)
MOBILE = os.path.dirname(DOCS)

if PLAY_STORE not in sys.path:
    sys.path.insert(0, PLAY_STORE)

import _compose_lib as lib  # noqa: E402


def _load_manifest(app: str) -> types.ModuleType:
    path = os.path.join(PLAY_STORE, app, "manifest.py")
    if not os.path.isfile(path):
        raise SystemExit(f"No hay manifiesto para app={app!r}: {path}")
    spec = importlib.util.spec_from_file_location(f"play_store_{app}_manifest", path)
    if spec is None or spec.loader is None:
        raise SystemExit(f"No se pudo cargar {path}")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def _assets_root(manifest: types.ModuleType) -> str:
    rel = getattr(manifest, "ASSETS_ROOT", ".")
    if os.path.isabs(rel):
        return rel
    return os.path.normpath(os.path.join(DOCS, rel))


def _logo_path(manifest: types.ModuleType) -> str:
    logo = getattr(manifest, "LOGO", "packages/shared/assets/branding/logo.png")
    if os.path.isabs(logo):
        return logo
    # LOGO se resuelve desde mobile/ (shared branding)
    return os.path.normpath(os.path.join(MOBILE, logo))


def run_feature(manifest: types.ModuleType) -> None:
    feature = getattr(manifest, "FEATURE", None)
    if not feature:
        print("Sin FEATURE en el manifiesto; salto.")
        return
    assets = _assets_root(manifest)
    info = lib.compose_feature_graphic(
        assets_root=assets,
        logo_path=_logo_path(manifest),
        out=feature["out"],
        tagline=feature["tagline"],
        pillars=feature["pillars"],
    )
    print(f"Wrote {lib.resolve_path(assets, feature['out'])} — {info}")


def run_screenshots(manifest: types.ModuleType, *, skip_missing: bool) -> None:
    shots = getattr(manifest, "SHOTS", None) or []
    if not shots:
        print("Sin SHOTS en el manifiesto; salto.")
        return
    assets = _assets_root(manifest)
    for shot in shots:
        src = shot["src"]
        src_abs = lib.resolve_path(assets, src)
        if not os.path.isfile(src_abs):
            msg = f"Falta src: {src_abs}"
            if skip_missing:
                print(f"SKIP {msg}")
                continue
            raise FileNotFoundError(msg)
        info = lib.compose_screenshot(
            assets_root=assets,
            src=src,
            out=shot["out"],
            phrase=shot["phrase"],
        )
        print(f"Wrote {lib.resolve_path(assets, shot['out'])} — {info}")


def main() -> None:
    parser = argparse.ArgumentParser(description="Compose Play Store assets Bioenlace")
    parser.add_argument(
        "app",
        choices=("paciente", "personalsalud"),
        help="App cuyo manifiesto usar",
    )
    parser.add_argument(
        "--kind",
        choices=("all", "feature", "screenshots"),
        default="all",
        help="Qué generar (default: all)",
    )
    parser.add_argument(
        "--skip-missing",
        action="store_true",
        help="En screenshots, omitir src ausentes en vez de fallar",
    )
    args = parser.parse_args()

    manifest = _load_manifest(args.app)
    if args.kind in ("all", "feature"):
        run_feature(manifest)
    if args.kind in ("all", "screenshots"):
        # Fuentes staff aún no listas: en --kind all omitimos src faltantes.
        skip_missing = args.skip_missing or (
            args.app == "personalsalud" and args.kind == "all"
        )
        run_screenshots(manifest, skip_missing=skip_missing)


if __name__ == "__main__":
    main()

"""Package only deployable plugin files; exclude repository tooling and local data."""

from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

root = Path(__file__).resolve().parent.parent
plugin = root / "plugin" / "reginor-lite"
destination = root / "dist" / "reginor-lite.zip"
destination.parent.mkdir(exist_ok=True)

# Explicit allowlist: secrets, local artifacts and test fixtures cannot enter the ZIP.
files = [plugin / "reginor-lite.php", plugin / "wpml-config.xml"]
files.extend(sorted((plugin / "src").rglob("*.php")))
for suffix in ("*.css", "*.js"):
    files.extend(sorted((plugin / "assets").glob(suffix)))
files.extend(plugin / "assets" / "vendor" / "leaflet" / name for name in ("leaflet.js", "leaflet.css", "LICENSE"))
files.extend(plugin / "assets" / "vendor" / "leaflet" / "images" / name for name in ("layers.png", "layers-2x.png", "marker-icon.png", "marker-icon-2x.png", "marker-shadow.png"))
if (plugin / "languages").exists():
    files.extend(sorted((plugin / "languages").glob("*.pot")))
    files.extend(sorted((plugin / "languages").glob("*.json")))
    files.extend(sorted((plugin / "languages").glob("*.mo")))
    files.extend(sorted((plugin / "languages").glob("*.l10n.php")))

with ZipFile(destination, "w", ZIP_DEFLATED) as archive:
    for source in files:
        if source.is_symlink():
            raise ValueError(f"Symbolsk lenke skal ikke pakkes: {source}")
        archive.write(source, source.relative_to(plugin.parent))

print(f"Laget {destination.relative_to(root)} ({len(files)} filer).")

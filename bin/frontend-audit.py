#!/usr/bin/env python3
"""Small dependency-free frontend contract check used in CI."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
files = list((ROOT / "application/modules").glob("*/views/**/*.php")) + list((ROOT / "application/views").glob("**/*.php"))
files = [p for p in files if "application/views/components" not in str(p)]
errors = []
tables = headers = manual_tables = 0
for path in files:
    text = path.read_text(encoding="utf-8", errors="ignore")
    if re.search(r"<th\s+[^>]*ead\b", text, re.I):
        errors.append(f"{path}: malformed <thead> tag")
    if re.search(r"<[^>]+\bclass\s*=\s*['\"][^'\"]*['\"][^>]+\bclass\s*=", text, re.I):
        errors.append(f"{path}: duplicate class attribute")
    if re.search(r"\son(?:click|change|submit|input|keyup|keydown)\s*=", text, re.I):
        errors.append(f"{path}: inline event handler; use data-action/data-call")
    if "application/views/emails" not in str(path) and re.search(r"\sstyle\s*=", text, re.I):
        errors.append(f"{path}: inline style; use classes or safe data presentation")
    # Режим интерфейса (Ui_Mode) выбирают компоненты и шаблон, а не представления
    # модулей: иначе `if simple` расползётся по сотням файлов (docs/FRONTEND_STATUS.md).
    if "Ui_Mode::" in text and path.name != "template_tailwindcss.php":
        errors.append(f"{path}: Ui_Mode:: in a view; use components or .pro-only/.simple-only")
    # Отступы от меню и шапки задаёт <main> в template_tailwindcss.php. Свой `container`
    # в представлении даёт двойные поля и центрирование с зазорами (docs/FRONTEND_STATUS.md).
    # Исключения: самостоятельные макеты (layout/, полный <!DOCTYPE>) и старые bootstrap-версии.
    if "/layout/" not in str(path) and "bootstrap" not in path.name and not re.search(r"<!doctype", text, re.I) and re.search(r"class\s*=\s*['\"][^'\"]*(?<![\w-])container(?:-fluid)?(?![\w-])", text):
        errors.append(f"{path}: class container in a view; <main> already sets page padding")
    table_count = len(re.findall(r"<table\b", text, re.I))
    caption_count = len(re.findall(r"<caption\b", text, re.I))
    if table_count:
        tables += table_count
        if "Component::render('table'" not in text and 'Component::render("table"' not in text:
            manual_tables += table_count
        if caption_count < table_count:
            errors.append(f"{path}: {table_count - caption_count} table(s) without caption")
    for header in re.findall(r"<th\b[^>]*>", text, re.I):
        headers += 1
        if not re.search(r"\bscope\s*=", header, re.I):
            errors.append(f"{path}: <th> without scope")

# Атрибут `hidden` задаёт `display: none` правилом из базового слоя Tailwind
# (`[hidden]:where(...)`), а display-утилита вроде `.inline-flex` — правилом из
# слоя утилит: специфичность у них одинаковая, утилита стоит в файле позже и
# поэтому выигрывает. Элемент, у которого есть и то и другое, остаётся видимым
# всегда — так индикатор «нет связи» светился в шапке при рабочей сети.
# Скрывать такой элемент надо обёрткой без display-утилиты либо классом `hidden`.
HIDDEN_TAG = re.compile(r"<(\w+)((?:\s+[-\w:]+(?:=\"[^\"]*\")?)*)\s*/?>", re.S)
DISPLAY_UTILS = frozenset([
    "block", "inline", "inline-block", "flex", "inline-flex", "grid", "inline-grid",
    "contents", "flow-root", "table", "table-cell", "table-row", "list-item",
])
component_files = list((ROOT / "application/views/components").glob("*.php"))
for path in files + component_files:
    text = path.read_text(encoding="utf-8", errors="ignore")
    for tag in HIDDEN_TAG.finditer(text):
        attrs = tag.group(2) or ""
        if not re.search(r"\shidden(?=[\s>])", attrs):
            continue
        classes = re.search(r"class\s*=\s*[\"']([^\"']*)", attrs)
        if not classes:
            continue
        clashing = sorted(set(classes.group(1).split()) & DISPLAY_UTILS)
        if clashing:
            errors.append(
                f"{path}: hidden attribute together with display utility "
                f"({', '.join(clashing)}); the utility overrides [hidden] and the element stays visible"
            )

for path in [ROOT / "application/views/template_tailwindcss.php", ROOT / "public_html/assets"]:
    text = path.read_text(encoding="utf-8", errors="ignore") if path.is_file() else "\n".join(p.read_text(encoding="utf-8", errors="ignore") for p in path.rglob("*.*"))
    if re.search(r"https://(?:cdn\.tailwindcss|cdnjs\.cloudflare|code\.jquery)", text):
        errors.append(f"{path}: runtime CDN dependency")

print(f"frontend audit: {tables} tables, {headers} headers, {manual_tables} manual tables, {len(errors)} errors")
if errors:
    print("\n".join(errors[:100]))
    sys.exit(1)

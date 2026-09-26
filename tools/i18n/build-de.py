#!/usr/bin/env python3
"""Write the German catalogs for Club Events (de_DE plus Swiss/Austrian
variants) and compile them with po2mo.php.

Until 1.4.0 these strings were forced to German on every site by a gettext
filter in the main plugin file. They now live in real catalogs, so German
applies only to German-language sites; translate.wordpress.org takes over
once the plugin is listed there.

Usage: python3 tools/i18n/build-de.py
"""
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LANG = ROOT / "club-events" / "languages"
LOCALES = ["de_DE", "de_DE_formal", "de_CH", "de_CH_informal", "de_AT"]

STRINGS = {
    "All": "Alle",
    "No upcoming events.": "Keine bevorstehenden Anlässe.",
    "No events found.": "Keine Anlässe gefunden.",
    "Add to Calendar": "Zum Kalender hinzufügen",
    "Add to Calendar (.ics)": "Zum Kalender (.ics)",
    "Subscribe": "Abonnieren",
    "Subscribed!": "Abonniert!",
    "Subscribing…": "Wird abonniert…",
    "Loading…": "Wird geladen…",
    "All day": "Ganztägig",
    "All-day event": "Ganztägiger Anlass",
    "Location": "Ort",
    "Details": "Details",
    "Upcoming": "Bevorstehend",
    "Past": "Vergangen",
    "Read more": "Weiterlesen",
    "Share": "Teilen",
    "Copy link": "Link kopieren",
    "Link copied!": "Link kopiert!",
}


def esc(text):
    return text.replace("\\", "\\\\").replace('"', '\\"')


def main():
    LANG.mkdir(parents=True, exist_ok=True)
    for locale in LOCALES:
        lines = [
            'msgid ""',
            'msgstr ""',
            '"Project-Id-Version: WP Club Events Simple\\n"',
            f'"Language: {locale}\\n"',
            '"MIME-Version: 1.0\\n"',
            '"Content-Type: text/plain; charset=UTF-8\\n"',
            '"Content-Transfer-Encoding: 8bit\\n"',
            '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
            '"X-Domain: club-events\\n"',
            "",
        ]
        for msgid, msgstr in STRINGS.items():
            lines += [f'msgid "{esc(msgid)}"', f'msgstr "{esc(msgstr)}"', ""]
        po = LANG / f"club-events-{locale}.po"
        po.write_text("\n".join(lines), encoding="utf-8")
        subprocess.run(["php", str(ROOT / "tools/i18n/po2mo.php"), str(po)], check=True)


if __name__ == "__main__":
    main()

# STV Malters – nächste Schritte (Stand 24.11.2026, abends)

WPVibe-Kontingent: 100 Aufrufe / 24 h (Free). Erst die Sparmassnahmen, dann
die Arbeit. Alles über WordPress / Astra / Spectra, Code nur wo nötig.

## 0. Sparen
- WPVibe-Plugin 1.20.5 → 1.24.3 aktualisieren (`plugin update vibe-ai`,
  2 Aufrufe). Neuere Versionen können mehrere Inhaltsänderungen pro Aufruf
  (`content_edit_batch`).
- Prüfen, ob „Enable Abilities“ (Astra → MCP) an ist → Astra-Werte gebündelt.
- Status der Farb-Freigabe `op_683486f7d1344a90` (Anlass-Farben) prüfen.

## 1. Galerie dynamisch (ohne eigenes JavaScript)
- Die vier Alben sind Unterseiten von „Galerie“ (ID 1388):
  1870 Vereinsturntag & Kids Sprint (Bild 1541), 1871 Männer- & Frauensporttag
  (1544), 1872 Wanderungen (1538), 1873 Winteranlässe (1542).
  → je Beitragsbild setzen (`_thumbnail_id`).
- Auf der Galerie-Seite den HTML-Block `stv-gallery` durch einen Query-Loop
  ersetzen: `postType: page`, `parents: [1388]`, neueste zuerst, Raster
  3/2/1 Spalten, Beitragsbild 3:2 + Titel + Auszug. Neue Alben = neue
  Unterseite mit Beitragsbild, erscheinen automatisch.
- Das Montserrat-Inline-CSS fällt damit weg.

## 2. Feste Werte in Blöcken entfernen (Astra soll greifen)
Startseite (ID 915): `font-size:14px`/`"fontSize":"14px"` (Abteilungs-Karten),
`11px` (Kennzahlen-Beschriftung), `letter-spacing` an Titeln,
`border-radius:3px` / `"radius":"3px"` an Buttons, Hero-Untertitel 20px.

## 3. Überschriften-Reihenfolge
- Vorstandsseiten (1391, 1598, 1606, 1608, 1610): Namen h4 → h3.
- Riegen-Agenden (1599, 1607, 1609, 1611) und Gesamtverein-Agenda (1394):
  `heading_level="2"` am Zeitstrahl, sobald Plugin 1.7.0 installiert ist
  (1.2.1 ignoriert das Attribut gefahrlos).

## 4. Plugin
- Club Events 1.7.0 installieren (Zip vom 24.11.). Danach im Zusatz-CSS die
  Übergangsregeln „Plugin 1.2.x“ entfernen.

## 5. Kontrolle
- Playwright-Durchlauf aller Menüseiten auf 390 px (kostet kein Kontingent):
  seitliches Scrollen, Bilder, Schriften, Überschriften, englische Reste.

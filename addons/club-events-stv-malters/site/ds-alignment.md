# STV Malters – Abgleich mit dem Design-System

> **Umgesetzt am 24.11.2026.** Das Zusatz-CSS der Seite ist durch
> `stv-site.css` (in diesem Ordner) ersetzt worden; die vorherige Fassung liegt
> als `custom-css-before-2026-11-24.css` hier und als Revision im Customizer.
> Fusszeile (Datenschutz-Link, keine h4) und Hero-Bilder sind ebenfalls
> angepasst. Die Abschnitte unten dokumentieren die Analyse.

Quelle: digital.stvmalters.ch (Stand 24.11.2026). Ziel: mataxacu.myhostpoint.ch
(Astra). Gilt für *Design → Customizer → Zusätzliches CSS*.

## Design-Tokens (digital.stvmalters.ch)

| Token | Wert |
|---|---|
| Primär | `#25319D` (hsl 234 62% 38%) |
| Hintergrund | `#F5F7FA` |
| Text | `#313649` |
| Text gedämpft | `#565E81` |
| Fläche sekundär | `#ECEEF4` |
| Akzent / Hover-Fläche | `#DFE3EC` |
| Rahmen / Input | `#DBDFE6` |
| Titel-Schrift | **Space Grotesk** 500/600/700 |
| Text-Schrift | **Inter** (variabel) |
| Radius | **8px überall** (`--radius: .5rem`, xl = 2xl = radius) |
| Schatten | sm `0 1px 2px` 6 %, md `0 4px 12px` 10 %, lg `0 8px 24px` 12 % (Farbe `#313649`) |
| Primär-Button | Space Grotesk 600, Höhe 44px, Radius 8px, Schatten `0 8px 24px rgba(37,49,157,.25)` |
| Sekundär-Button | 1px Rahmen `#DBDFE6`, weiss, Hover `#DFE3EC` |
| Titel | bold, `letter-spacing: -0.025em`, **keine Grossbuchstaben** |

Astra ist bereits richtig eingestellt (Palette `#25319D / #F5F7FA`, Inter,
Space Grotesk). Abweichungen entstehen ausschliesslich im Zusätzlichen CSS.

## 1. Im bestehenden CSS ersetzen (Suchen/Ersetzen)

1. Erste Zeile löschen:
   `@import url('https://fonts.googleapis.com/css2?family=Montserrat…&family=Noto+Sans…');`
   (lädt zwei fremde Schriften von Google, unnötig und datenschutzrelevant)
2. `'Montserrat'` und `Montserrat` → `'Space Grotesk'`
3. `'Noto Sans'` → `Inter`
4. `border-radius:10px`, `:12px`, `:14px`, `:16px` → `border-radius:8px`
5. Im Block `/* === STV mobile v6 === */` diese Zeile löschen (macht die
   Blog-Karten auf dem Handy fast randlos, widerspricht `STV-BLOG-MOBILE-FIX`):
   `.uagb-post__inner-wrap .uagb-post__text{padding-left:6px !important;padding-right:6px !important;}`
6. Doppelte Blöcke zusammenführen: Tabellen sind in `Tabellen`,
   `STV-DESIGN-POLISH` und `STV-QA-FIX` je neu definiert, Buttons in
   `STV-UI-CONSISTENCY` und `STV button harmony`, Blog-Karten auf dem Handy in
   `STV-FORM-CSS` (Ende), `STV-BLOG-MOBILE-FIX` und `STV mobile v6`. Jeweils
   nur den letzten Block behalten.
7. `.stv-footer`, `.stv-fname`, `.stv-fsub`, `.stv-social` (inkl. `STV icons v1`
   für Instagram/Facebook) werden von der aktuellen Fusszeile nicht mehr
   verwendet und können weg, sofern die Social-Links nicht zurückkommen.
8. Nach Update auf Club Events 1.6.1 die Blöcke `STV-UI-CONSISTENCY` und
   `STV button harmony` (alle `.ce-*`-Regeln) löschen: Das Plugin übernimmt
   Schrift, Farbe und Radius jetzt selbst aus Astra. Die `!important`-Regeln
   würden sonst die Design-System-Werte wieder überschreiben.

## 2. Am Ende anfügen

```css
/* === STV Design-System-Abgleich (digital.stvmalters.ch) 2026-11 === */
:root{
  --stv-primary:#25319D; --stv-primary-dk:#1B2566;
  --stv-bg:#F5F7FA; --stv-text:#313649; --stv-muted:#565E81;
  --stv-border:#DBDFE6; --stv-radius:8px;
  --stv-shadow-md:0 4px 12px rgba(49,54,73,.10);
  --stv-shadow-lg:0 8px 24px rgba(49,54,73,.12);
}
body{color:var(--stv-text);}
h1,h2,h3,h4,h5,h6,.entry-content h1,.entry-content h2,.entry-content h3{letter-spacing:-.025em;}
/* Hero-Titel wie im Design-System: bold, ohne Versalien */
.wp-block-cover h1,.ast-single-entry-banner .entry-title{text-transform:none!important;font-weight:700!important;letter-spacing:-.025em!important;}
/* Buttons: 44px hoch, 8px Radius, Primär mit Hero-Schatten */
.entry-content .wp-block-button__link,.ast-button,.srfm-submit-button{min-height:44px;border-radius:var(--stv-radius)!important;font-weight:600;}
.entry-content .wp-block-button:not(.is-style-outline) .wp-block-button__link{box-shadow:0 8px 24px rgba(37,49,157,.25);}
/* Karten, Bilder, Formulare: ein Radius */
.uagb-post-grid article.uagb-post__inner-wrap,.entry-content .wp-block-image img,.srfm-input-common{border-radius:var(--stv-radius)!important;}
.uagb-post-grid article.uagb-post__inner-wrap{box-shadow:var(--stv-shadow-md);}
.uagb-post-grid article.uagb-post__inner-wrap:hover{box-shadow:var(--stv-shadow-lg);}
/* Gedämpfter Text */
.stv-role,.uagb-post-grid .uagb-post__date,.single-post .entry-meta{color:var(--stv-muted)!important;}
/* Barrierefreiheit: Links im Fliesstext nicht nur über Farbe (Lighthouse link-in-text-block) */
.entry-content p a:not(.wp-block-button__link):not(.stv-fbtn),
.entry-content li a,.site-footer p a:not(.stv-fbtn){text-decoration:underline;text-underline-offset:2px;}
/* Fusszeilen-Titel (siehe Abschnitt 3) */
.stv-fhead{color:#fff;font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:15px;letter-spacing:.3px;text-transform:uppercase;margin:0 0 12px;}
/* Handy: Blog-Karten mit normalem Innenabstand */
@media (max-width:768px){
  .uagb-post__inner-wrap .uagb-post__text{padding-left:1.1rem!important;padding-right:1.1rem!important;}
  .wp-block-cover h1{font-size:2.25rem!important;line-height:1.1;}
}
/* === Ende Design-System-Abgleich === */
```

## 3. Fusszeile (Astra → Footer Builder → HTML 2)

Das `<h4>` bricht auf **jeder** Seite die Überschriften-Reihenfolge
(Lighthouse `heading-order`), und die Datenschutzerklärung ist nirgends
verlinkt. Ersetzen durch:

```html
<p class="stv-fhead">Kontakt</p>
<p>Turnverein STV Malters<br>6102 Malters, LU</p>
<p><a class="stv-fbtn" href="/kontakt/">Kontakt aufnehmen →</a></p>
```

Copyright-Zeile (Footer Builder → Copyright):

```html
Copyright © 2026 STV Malters · <a href="/impressum/">Impressum</a> · <a href="/datenschutz/">Datenschutz</a> · <a href="/intern/">Intern</a>
```

Im Widget „Footer Widget 1“ steht „Kontakt aufnehmen“ ein zweites Mal. Dort
kann der Link entfallen.

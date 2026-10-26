# Flatsome ekstra farger

WordPress-plugin som utvider [Flatsome](https://uxthemes.com/flatsome/) med egne farger og knappestiler. Fargene blir CSS-variabler, vises i Customizer og som fargeprøver i UX Builder. Knappestiler kan velges under **Color** på knappeelementer i UX Builder.

**Versjon:** 1.0.0  
**Forfatter:** Benjamin Pedersen

## Krav

- WordPress
- Flatsome-tema (med UX Builder)

## Installasjon

1. Last ned eller klon dette repoet.
2. Legg mappen `flatsome-ekstra-farger` i `wp-content/plugins/`.
3. Aktiver **Flatsome ekstra farger** under **Plugins** i WordPress.

Alternativt: pakk mappen som zip og bruk **Plugins → Add New → Upload Plugin**.

## Oppsett

Gå til **Utseende → Ekstra farger** (krever `edit_theme_options`).

### Farger

- Legg til rader med **navn** og **farge** (hex, f.eks. `#1a2b3c`).
- Navnet brukes som etikett i UX Builder og Customizer. Ved lagring genereres en unik ID (slug) og CSS-variabelen `var(--fs-color-{id})`.
- Fargene vises under **Style → Colors** i Customizer og som ekstra prøver i Flatsome sine fargevelgere i UX Builder.
- Du kan finjustere fargeverdier i Customizer; disse lagres som theme mod.

### Knappestiler

- Hver stil har **navn**, **bakgrunn** og **tekstfarge**.
- Klassen blir `fef-btn-{id}` (f.eks. `fef-btn-merke`).
- Stilene dukker opp i **Color**-listen for knapper i UX Builder, sammen med Flatsome sine standardvalg.
- Outline-, link- og underline-varianter støttes via pluginens CSS.

## Tekniske detaljer

| Type | Eksempel |
|------|----------|
| Fargevariabel | `--fs-color-min-farge` |
| Knappeklasse | `.fef-btn-min-stil` |
| Knappe-CSS-variabler | `--fef-btn-{id}-bg`, `--fef-btn-{id}-fg` |

Følgende ID-er er reservert (kan ikke brukes som egne farger/knapper): `primary`, `secondary`, `success`, `alert`, `white`.

Farge- og knappedefinisjoner lagres i WordPress-options `fef_colors` og `fef_button_styles`. Aktive fargeverdier kan også ligge i theme mods (`fef_color_{id}`).

## Lisens

Ingen lisens er angitt i repoet ennå. Legg til en `LICENSE`-fil om du vil åpne kildekoden under en bestemt lisens.

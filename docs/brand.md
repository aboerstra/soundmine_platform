# TheSoundMine — Brand spec v1.1

Source of truth for the logo, colours and UI colour conventions. Use these values exactly.

## Logo

- **Mark:** a 4×4 grid of rounded steps (18/96 step size, 4/96 gap, 3/96 corner radius).
  - Unlit steps = **rock**.
  - A **gold seam** of 7 steps climbs from bottom-left to top-right: (row,col) (3,0) (2,0) (2,1) (1,1) (1,2) (0,2) (0,3), with row 0 at the top.
  - The **playhead** is the single white step at (1,2).
- **Meaning:** the grid is the mine, and the gold vein is the pattern you dig out. Amber/gold also means *queued* in the common hardware LED convention, which is what the product does.
- **Wordmark:** `thesoundmine`, Space Grotesk. "the" 400, "sound" 700, "mine" 700 in gold. Tracking −0.03em.
- **Clear space:** one step width on all sides. **Minimum size:** 16 px for the mark, 120 px wide for the lockup. Below 24 px, use the mark alone.
- **Avoid:** a multicolour seam, a red or orange seam, rotating or rearranging the steps.

## Files

| File | Use |
|---|---|
| `thesoundmine-lockup-on-dark.svg/.png` | Primary lockup on dark grounds |
| `thesoundmine-lockup-on-light.svg/.png` | Lockup on light grounds |
| `thesoundmine-mark-on-dark.svg/.png` | Mark only, dark grounds |
| `thesoundmine-mark-on-light.svg/.png` | Mark only, light grounds |
| `thesoundmine-app-icon.svg / -1024.png` | App and plugin icon (macOS/Windows) |
| `favicon.svg / favicon-32.png` | Website favicon |

All SVG text is converted to outlines, so no font is needed to render the logo files.


## Naming

- **Brand:** TheSoundMine. In logos and lockups it's always lowercase `thesoundmine`; in running text, write TheSoundMine.
- **Products:** TheSoundMine Pattern Conductor, TheSoundMine Harmonic Conductor, TheSoundMine Studio. After the first mention, the product name alone is fine.

## Product family

Every product icon is the master 4×4 grid. Only the lit steps change, and they show what the product does.

| Product | Icon pattern | Main | Tint | On light |
|---|---|---|---|---|
| Pattern Conductor | Four on the floor in drum lanes: kick on every beat, claps on 2 and 4, white downbeat. | `#35C4D4` | `#A3D6DC` | `#14838F` |
| Harmonic Conductor | Piano roll: a held three-note chord moving to the next, adapted top note in white. | `#AA78E2` | `#D0BAE8` | `#6F39AC` |
| Studio | Signal path: a gold route down and across the grid, ending at the playhead. | `#E2B33C` | `#F7DE8E` | `#B8860B` |

**Rules**

- One hue per product, taken from the track wheel. Gold belongs to the masterbrand and Studio.
- Product colours are never green, red or amber: those mean playing, recording and queued in the UI.
- Exactly one white step per icon: the playhead, the moment that matters.
- Lockup: icon + `thesoundmine` (Space Grotesk 400, muted) + product name (700).

**Product files** (in `products/`): for each product, `-app-icon.svg` / `-app-icon-1024.png`, `-mark-on-dark/light.svg/.png`, `-lockup-on-dark/light.svg/.png`. All text is outlined.

```css
:root {
  --tsm-pattern-conductor: #35C4D4;
  --tsm-pattern-conductor-tint: #A3D6DC;
  --tsm-pattern-conductor-on-light: #14838F;
  --tsm-harmonic-conductor: #AA78E2;
  --tsm-harmonic-conductor-tint: #D0BAE8;
  --tsm-harmonic-conductor-on-light: #6F39AC;
  --tsm-studio: #E2B33C;
  --tsm-studio-tint: #F7DE8E;
  --tsm-studio-on-light: #B8860B;
}
```

## Brand colours

| Token | Hex | Use |
|---|---|---|
| Ink | `#131417` | Primary ground |
| Panel | `#1C1D21` | Surfaces |
| Rock | `#34353B` | Unlit steps in mark |
| Muted | `#A3A3AB` | Secondary text |
| Paper | `#F4F1EA` | Light ground, text on dark |
| Gold | `#E2B33C` | Seam on dark, queued state |
| Gold · light | `#B8860B` | Seam on light grounds |
| Gold · text | `#7A5800` | "mine" on light grounds |

## UI state colours (common hardware LED convention)

| State | Hex | Meaning |
|---|---|---|
| Off | `#26272C` | Unlit step |
| On | `#5594E7` | Lit step (track colour) |
| Playhead | `#F4F1EA` | Current step |
| Playing | `#3FCB7E` | Transport running |
| Recording | `#F0524F` | Armed / recording |
| Queued | `#E2B33C` | Lands on next boundary |

"On" uses the step's track colour; `#5594E7` is shown as an example.

## Track / device colours

Evenly spaced hues. Assign in this order to tracks or devices, then wrap around.

| # | Name | Hex |
|---|---|---|
| 1 | Red | `#EA5D53` |
| 2 | Orange | `#F08D42` |
| 3 | Yellow | `#F0D342` |
| 4 | Green | `#4DCB77` |
| 5 | Cyan | `#35C4D4` |
| 6 | Blue | `#5594E7` |
| 7 | Purple | `#AA78E2` |
| 8 | Pink | `#E56CB0` |

## Type

- **Space Grotesk** 400/700: brand, headings, UI text.
- **JetBrains Mono** 500: values, device data, labels (PC numbers, channels, lookahead).
- Both are free Google Fonts (SIL Open Font License).

## CSS tokens

```css
:root {
  --tsm-ink: #131417;
  --tsm-panel: #1C1D21;
  --tsm-rock: #34353B;
  --tsm-muted: #A3A3AB;
  --tsm-paper: #F4F1EA;
  --tsm-gold: #E2B33C;
  --tsm-gold-light: #B8860B;
  --tsm-gold-text: #7A5800;
  --tsm-state-off: #26272C;
  --tsm-state-on: #5594E7;
  --tsm-state-playhead: #F4F1EA;
  --tsm-state-playing: #3FCB7E;
  --tsm-state-recording: #F0524F;
  --tsm-state-queued: #E2B33C;
  --tsm-track-1: #EA5D53; /* Red */
  --tsm-track-2: #F08D42; /* Orange */
  --tsm-track-3: #F0D342; /* Yellow */
  --tsm-track-4: #4DCB77; /* Green */
  --tsm-track-5: #35C4D4; /* Cyan */
  --tsm-track-6: #5594E7; /* Blue */
  --tsm-track-7: #AA78E2; /* Purple */
  --tsm-track-8: #E56CB0; /* Pink */
}
```

# Design System

Reference for the visual language of `rytkoset-theme`. Read this before writing or modifying CSS so new UI stays consistent. The source of truth is `assets/css/base.css` (`:root` block) — this document describes intent and usage.

## Principles

- Mobile-first, WCAG 2.1 AA, semantic HTML — no Bootstrap or other CSS frameworks
- Always use CSS variables for color, radius, shadow, and transition values — never hardcode
- The theme supports a light (default) and a dark theme via `:root[data-theme="dark"]`. Any new color must be defined for both themes
- No build step: `style.css` imports every module file directly

## Color tokens

Defined in `assets/css/base.css`. Use the variable, not the hex value.

| Token | Light value | Purpose |
| --- | --- | --- |
| `--color-bg` | `#f5f5f5` | Page background |
| `--color-surface` | `#ffffff` | Card / panel background |
| `--color-surface-alt` | `#f9fafb` | Secondary surface (alternating sections) |
| `--color-surface-muted` | `#edf2f7` | Muted fill |
| `--color-text` | `#1f2933` | Body text |
| `--color-text-mute` | `#5b6577` | Secondary / meta text |
| `--color-text-inverted` | `#ffffff` | Text on dark backgrounds |
| `--color-primary` | `#0f4c81` | Brand blue — links, primary surfaces |
| `--color-primary-dark` | `#0b315b` | Hover / nav background |
| `--color-primary-deeper` | `#08254a` | Text on accent buttons |
| `--color-primary-light` | `#3b76a8` | Lighter brand accents |
| `--color-accent` | `#fbbf24` | Amber accent — primary buttons, highlights |
| `--color-accent-hover` | `#faca45` | Accent hover state |
| `--color-border-neutral` | `rgba(0,0,0,0.1)` | Standard borders |

Dark theme overrides all of the above in the `:root[data-theme="dark"]` block — when adding a color, add it there too.

## Radius, shadow, transition

| Token | Value | Use |
| --- | --- | --- |
| `--radius-small` | `4px` | Small elements |
| `--radius-medium` | `8px` | Cards, panels, form fields |
| `--radius-m` / `--radius-l` | `10px` / `14px` | Larger panels (`.card` uses `--radius-l`) |
| `--radius-pill` | `999px` | Buttons, badges, chips |
| `--shadow-card` | — | Resting card shadow |
| `--shadow-card-hover` | — | Card hover shadow |
| `--shadow-dropdown` | — | Menus / dropdowns |
| `--transition-fast` | `150ms ease` | Micro-interactions |
| `--transition-normal` | `250ms ease` | Color / state changes |
| `--transition-hover` | `0.2s ease` | Hover transforms |

## Typography

- `--font-body` — system UI font stack; `--font-heading` aliases it (no separate display font)
- Body `line-height: 1.6`; headings `1.25`
- Use `clamp()` for large headings, e.g. titles: `clamp(2rem, 5vw, 3.8rem)`
- Meta / label text: uppercase, `font-weight: 700-800`, `letter-spacing` ~`0.03-0.12em`, `font-size` ~`0.85rem`

## Layout

- `.container` — `width: min(1100px, 100% - 2rem)`, centered
- `.section` — `3.5rem 0` vertical padding; variants `.section--light`, `.section--accent`
- `.section__narrow` (720px) / `.section__wide` (1200px) — content width caps
- `.grid` with `--grid--3` for three-column layouts
- Breakpoints in use: `48rem`, `64rem` (rem-based), and `640px / 960px / 1280px` (px-based, hero areas)
- Front-page content bands (`assets/css/home.css`) alternate light/dark with the `--home-band-light`, `--home-band-light-edge`, `--home-band-dark`, `--home-band-dark-2` tokens. These adapt in the dark theme: a "light" band uses the dark surface token and a "dark" band deepens, keeping the alternating rhythm coherent. Light bands use `--color-text`/`--color-text-mute` (theme-aware); dark bands always use light text since navy is dark in both themes

## Core components

- `.btn` + modifier — `.btn--primary` (accent fill), `.btn--ghost` (transparent on dark), `.btn--light` (white). Pill radius, `min-height: 40px`, `font-weight: 800`
- `.card` — surface background, `--radius-l`, `--shadow-card`; lifts on hover with `translateY(-2px)`. Child elements `.card__title`, `.card__text`, `.card__link`
- Cards for content types follow the `*-card` / `*-card__media` / `*-card__body` pattern (see `.blog-card`, `.event-card`)
- Form fields: `min-height: 48px`, `--radius-medium`, `1px` neutral border, accent focus outline
- WordPress table block (`.wp-block-table.is-style-stripes`, used for content tables like the magazine table of contents): core paints striped rows with a fixed light gray, so `components.css` overrides the odd-row `td`/`th` background with `--color-surface-muted` (follows light/dark mode). Right-aligned cells (`.has-text-align-right`) get `white-space: nowrap` so multi-digit numbers don't break mid-number in narrow columns

## Focus & accessibility

- Global focus style: `3px solid var(--color-accent)` outline with `3px` offset on links, buttons, `[tabindex]`
- Primary buttons use a white outline + accent box-shadow on focus
- Form inputs `min-height: 48px` for touch targets
- Maintain visible focus on every interactive element

## Conventions when adding styles

- Add new rules to the matching module file (`components.css`, `layout.css`, etc.) — do not put everything in one file
- Use BEM-style class names: `block__element--modifier`
- Reference existing tokens; if a genuinely new token is needed, define it in `base.css` for both light and dark themes
- Test both themes and mobile width before considering the change done

## Do not

- Do not hardcode hex colors, px radii, or shadows that duplicate an existing token
- Do not add a CSS framework or external UI kit
- Do not introduce new fonts without a deliberate decision — the theme intentionally uses the system font stack
- Do not add a build step (Sass, PostCSS, npm tooling)

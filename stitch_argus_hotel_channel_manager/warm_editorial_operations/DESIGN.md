---
name: Warm Editorial Operations
colors:
  surface: '#FFFFFF'
  surface-dim: '#dddad2'
  surface-bright: '#fcf9f1'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3eb'
  surface-container: '#f1eee6'
  surface-container-high: '#ebe8e0'
  surface-container-highest: '#e5e2da'
  on-surface: '#1c1c17'
  on-surface-variant: '#43474a'
  inverse-surface: '#31312b'
  inverse-on-surface: '#f4f1e9'
  outline: '#73787b'
  outline-variant: '#c3c7ca'
  surface-tint: '#536068'
  primary: '#111e25'
  on-primary: '#ffffff'
  primary-container: '#26333a'
  on-primary-container: '#8d9ba4'
  inverse-primary: '#bbc9d1'
  secondary: '#57615d'
  on-secondary: '#ffffff'
  secondary-container: '#d8e2dd'
  on-secondary-container: '#5b6561'
  tertiary: '#00211b'
  on-tertiary: '#ffffff'
  tertiary-container: '#113730'
  on-tertiary-container: '#7aa197'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e5ee'
  primary-fixed-dim: '#bbc9d1'
  on-primary-fixed: '#101d24'
  on-primary-fixed-variant: '#3b4950'
  secondary-fixed: '#dbe5e0'
  secondary-fixed-dim: '#bfc9c4'
  on-secondary-fixed: '#141d1b'
  on-secondary-fixed-variant: '#3f4945'
  tertiary-fixed: '#c3ebe0'
  tertiary-fixed-dim: '#a7cfc4'
  on-tertiary-fixed: '#00201a'
  on-tertiary-fixed-variant: '#294d45'
  background: '#fcf9f1'
  on-background: '#1c1c17'
  surface-variant: '#e5e2da'
  border: '#E4E1D9'
  danger: '#AD3E39'
  warning: '#B78128'
  nav-active-bg: '#EAF0EC'
  danger-soft: '#FDF2F2'
  warning-soft: '#FEF9EE'
  success-soft: '#F0F5F3'
typography:
  display-lg:
    fontFamily: Noto Serif
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 38px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Noto Serif
    fontSize: 26px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  heading-md:
    fontFamily: Noto Serif
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 30px
    letterSpacing: -0.01em
  heading-sm:
    fontFamily: Noto Serif
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 26px
  title-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
  title-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 22px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 18px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
  data-mono:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 0.75rem
  margin: 1.5rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1rem
  space-xl: 1.5rem
---

## Brand & Style

This design system expresses a calm, restrained, and trustworthy editorial identity tailored for independent hotel operations. It fuses the tactile warmth of high-end boutique hospitality with the precision, data density, and reliability required for daily property management. 

Drawing from modern warm minimalism and the structured utility of component-driven workspaces, the interface replaces cold, sterile enterprise conventions with an organic palette of warm limestone, crisp paper-white working planes, deep ink typography, and grounding evergreen accents. 

Visual priority is strictly anchored in operational legibility:
- High typographic contrast allows desk staff and revenue managers to scan rates, inventory counts, and sync statuses quickly under varying ambient conditions.
- Clutter, dramatic gradients, and ornamental illustrations are eliminated in favor of clean grid alignments, deliberate white space, and hairline borders.
- State changes provide immediate, predictable feedback without jarring animations or saturated neon alarms.

## Colors

The color palette establishes an architectural balance between foundational warmth and high-contrast operational clarity.

- **Primary (`#26333A` - Deep Ink):** Grounding tone for all primary headings, operational metrics, interactive labels, and high-priority data points.
- **Secondary (`#69736F` - Muted Sage-Gray):** Structural secondary tone for supporting text, metadata, table headers, inactive iconography, and tertiary descriptors.
- **Tertiary (`#4C7168` - Evergreen Accent):** Purposeful semantic action tone used for primary buttons, active navigation indicators, confirmed operational confirmations, and healthy sync states.
- **Neutral (`#F3F0E8` - Warm Limestone):** Canvas and page background framing the elevated operational planes, softening prolonged screen sessions.
- **Surface (`#FFFFFF`):** High-clarity white for cards, inputs, data grids, sidebars, modal dialogues, and calendar cells.
- **Border (`#E4E1D9`):** Hairline structure for card dividers, table borders, and control edges.
- **Danger (`#AD3E39`):** Reserved for destructive actions, severe channel disconnects, and overbooked inventory. Soft variant (`#FDF2F2`) applies to critical cell fills.
- **Warning (`#B78128`):** Reserved for stop-sell restrictions, impending sync alerts, and pending reservation actions. Soft variant (`#FEF9EE`) applies to warning cell fills.

Never convey status via color alone; combine every color state with textual labels or explicit icons.

## Typography

The typographic hierarchy pairs the editorial, hospitality-led cadence of **Noto Serif** with the utilitarian precision of **Inter**.

- **Display & Headings (Noto Serif):** Used strictly for property names, page titles, section introductions, and modal headers. It imbues a quiet, classical luxury without impeding functionality.
- **Interface & Operational Text (Inter):** Applied to navigation rails, form fields, action triggers, tabular numbers, calendar cells, and data columns.
- **Tabular Numerals:** All inventory numbers, dates, room totals, and currency values must utilize tabular lining figures (`font-variant-numeric: tabular-nums`) to preserve column alignment and rapid vertical scanning.
- **Currency & Financials:** Format monetary values with appropriate regional digit groupings and explicit symbols (e.g., ₹10,500) paired with medium weights to avoid visual clipping in dense grids.

## Layout & Spacing

The workspace uses a structured hybrid grid combining a persistent desktop navigation rail with a fluid primary operational canvas:

- **Desktop Framework (1024px+):** Fixed 240px navigation rail along the left edge, fixed operational top-bar for property-switching and global health alerts, and a fluid content canvas structured with a 24px (`1.5rem`) gutter and margin system.
- **Tablet / Responsive Breakpoints (768px - 1023px):** The left rail condenses into an icon-and-popout state (68px width). Layout grids collapse from multi-column operational cards into stacked full-width segments.
- **Mobile (< 768px):** Navigation transitions into a bottom navigation sheet or hamburger drawer. Canvas margins drop to 16px (`1rem`).
- **Calendar & Matrix Workspaces:** The inventory calendar features a sticky left column pinned for room types and rates. The date horizontal axis must maintain a minimum cell width of 48px to prevent truncation, enforcing smooth native horizontal scrolling over responsive column compression.

## Elevation & Depth

This system avoids heavy drop shadows, skeuomorphic bevels, and multi-tiered floating surfaces. Depth is established through structural layering and hairline borders:

- **Flat Canvas Tiers:** The page background sits at the lowest tier in Warm Limestone (`#F3F0E8`). Operational surfaces—such as cards, data tables, and calendar boards—rest on pure white (`#FFFFFF`) defined strictly by a 1px border of `#E4E1D9`.
- **Low-Contrast Ambient Elevation:** For floating overlays (contextual menus, date pickers, popovers, and dialogs), use an ambient, low-contrast shadow tinted with the primary ink tone:
  - *Overlay Shadow:* `0 4px 20px -2px rgba(38, 51, 58, 0.08), 0 2px 6px -1px rgba(38, 51, 58, 0.04)`
- **Hover & Active Depth:** Buttons and table rows indicate interactivity via subtle background shifts rather than vertical lift.

## Shapes

The geometric framework is tailored for compact data layout with softened, human-centered contours:

- **Controls (Inputs, Buttons, Dropdowns):** Form elements and compact interactive targets utilize `rounded-sm` (4px to 6px) to maintain crisp, alignment-friendly bounds within tight data forms.
- **Containers & Cards:** Working panels, calendar wraps, and dialog shells use `rounded-md` (8px to 9px), ensuring clear visual separation from the background without feeling overly pillowy.
- **Status Tags & Badges:** Compact operational pills employ full rounding (`rounded-full` / 9999px) to distinctly contrast against rectangular cells and data inputs.
- **Iconography Slots:** Icons live in strict square boundaries (16px, 20px, or 24px) centered inside uniform alignment bounding boxes.

## Components

### Buttons
- **Primary:** Background in Tertiary (`#4C7168`), text in white (`#FFFFFF`), 40px height, 10px 16px padding, 6px border radius. Hover: `#3E5D55`. Active: `#344E48`.
- **Secondary / Outline:** Background in Surface (`#FFFFFF`), border in `#E4E1D9`, text in Primary (`#26333A`), 38px height, 9px 14px padding. Hover: `#F3F0E8`.
- **Destructive:** Background in Danger (`#AD3E39`), text in white (`#FFFFFF`), used only for irrevocable deletions or room blocks.

### Inputs & Selects
- 40px standard height, surface background (`#FFFFFF`), 1px solid border (`#E4E1D9`), 6px border radius.
- Focus state: 1px border `#4C7168` accompanied by an outline ring `rgba(76, 113, 104, 0.15)`.
- Labels must consistently render above the field in `label-sm` or `title-sm` with supporting cues in Secondary (`#69736F`).

### Cards & Panels
- Surface white (`#FFFFFF`) background with a 1px outline of `#E4E1D9`, 9px corner radius, and 20px interior padding.
- Headers integrate clear editorial titles with embedded actions aligned to the far right.

### Tables & Inventory Calendar
- **Table Headers:** Background `#F3F0E8` or pure `#FFFFFF` with bottom border `#E4E1D9`, typography in `label-sm` uppercase with muted sage text (`#69736F`).
- **Rows:** 44px minimum touch height, divider line `#E4E1D9`. Numeric columns right-aligned with tabular figures.
- **Calendar Cells:** Minimum 48px width. Show rate on top, availability count in center, and sync dot below. 
  - *Healthy cell:* White background, deep ink text.
  - *Oversold cell:* `#FDF2F2` background, `#AD3E39` text with deficit indicator badge.
  - *Stop-sell cell:* `#FEF9EE` background, `#B78128` text with warning status marker.

### Chips & Badges
- 20px to 24px height, pill-shaped radius, 4px 8px horizontal padding, `label-sm` font.
- *Active/Connected:* Background `#F0F5F3`, text `#4C7168`.
- *Pending:* Background `#FEF9EE`, text `#B78128`.
- *Blocked/Error:* Background `#FDF2F2`, text `#AD3E39`.

### Dialogs & Drawers
- Centered modal dialogs for targeted actions (bulk-rate updates, reservation checks). 12px corner radius, subtle low-contrast shadow, accompanied by a translucent backdrop overlay (`rgba(38, 51, 58, 0.4)`).
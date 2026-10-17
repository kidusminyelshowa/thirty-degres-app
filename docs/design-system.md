# Design system

Decided 14 October 2026 (Linear THI-27). Colours and type live in `resources/css/app.css`; open `/design-system` on any preview link to see them in light and dark mode, in English and Amharic.

## Colours

The brand palette comes from the Thirty Degres studio identity.

| Name  | Hex       | Use                                                |
| ----- | --------- | -------------------------------------------------- |
| Ink   | `#022018` | Text, primary buttons (light mode)                 |
| Mist  | `#F6F9F7` | Page background (light mode)                       |
| Green | `#01803B` | Brand accent, links, focus rings                   |
| Lime  | `#D1F811` | Highlights and active states; primary in dark mode |

Screens use colour roles (`bg-primary`, `text-muted-foreground`, `border-border` and so on), never raw hex values, so light and dark mode both work. Every text and background pair meets WCAG AA contrast (4.5:1 or better).

## Type

- **Latin:** Instrument Sans, self-hosted.
- **Ge'ez (Amharic, Tigrinya):** Noto Sans Ethiopic, self-hosted. Browsers only download it when a page shows Ethiopic text.
- The full brand look will be designed by Kidus later; until then these two fonts and the palette above stand. The studio's Loos Normal is not used.
- Use Tailwind's size scale: `text-4xl` display, `text-2xl` heading, `text-lg` subheading, `text-base` body, `text-sm` small.

## Ge'ez rules

1. Mark Ethiopic text with `lang="am"` (or `lang="ti"`), or use the `font-ethiopic` class. It then gets the Ethiopic font and a line height of 1.8, because Ge'ez is taller and denser than Latin.
2. Don't apply uppercase, small caps or letter-spacing to Ethiopic. The script has no case and tracking breaks its rhythm. The rule above resets both.
3. Prefer `font-medium` or `font-semibold` over `font-bold` for Ethiopic headings; heavy weights fill in the small counters.
4. Leave room in layouts: Amharic labels are often shorter than English but taller. Buttons and table rows must not have a fixed height that clips them.
5. Every screen change is checked with Amharic text before it ships (CLAUDE.md rule 7).

## Amharic words

Use these terms in every screen so the interface stays consistent. Add new ones here as they are agreed.

| English  | Amharic |
| -------- | ------- |
| Upload   | ጫን      |
| Download | አውርድ    |

Proposed with the Amharic interface (THI-38), to be confirmed by Kidus. They are used throughout `lang/am.json`; change a word there and here together.

| English        | Amharic        |
| -------------- | -------------- |
| Workspace      | የስራ ቦታ         |
| Brand          | ብራንድ           |
| Sub-brand      | ንዑስ ብራንድ       |
| Library        | ማከማቻ           |
| Guidelines     | መመሪያዎች         |
| File           | ፋይል            |
| Draft          | ረቂቅ            |
| Approve        | አጽድቅ           |
| Approved       | የጸደቀ           |
| Archive        | ወደ ማህደር        |
| Publish        | አትም            |
| Owner          | ባለቤት           |
| Admin          | አስተዳዳሪ         |
| Member         | አባል            |
| Brand manager  | የብራንድ ኃላፊ      |
| Editor         | አርታዒ           |
| Viewer         | ተመልካች          |
| Partner        | አጋር            |
| Share          | አጋራ            |
| Link           | ሊንክ            |
| Tag            | መለያ            |
| Logo           | አርማ            |
| Typeface       | የፊደል ቅርጽ       |
| Password       | የይለፍ ቃል        |
| Passkey        | የመግቢያ ቁልፍ      |
| Two-step login | ባለሁለት ደረጃ መግቢያ |

## Interface language

The app's own screens come in English and Amharic (`config/languages.php`, `interface`). Every text goes through `t()` in React or `__()` in PHP with English as the source; translations live in `lang/am.json`, and `InterfaceTranslationsTest` fails CI if any text is missing one. People switch from the user menu, **Settings > Appearance and language**, the sign-in pages or a shared link. Signed in, the choice is saved on the account and used for their emails; signed out it is kept in a cookie, and before anyone chooses the browser's language decides. When the interface is Amharic the whole page carries `lang="am"`, so the Ge'ez rules above apply everywhere.

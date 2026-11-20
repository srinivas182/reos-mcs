# ADR 0008: Mobile UI on Unistyles 3

- Status: Accepted
- Date: 2026-11-20

## Context

The phone app must match the web design system: the same tokens, the three themes (light, dark, calm high contrast), the person's text size on top of the phone's own text setting, 48-point targets, English and Spanish. Sprint 2's spec chose Unistyles 3 for styling (approved 20 November 2026).

## Decision

- **Unistyles 3.8** with its Babel plugin (`root: 'src'`). `src/ui/theme.ts` builds the three Unistyles themes from `@reos/tokens`. Each theme carries `scale` (the text-size choice) and `font()`, so every size grows with the person's choice; React Native's own font scaling stacks on top (capped at 2× per text).
- **Preferences** (`src/ui/preferences.ts`): theme, text size and language. They're saved on the device with MMKV 4 and applied through `UnistylesRuntime` (theme switch, adaptive themes for "match my device", `updateTheme` for text size), so screens restyle without re-rendering. From Sprint 3 the account's settings (shared with the web) take precedence.
- **Components** (`src/ui`) follow the **mobile journey screens**, not the web set (amended 20 November 2026, Sprint 2.2e). The web and phone apps share the brand (colours, type, themes, accessibility rules) and the behaviour of shared ideas (undo toast, empty states, announced errors), but each has its own layout language:
  - Structure: `Screen` (scroll content, sticky footer, tab bar slot, dark variant), `ScreenHeader`, `SectionTitle`, `TabBar` with per-role tabs (`src/navigation/roleTabs.ts`, one set per persona from the journeys), `AskBar` (ask anything, with voice).
  - Cards: `Card`, dark `HeroCard`, blue `ActionCard`, `ListRow`, `FeatureTile`, `SwitchRow`, `IconTile`, `Avatar`.
  - Choice and status: `PillGroup` and `SuggestionPills`, `Chip`, `OptionGroup` (lettered answers that lock and mark right or wrong for quizzes), `ProgressBar`, `ProgressRing`, `Callout`, `ChatBubble`.
  - Basics: Text, Button (54-point primary, 50-point secondary, on-hero, ghost, destructive, warning; haptic tap; busy state), TextField, EmptyState, undo toast, Sheet, OfflineBanner, Icon (Lucide).
  - Phone-only measurements (gutter, card and button radii, 54-point buttons) live in `@reos/tokens` as `mobile`, and status tones (`tone*`, `hero*`) are semantic colours checked for contrast in all three themes.
- **Tests:** Jest uses Unistyles' official mocks with our themes registered, in-memory MMKV, and React Native Testing Library 14 (async `render` and `fireEvent`).

## Deviations from the Sprint 2 spec

1. **Components live in `apps/mobile/src/ui`, not `packages/ui-native`.** There is one mobile app; the Unistyles Babel plugin processes only the app's `src` by default; and a package would add build configuration with no consumer to share it. They move to a package if a second React Native app appears.
2. **An on-device component gallery (`/design`, development builds only) instead of React Native Storybook.** It rebuilds candidate journey screens (Today, Practice, Quiz, Ask) from the kit next to the full component list, and reacts to the real Display settings. That gives the same review value with no second Metro entry point. The web Storybook remains the design-review site for the web.
3. **Text is one step larger than the journey PDFs** (16-point body minimum), per the design standard.

## Consequences

- **Expo Go is no longer supported.** Unistyles and MMKV use Nitro native modules, so developers run a development build (`npx expo run:ios` / `run:android`, or the EAS `development` profile from Sprint 2.3).
- `react-native-nitro-modules` is pinned to an exact version, as Unistyles requires; Renovate's Expo group keeps it with the SDK upgrade.

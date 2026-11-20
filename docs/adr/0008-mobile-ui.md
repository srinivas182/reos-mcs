# ADR 0008: Mobile UI on Unistyles 3

- Status: Accepted
- Date: 2026-11-20

## Context

The phone app must match the web design system: the same tokens, the three themes (light, dark, calm high contrast), the person's text size on top of the phone's own text setting, 48-point targets, English and Spanish. Sprint 2's spec chose Unistyles 3 for styling (approved 20 November 2026).

## Decision

- **Unistyles 3.8** with its Babel plugin (`root: 'src'`). `src/ui/theme.ts` builds the three Unistyles themes from `@reos/tokens`. Each theme carries `scale` (the text-size choice) and `font()`, so every size grows with the person's choice; React Native's own font scaling stacks on top (capped at 2× per text).
- **Preferences** (`src/ui/preferences.ts`): theme, text size and language. They're saved on the device with MMKV 4 and applied through `UnistylesRuntime` (theme switch, adaptive themes for "match my device", `updateTheme` for text size), so screens restyle without re-rendering. From Sprint 3 the account's settings (shared with the web) take precedence.
- **Components** (`src/ui`): Text, Button (haptic tap, busy state), TextField (label, hint, announced error), ChoiceTiles (radio semantics), EmptyState, undo toast (`ToastProvider` + `useUndoToast`), Sheet (modal bottom sheet), OfflineBanner (expo-network). The names and behaviour match the web components.
- **Tests:** Jest uses Unistyles' official mocks with our themes registered, in-memory MMKV, and React Native Testing Library 14 (async `render` and `fireEvent`).

## Deviations from the Sprint 2 spec

1. **Components live in `apps/mobile/src/ui`, not `packages/ui-native`.** There is one mobile app; the Unistyles Babel plugin processes only the app's `src` by default; and a package would add build configuration with no consumer to share it. They move to a package if a second React Native app appears.
2. **An on-device component gallery (`/design`, development builds only) instead of React Native Storybook.** It shows every component and reacts to the real Display settings. That gives the same review value with no second Metro entry point. The web Storybook remains the main design-review site.

## Consequences

- **Expo Go is no longer supported.** Unistyles and MMKV use Nitro native modules, so developers run a development build (`npx expo run:ios` / `run:android`, or the EAS `development` profile from Sprint 2.3).
- `react-native-nitro-modules` is pinned to an exact version, as Unistyles requires; Renovate's Expo group keeps it with the SDK upgrade.

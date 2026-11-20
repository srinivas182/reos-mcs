# @reos/mobile

The REOS iOS and Android app (Expo SDK 58, Expo Router). It talks to `apps/platform` only through the REST API at `EXPO_PUBLIC_API_URL`.

The app uses native modules (Unistyles, MMKV), so it runs in a **development build**, not Expo Go:

```bash
npx expo run:ios        # or: npx expo run:android (first time, and after native changes)
EXPO_PUBLIC_API_URL=http://<your-machine-ip>:8000 pnpm dev   # start Metro
pnpm check          # ESLint
pnpm types:check    # TypeScript
pnpm test           # Jest
```

Builds and store releases go through EAS (configured in Sprint 2). Use `npx expo install <package>` to add dependencies so versions match the SDK.

## UI

Components live in `src/ui` (Text, Button, TextField, ChoiceTiles, EmptyState, Sheet, undo toast, OfflineBanner), styled with Unistyles 3 from `@reos/tokens`. In a development build, open **Component gallery (dev)** on the home screen to see them all, and **Display and language** to switch theme, text size and language. See `docs/adr/0008-mobile-ui.md`.


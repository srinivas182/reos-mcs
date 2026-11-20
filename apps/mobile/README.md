# @reos/mobile

The REOS iOS and Android app (Expo SDK 58, Expo Router). It talks to `apps/platform` only through the REST API at `EXPO_PUBLIC_API_URL`.

```bash
EXPO_PUBLIC_API_URL=http://<your-machine-ip>:8000 pnpm dev   # start Metro
pnpm check          # ESLint
pnpm types:check    # TypeScript
pnpm test           # Jest
```

Builds and store releases go through EAS (configured in Sprint 2). Use `npx expo install <package>` to add dependencies so versions match the SDK.

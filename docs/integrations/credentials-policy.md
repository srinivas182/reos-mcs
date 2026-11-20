# Credentials policy

How REOS stores, shares and rotates every key, token and password for the services in the [integrations register](README.md). It applies to Mayura, the client and every contractor.

## Rules

1. **Never in chat, email, tickets or git.** Share a credential only through the vault. The pre-commit hook blocks `.env` files and signing keys; CI never prints secrets.
2. **One home per environment:**

   | Environment | Where credentials live | Who can read them |
   | --- | --- | --- |
   | Local development | `apps/platform/.env` (git-ignored), sandbox keys only | The developer |
   | Shared sandbox keys | 1Password vault **REOS – Sandboxes** | Engineers on the project |
   | Staging and production | AWS Secrets Manager (us-east-2, replicated to us-west-2), read by the app's task role | The app; DevOps through IAM Identity Center with MFA |
   | Mobile signing (Apple, Google) | EAS credentials (managed by Expo) | Mobile lead and DevOps |
   | CI | GitHub Actions environment secrets, scoped per environment | Workflows only |

   **Until the AWS account exists** (decision 20 November 2026, [ADR 0009](../adr/0009-integration-hub.md)): staging and production vendor keys are entered by the super admin in **Settings › Integrations**. They are stored encrypted with `REOS_CREDENTIALS_KEY` (kept in 1Password **REOS – Production**, never in git) and every change is audited. They move to Secrets Manager behind the same screen when AWS is ready.
3. **Production keys never reach a laptop.** No one copies a production secret to a local file. Debugging production uses staging keys or read-only roles.
4. **Every credential has an owner and an account owner.** The register's `owner` field says who holds the vendor account; the vault entry names the engineer responsible for rotation. Accounts are opened in the client's legal entity (billing and ownership), with Mayura invited as admins, never sharing a login.
5. **Least privilege.** Request only the scopes a feature needs (for example Gmail send before Gmail read; see the register). One key per environment and per service; never reuse a key across staging and production.
6. **Rotation:**

   | Credential type | Rotate |
   | --- | --- |
   | API keys and client secrets | Every 90 days, and immediately when someone with access leaves |
   | Webhook signing secrets | Every 180 days (dual-secret overlap, so no downtime) |
   | Database passwords | Managed by Secrets Manager automatic rotation |
   | Apple and Google signing keys | Never rotated casually; stored only in EAS and the 1Password emergency kit |

7. **Tenant-owned credentials** (a brokerage's own Lone Wolf or QuickBooks account, for example) are stored encrypted per tenant (Laravel encryption with a per-environment key from Secrets Manager), are never visible to REOS staff, and are deleted when the tenant disconnects.
8. **Leaks.** If a credential appears anywhere it shouldn't (chat, a commit, a screenshot, a log), treat it as compromised: revoke it at the vendor first, then issue a new one, then check the vendor's audit log. Record the incident in the security log.

## Checklist when an application is approved

- [ ] Store the sandbox and production credentials in the right place above.
- [ ] Update `register.yaml`: `status: approved`, `applied_on`, `approved_on`, and `reference` if the vendor gave one.
- [ ] Run `pnpm integrations:render` (the commit hook does it) and open a pull request.
- [ ] Add the vendor to the data-processing agreement list if `personal_data` is true.

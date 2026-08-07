# CMIH Africa Multi-App Split

This is the phase-two split plan for moving away from one heavy Laravel app.

## Target Workspace

```text
C:\Users\Realtime IT\Desktop\CMIH AFRICA
├── CMIH AFRICA WEBSITE
├── CMIH AFRICA STAFF PORTAL
└── CMIH AFRICA BRANDS PORTAL
```

The split workspace is generated from the current app with:

```powershell
.\tools\prepare-cmih-split.ps1
```

The script intentionally does not copy `.env`, `.login`, `.git`, `vendor`, `node_modules`, or runtime `storage` data.

For a self-contained local/testing copy, use:

```powershell
.\tools\prepare-cmih-split.ps1 -IncludeDependencies -IncludeStorage -IncludeEnv
```

That copies `vendor`, `node_modules`, and `storage`, then creates app-specific `.env` files from the current source `.env` with `APP_URL` and `CMIH_APP_KIND` adjusted. It still does not copy `.login`.

## App Ownership

| Folder | Future URL | App Kind | Primary Purpose |
| --- | --- | --- | --- |
| `CMIH AFRICA WEBSITE` | `https://www.cmih.africa` | `website` | Public website, portfolio, news, contact, public campaign/survey links |
| `CMIH AFRICA STAFF PORTAL` | `https://portal.cmih.africa` | `staff` | Staff dashboard, tasks, HR, finance, operations, messaging, payroll |
| `CMIH AFRICA BRANDS PORTAL` | `https://brands.cmih.africa` | `brands` | Merchandiser/brands portal, route planning, live tracking, SKU AI catalog |

Each app copy gets an `.env.split.example` showing the values to copy into that app's real `.env` during deployment.

## Boundary Switch

The current production app remains in monolith mode by default:

```dotenv
CMIH_APP_KIND=all
```

For split deployments:

```dotenv
CMIH_APP_KIND=website
CMIH_APP_KIND=staff
CMIH_APP_KIND=brands
```

The `RestrictSplitAppAccess` middleware uses this value to redirect users to the correct subdomain when they hit the wrong app.

## Next Deployment Steps

1. Create the three subdomains in hosting/DNS:
   - `www.cmih.africa`
   - `portal.cmih.africa`
   - `brands.cmih.africa`
2. Point each subdomain document root to that app's `public` folder.
3. Create a real `.env` per app using the current production values plus the correct `CMIH_APP_KIND`.
4. Run per app:
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci`
   - `npm run build`
   - `php artisan storage:link`
   - `php artisan optimize:clear`
   - `php artisan optimize`
5. Keep all three apps on the same production database at first.
6. After the subdomains are stable, extract database/storage ownership module by module.

## Important

Do not delete or move the current `CMIH-Website New` production repo until the three subdomains are deployed, tested, and signed off. The split workspace is the safe bridge.

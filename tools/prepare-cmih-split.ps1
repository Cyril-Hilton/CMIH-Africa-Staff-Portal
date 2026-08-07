param(
    [string] $SourceRoot = "C:\Users\Realtime IT\Desktop\CMIH-Website New",
    [string] $TargetRoot = "C:\Users\Realtime IT\Desktop\CMIH AFRICA",
    [switch] $IncludeDependencies,
    [switch] $IncludeStorage,
    [switch] $IncludeEnv
)

$ErrorActionPreference = "Stop"

$source = Resolve-Path -LiteralPath $SourceRoot
New-Item -ItemType Directory -Force -Path $TargetRoot | Out-Null
$target = Resolve-Path -LiteralPath $TargetRoot

$apps = @(
    @{ Name = "CMIH AFRICA WEBSITE"; Kind = "website"; Url = "https://www.cmih.africa" },
    @{ Name = "CMIH AFRICA STAFF PORTAL"; Kind = "staff"; Url = "https://portal.cmih.africa" },
    @{ Name = "CMIH AFRICA BRANDS PORTAL"; Kind = "brands"; Url = "https://brands.cmih.africa" }
)

function Set-EnvValue {
    param(
        [string] $Path,
        [string] $Key,
        [string] $Value
    )

    $lines = Get-Content -LiteralPath $Path
    $escapedValue = $Value
    $pattern = "^$([Regex]::Escape($Key))="
    $updated = $false
    $lines = $lines | ForEach-Object {
        if ($_ -match $pattern) {
            $updated = $true
            "$Key=$escapedValue"
        } else {
            $_
        }
    }

    if (-not $updated) {
        $lines += "$Key=$escapedValue"
    }

    Set-Content -LiteralPath $Path -Value $lines -Encoding UTF8
}

foreach ($app in $apps) {
    $destination = Join-Path $target.Path $app.Name
    New-Item -ItemType Directory -Force -Path $destination | Out-Null
    $resolvedDestination = Resolve-Path -LiteralPath $destination

    if (-not $resolvedDestination.Path.StartsWith($target.Path, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to copy outside target root: $($resolvedDestination.Path)"
    }

    $excludedDirectories = @(".git")
    if (-not $IncludeDependencies) {
        $excludedDirectories += @("vendor", "node_modules")
    }
    if (-not $IncludeStorage) {
        $excludedDirectories += @("storage")
    }

    $excludedFiles = @(".login", ".phpunit.result.cache")
    if (-not $IncludeEnv) {
        $excludedFiles += @(".env")
    }

    $robocopyArgs = @(
        $source.Path,
        $resolvedDestination.Path,
        "/E",
        "/XD"
    ) + $excludedDirectories + @(
        "/XF"
    ) + $excludedFiles + @(
        "/NFL",
        "/NDL",
        "/NJH",
        "/NJS",
        "/NP"
    )

    & robocopy @robocopyArgs | Out-Null

    if ($LASTEXITCODE -gt 7) {
        throw "Robocopy failed for $($app.Name) with exit code $LASTEXITCODE"
    }

    foreach ($dir in @(
        "storage",
        "storage\app",
        "storage\app\public",
        "storage\framework",
        "storage\framework\cache",
        "storage\framework\cache\data",
        "storage\framework\sessions",
        "storage\framework\views",
        "storage\logs",
        "bootstrap\cache"
    )) {
        New-Item -ItemType Directory -Force -Path (Join-Path $resolvedDestination.Path $dir) | Out-Null
    }

    $splitEnv = @"
# Copy these into this app's real .env when deploying this split app.
APP_URL=$($app.Url)
CMIH_APP_KIND=$($app.Kind)
CMIH_WEBSITE_URL=https://www.cmih.africa
CMIH_STAFF_PORTAL_URL=https://portal.cmih.africa
CMIH_BRANDS_PORTAL_URL=https://brands.cmih.africa
"@

    Set-Content -LiteralPath (Join-Path $resolvedDestination.Path ".env.split.example") -Value $splitEnv -Encoding UTF8

    if ($IncludeEnv -and (Test-Path -LiteralPath (Join-Path $resolvedDestination.Path ".env"))) {
        $envPath = Join-Path $resolvedDestination.Path ".env"
        Set-EnvValue -Path $envPath -Key "APP_URL" -Value $app.Url
        Set-EnvValue -Path $envPath -Key "CMIH_APP_KIND" -Value $app.Kind
        Set-EnvValue -Path $envPath -Key "CMIH_WEBSITE_URL" -Value "https://www.cmih.africa"
        Set-EnvValue -Path $envPath -Key "CMIH_STAFF_PORTAL_URL" -Value "https://portal.cmih.africa"
        Set-EnvValue -Path $envPath -Key "CMIH_BRANDS_PORTAL_URL" -Value "https://brands.cmih.africa"
    }
}

Get-ChildItem -Path $target.Path | Select-Object Name, FullName

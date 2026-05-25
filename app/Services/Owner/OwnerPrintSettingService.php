<?php

namespace App\Services\Owner;

use App\Models\TenantPrintSetting;
use App\Services\Platform\PlatformBrandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerPrintSettingService
{
    private const DEFAULT_PRINTER_NAME = 'Printer Utama';

    private const DEFAULT_PRINT_TYPE = 'thermal';

    private const DEFAULT_PAPER_SIZE = '80mm';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_PRINT_TYPES = [
        'thermal',
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_PAPER_SIZES = [
        '58mm',
        '80mm',
    ];

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(string $tenantId): array
    {
        return [
            'printSetting' => $this->resolveTenantPrintSetting($tenantId),
            'printPaperSizeOptions' => $this->resolvePaperSizeOptions(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updatePrintSetting(string $tenantId, array $validated): void
    {
        if (! Schema::hasTable('tenant_print_settings')) {
            throw ValidationException::withMessages([
                'paper_size' => 'Tabel pengaturan cetak tenant belum siap.',
            ]);
        }

        $printerName = $this->sanitizePrinterName((string) ($validated['printer_name'] ?? self::DEFAULT_PRINTER_NAME));
        $printType = $this->normalizePrintType((string) ($validated['print_type'] ?? self::DEFAULT_PRINT_TYPE));
        $paperSize = $this->normalizePaperSize((string) ($validated['paper_size'] ?? self::DEFAULT_PAPER_SIZE));

        TenantPrintSetting::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
            ],
            [
                'tenant_id' => $tenantId,
                'printer_name' => $printerName,
                'print_type' => $printType,
                'paper_size' => $paperSize,
            ],
        );
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function buildWindowsKioskInstallerDownload(Request $request, string $tenantId): array
    {
        $tenantPrintSetting = $this->resolveTenantPrintSetting($tenantId);
        $preferredPrinterName = $this->resolvePreferredPrinterNameForInstaller($tenantPrintSetting);
        $branding = app(PlatformBrandingService::class)->sharedProps();
        $appName = trim((string) ($branding['appName'] ?? 'AutoServ'));
        $appLogoUrl = $this->resolveAbsoluteUrl(
            (string) ($branding['appLogoUrl'] ?? ''),
            $request,
        );
        $logoBackgroundEnabled = (bool) ($branding['logoBackgroundEnabled'] ?? true);
        $logoBackgroundColor = $this->sanitizeHexColor(
            (string) ($branding['logoBackgroundColor'] ?? '#10B981'),
            '#10B981',
        );

        $powerShellScript = $this->buildWindowsKioskInstallerScript(
            appUrl: $this->resolveOwnerReportAppUrl($request, $tenantId),
            preferredPrinterName: $preferredPrinterName,
            appName: $appName !== '' ? $appName : 'AutoServ',
            appLogoUrl: $appLogoUrl,
            logoBackgroundEnabled: $logoBackgroundEnabled,
            logoBackgroundColor: $logoBackgroundColor,
        );
        $scriptContent = $this->buildWindowsBatchInstallerScript($powerShellScript);

        return [
            'filename' => sprintf(
                'autoserv-kiosk-installer-%s.cmd',
                $this->sanitizeFileToken($tenantId),
            ),
            'content' => $scriptContent,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveTenantPrintSetting(string $tenantId): array
    {
        $fallback = [
            'printer_name' => self::DEFAULT_PRINTER_NAME,
            'print_type' => self::DEFAULT_PRINT_TYPE,
            'paper_size' => self::DEFAULT_PAPER_SIZE,
        ];

        if (! Schema::hasTable('tenant_print_settings')) {
            return $fallback;
        }

        $setting = TenantPrintSetting::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $setting) {
            return $fallback;
        }

        return [
            'printer_name' => $this->sanitizePrinterName((string) ($setting->printer_name ?? self::DEFAULT_PRINTER_NAME)),
            'print_type' => $this->normalizePrintType((string) ($setting->print_type ?? self::DEFAULT_PRINT_TYPE)),
            'paper_size' => $this->normalizePaperSize((string) ($setting->paper_size ?? self::DEFAULT_PAPER_SIZE)),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function resolvePaperSizeOptions(): array
    {
        return [
            [
                'value' => '58mm',
                'label' => '58 mm',
                'description' => 'Cocok untuk printer thermal kecil.',
            ],
            [
                'value' => '80mm',
                'label' => '80 mm',
                'description' => 'Cocok untuk printer thermal kasir standar.',
            ],
        ];
    }

    private function sanitizePrinterName(string $value): string
    {
        $normalized = trim(strip_tags($value));

        return $normalized !== '' ? mb_substr($normalized, 0, 120) : self::DEFAULT_PRINTER_NAME;
    }

    private function normalizePrintType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, self::ALLOWED_PRINT_TYPES, true)
            ? $normalized
            : self::DEFAULT_PRINT_TYPE;
    }

    private function normalizePaperSize(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, self::ALLOWED_PAPER_SIZES, true)
            ? $normalized
            : self::DEFAULT_PAPER_SIZE;
    }

    private function resolveOwnerReportAppUrl(Request $request, string $tenantId): string
    {
        $relativeReportPath = (string) route('owner.reports.sales.index', ['tenant' => $tenantId], false);
        $baseUrl = rtrim((string) $request->getSchemeAndHttpHost(), '/');

        return $baseUrl.$relativeReportPath;
    }

    /**
     * @param  array<string, string>  $printSetting
     */
    private function resolvePreferredPrinterNameForInstaller(array $printSetting): string
    {
        $printerName = trim((string) ($printSetting['printer_name'] ?? ''));

        if ($printerName === '' || strcasecmp($printerName, self::DEFAULT_PRINTER_NAME) === 0) {
            return '';
        }

        return $printerName;
    }

    private function buildWindowsKioskInstallerScript(
        string $appUrl,
        string $preferredPrinterName = '',
        string $appName = 'AutoServ',
        string $appLogoUrl = '',
        bool $logoBackgroundEnabled = true,
        string $logoBackgroundColor = '#10B981',
    ): string
    {
        $escapedAppUrl = $this->escapePowerShellSingleQuotedString($appUrl);
        $escapedPreferredPrinterName = $this->escapePowerShellSingleQuotedString($preferredPrinterName);
        $escapedAppName = $this->escapePowerShellSingleQuotedString($appName);
        $escapedAppLogoUrl = $this->escapePowerShellSingleQuotedString($appLogoUrl);
        $escapedLogoBackgroundColor = $this->escapePowerShellSingleQuotedString($logoBackgroundColor);
        $logoBackgroundEnabledLiteral = $logoBackgroundEnabled ? '1' : '0';

        return str_replace(
            [
                '__APP_URL__',
                '__PREFERRED_PRINTER__',
                '__APP_NAME__',
                '__APP_LOGO_URL__',
                '__LOGO_BG_ENABLED__',
                '__LOGO_BG_COLOR__',
            ],
            [
                $escapedAppUrl,
                $escapedPreferredPrinterName,
                $escapedAppName,
                $escapedAppLogoUrl,
                $logoBackgroundEnabledLiteral,
                $escapedLogoBackgroundColor,
            ],
            <<<'POWERSHELL'
#requires -version 5.1
param(
    [bool]$LaunchAfterInstall = $true
)

$ErrorActionPreference = 'Stop'
$AppUrl = '__APP_URL__'
$PreferredPrinter = '__PREFERRED_PRINTER__'
$AppName = '__APP_NAME__'
$AppLogoUrl = '__APP_LOGO_URL__'
$LogoBackgroundEnabled = '__LOGO_BG_ENABLED__' -eq '1'
$LogoBackgroundColor = '__LOGO_BG_COLOR__'
$ShortcutName = 'AUTOSERV Workshop'
$StartupShortcutName = 'AUTOSERV Workshop Auto Start'
$ProfileDir = Join-Path $env:LOCALAPPDATA 'AUTOSERV-KioskProfile'
$ShortcutIconPath = Join-Path $ProfileDir 'autoserv-shortcut.ico'

function Resolve-BrowserPath {
    param(
        [string[]]$Candidates
    )

    foreach ($candidate in $Candidates) {
        if (Test-Path -LiteralPath $candidate) {
            return $candidate
        }
    }

    return $null
}

function New-OrUpdateShortcut {
    param(
        [Parameter(Mandatory = $true)]
        [object]$Shell,
        [Parameter(Mandatory = $true)]
        [string]$ShortcutPath,
        [Parameter(Mandatory = $true)]
        [string]$TargetPath,
        [Parameter(Mandatory = $true)]
        [string]$Arguments,
        [string]$IconPath = ''
    )

    $shortcut = $Shell.CreateShortcut($ShortcutPath)
    $shortcut.TargetPath = $TargetPath
    $shortcut.Arguments = $Arguments
    $shortcut.WorkingDirectory = Split-Path -Path $TargetPath -Parent
    $shortcut.WindowStyle = 1
    if (-not [string]::IsNullOrWhiteSpace($IconPath) -and (Test-Path -LiteralPath $IconPath)) {
        $shortcut.IconLocation = "$IconPath,0"
    } else {
        $shortcut.IconLocation = "$TargetPath,0"
    }
    $shortcut.Description = 'Launcher AUTOSERV dengan kiosk printing aktif.'
    $shortcut.Save()
}

function Ensure-ColorHex {
    param(
        [string]$Hex,
        [string]$Fallback = '#10B981'
    )

    if ([string]::IsNullOrWhiteSpace($Hex)) {
        return $Fallback
    }

    $normalized = $Hex.Trim().ToUpper()
    if ($normalized -match '^#[0-9A-F]{6}$') {
        return $normalized
    }

    return $Fallback
}

function Get-Initials {
    param(
        [string]$Text
    )

    $normalized = ($Text -replace '\s+', ' ').Trim()
    if ([string]::IsNullOrWhiteSpace($normalized)) {
        return 'A'
    }

    $parts = $normalized.Split(' ')
    if ($parts.Length -ge 2) {
        return (($parts[0].Substring(0, 1) + $parts[1].Substring(0, 1)).ToUpper())
    }

    if ($parts[0].Length -ge 2) {
        return $parts[0].Substring(0, 2).ToUpper()
    }

    return $parts[0].Substring(0, 1).ToUpper()
}

function Try-BuildShortcutIcon {
    param(
        [string]$LogoUrl,
        [string]$IconPath,
        [string]$AppLabel,
        [bool]$UseBackground,
        [string]$BackgroundHex
    )

    try {
        Add-Type -AssemblyName System.Drawing
        Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class NativeIconApi {
    [DllImport("user32.dll", CharSet = CharSet.Auto)]
    public static extern bool DestroyIcon(IntPtr handle);
}
'@ -ErrorAction SilentlyContinue | Out-Null

        $iconDir = Split-Path -Parent $IconPath
        if (-not (Test-Path -LiteralPath $iconDir)) {
            New-Item -ItemType Directory -Path $iconDir -Force | Out-Null
        }

        $size = 256
        $bitmap = New-Object System.Drawing.Bitmap($size, $size)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
        $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

        $backgroundColor = [System.Drawing.ColorTranslator]::FromHtml((Ensure-ColorHex -Hex $BackgroundHex))
        if ($UseBackground) {
            $graphics.Clear($backgroundColor)
        } else {
            $graphics.Clear([System.Drawing.Color]::Transparent)
        }

        $hasRenderedLogo = $false
        if (-not [string]::IsNullOrWhiteSpace($LogoUrl)) {
            try {
                $tempFile = Join-Path $env:TEMP ("autoserv-logo-" + [Guid]::NewGuid().ToString('N') + ".img")
                Invoke-WebRequest -Uri $LogoUrl -UseBasicParsing -OutFile $tempFile -ErrorAction Stop
                $logo = [System.Drawing.Image]::FromFile($tempFile)

                $margin = [int]($size * 0.12)
                $drawSize = $size - ($margin * 2)
                $ratio = [Math]::Min(($drawSize / $logo.Width), ($drawSize / $logo.Height))
                $targetWidth = [int]([Math]::Round($logo.Width * $ratio))
                $targetHeight = [int]([Math]::Round($logo.Height * $ratio))
                $targetX = [int](($size - $targetWidth) / 2)
                $targetY = [int](($size - $targetHeight) / 2)

                if (-not $UseBackground) {
                    $fallbackBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml('#0F172A'))
                    $graphics.FillRectangle($fallbackBrush, 0, 0, $size, $size)
                    $fallbackBrush.Dispose()
                }

                $graphics.DrawImage($logo, $targetX, $targetY, $targetWidth, $targetHeight)
                $logo.Dispose()
                Remove-Item -LiteralPath $tempFile -Force -ErrorAction SilentlyContinue
                $hasRenderedLogo = $true
            } catch {
                # fallback to text icon
            }
        }

        if (-not $hasRenderedLogo) {
            $foregroundHex = if ($UseBackground) { '#FFFFFF' } else { '#0F172A' }
            if (-not $UseBackground) {
                $brushBg = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml('#E2E8F0'))
                $graphics.FillRectangle($brushBg, 0, 0, $size, $size)
                $brushBg.Dispose()
            }

            $initials = Get-Initials -Text $AppLabel
            $font = New-Object System.Drawing.Font('Segoe UI', 92, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
            $brushText = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml($foregroundHex))
            $textSize = $graphics.MeasureString($initials, $font)
            $textX = ($size - $textSize.Width) / 2
            $textY = ($size - $textSize.Height) / 2 - 4
            $graphics.DrawString($initials, $font, $brushText, $textX, $textY)
            $brushText.Dispose()
            $font.Dispose()
        }

        $iconHandle = $bitmap.GetHicon()
        $icon = [System.Drawing.Icon]::FromHandle($iconHandle)
        $fileStream = New-Object System.IO.FileStream($IconPath, [System.IO.FileMode]::Create)
        $icon.Save($fileStream)
        $fileStream.Close()
        $icon.Dispose()
        [NativeIconApi]::DestroyIcon($iconHandle) | Out-Null

        $graphics.Dispose()
        $bitmap.Dispose()

        return $true
    } catch {
        return $false
    }
}

function Set-RegistryDwordPolicy {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Name,
        [Parameter(Mandatory = $true)]
        [int]$Value
    )

    try {
        if (-not (Test-Path -LiteralPath $Path)) {
            New-Item -Path $Path -Force | Out-Null
        }

        New-ItemProperty -Path $Path -Name $Name -PropertyType DWord -Value $Value -Force | Out-Null

        return $true
    } catch {
        return $false
    }
}

function Enable-SilentPrintingPolicies {
    $policyPaths = @(
        'HKCU:\Software\Policies\Google\Chrome',
        'HKCU:\Software\Policies\Microsoft\Edge'
    )

    $result = @{
        succeeded = 0
        failed = 0
    }

    foreach ($policyPath in $policyPaths) {
        if (Set-RegistryDwordPolicy -Path $policyPath -Name 'PrintingEnabled' -Value 1) { $result.succeeded++ } else { $result.failed++ }
        if (Set-RegistryDwordPolicy -Path $policyPath -Name 'SilentPrintingEnabled' -Value 1) { $result.succeeded++ } else { $result.failed++ }
        if (Set-RegistryDwordPolicy -Path $policyPath -Name 'PrintPreviewUseSystemDefaultPrinter' -Value 1) { $result.succeeded++ } else { $result.failed++ }
        if (Set-RegistryDwordPolicy -Path $policyPath -Name 'UseSystemPrintDialog' -Value 0) { $result.succeeded++ } else { $result.failed++ }
    }

    return $result
}

function Try-SetDefaultPrinter {
    param(
        [string]$PrinterName
    )

    if ([string]::IsNullOrWhiteSpace($PrinterName)) {
        return $false
    }

    try {
        $printer = Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue
        if (-not $printer) {
            return $false
        }

        $network = New-Object -ComObject WScript.Network
        $network.SetDefaultPrinter($PrinterName)

        return $true
    } catch {
        return $false
    }
}

function Get-DefaultPrinterName {
    try {
        $defaultPrinter = Get-CimInstance Win32_Printer -ErrorAction SilentlyContinue `
            | Where-Object { $_.Default -eq $true } `
            | Select-Object -First 1

        if ($defaultPrinter) {
            return [string]$defaultPrinter.Name
        }
    } catch {
        # noop
    }

    return ''
}

$browserCandidates = @(
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "$env:LocalAppData\Google\Chrome\Application\chrome.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:LocalAppData\Microsoft\Edge\Application\msedge.exe"
)

$browserPath = Resolve-BrowserPath -Candidates $browserCandidates

if (-not $browserPath) {
    throw 'Chrome/Edge tidak ditemukan. Silakan install Chrome atau Edge dulu, lalu jalankan script ini lagi.'
}

$policyResult = Enable-SilentPrintingPolicies

if (-not (Test-Path -LiteralPath $ProfileDir)) {
    New-Item -ItemType Directory -Path $ProfileDir -Force | Out-Null
}

$isCustomIconReady = Try-BuildShortcutIcon `
    -LogoUrl $AppLogoUrl `
    -IconPath $ShortcutIconPath `
    -AppLabel $AppName `
    -UseBackground $LogoBackgroundEnabled `
    -BackgroundHex $LogoBackgroundColor

$shortcutArgs = '--kiosk-printing --disable-print-preview --no-first-run --no-default-browser-check'
$shortcutArgs += " --user-data-dir=""$ProfileDir"""
$shortcutArgs += " --app=""$AppUrl"""
$desktopPath = [Environment]::GetFolderPath('Desktop')
$startMenuPath = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs'
$startupPath = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Startup'

if (-not (Test-Path -LiteralPath $startMenuPath)) {
    New-Item -ItemType Directory -Path $startMenuPath -Force | Out-Null
}

if (-not (Test-Path -LiteralPath $startupPath)) {
    New-Item -ItemType Directory -Path $startupPath -Force | Out-Null
}

$desktopShortcut = Join-Path $desktopPath "$ShortcutName.lnk"
$startShortcut = Join-Path $startMenuPath "$ShortcutName.lnk"
$startupShortcut = Join-Path $startupPath "$StartupShortcutName.lnk"

$wshShell = New-Object -ComObject WScript.Shell

$isPreferredPrinterApplied = Try-SetDefaultPrinter -PrinterName $PreferredPrinter
$defaultPrinterName = Get-DefaultPrinterName

New-OrUpdateShortcut -Shell $wshShell -ShortcutPath $desktopShortcut -TargetPath $browserPath -Arguments $shortcutArgs -IconPath $ShortcutIconPath
New-OrUpdateShortcut -Shell $wshShell -ShortcutPath $startShortcut -TargetPath $browserPath -Arguments $shortcutArgs -IconPath $ShortcutIconPath
New-OrUpdateShortcut -Shell $wshShell -ShortcutPath $startupShortcut -TargetPath $browserPath -Arguments $shortcutArgs -IconPath $ShortcutIconPath

Write-Host ''
Write-Host '=== Setup Auto Print Selesai ===' -ForegroundColor Green
Write-Host "App URL            : $AppUrl"
Write-Host "Browser            : $browserPath"
Write-Host "Desktop shortcut   : $desktopShortcut"
Write-Host "Start Menu shortcut: $startShortcut"
Write-Host "Startup shortcut   : $startupShortcut"
Write-Host "Profile kiosk      : $ProfileDir"
Write-Host "Icon shortcut      : $ShortcutIconPath"
Write-Host "Default printer    : $defaultPrinterName"
Write-Host "Policy sukses      : $($policyResult.succeeded)"
Write-Host "Policy gagal       : $($policyResult.failed)"
Write-Host ''
if ($isCustomIconReady) {
    Write-Host "Icon aplikasi berhasil dipakai dari branding: $AppName" -ForegroundColor Green
} else {
    Write-Host 'Icon custom gagal dibuat, shortcut memakai icon browser default.' -ForegroundColor Yellow
}

if ($policyResult.failed -gt 0) {
    Write-Host 'Sebagian policy browser tidak bisa ditulis (akses dibatasi OS). Installer tetap lanjut memakai mode kiosk shortcut.' -ForegroundColor Yellow
}

if ($isPreferredPrinterApplied) {
    Write-Host "Printer default diset ke: $PreferredPrinter" -ForegroundColor Green
} elseif (-not [string]::IsNullOrWhiteSpace($PreferredPrinter)) {
    Write-Host "Printer '$PreferredPrinter' tidak ditemukan. Pilih printer thermal sebagai default printer Windows." -ForegroundColor Yellow
} else {
    Write-Host 'Nama printer di pengaturan masih default. Pilih printer thermal sebagai default printer Windows.' -ForegroundColor Yellow
}

if ($defaultPrinterName -match 'pdf|xps|onenote') {
    Write-Host 'Default printer saat ini terlihat printer virtual (PDF/XPS). Ubah ke printer thermal agar silent print berjalan.' -ForegroundColor Yellow
}

Write-Host 'Jika dialog print masih muncul, tutup semua browser lalu buka ulang lewat shortcut AUTOSERV Workshop.' -ForegroundColor Yellow

if ($LaunchAfterInstall) {
    Start-Process -FilePath $browserPath -ArgumentList $shortcutArgs
}
POWERSHELL
        );
    }

    private function buildWindowsBatchInstallerScript(string $powerShellScript): string
    {
        $batchScript = <<<'BATCH'
@echo off
setlocal
set "TMP_PS1=%TEMP%\autoserv-kiosk-installer-%RANDOM%%RANDOM%.ps1"

for /f "tokens=1 delims=:" %%i in ('findstr /n /c:"__PS_SCRIPT_BELOW__" "%~f0"') do set /a PS_LINE=%%i+1

if not defined PS_LINE (
    echo Gagal menyiapkan installer.
    pause
    exit /b 1
)

more +%PS_LINE% "%~f0" > "%TMP_PS1%"
powershell -NoProfile -ExecutionPolicy Bypass -File "%TMP_PS1%"
set "EXIT_CODE=%ERRORLEVEL%"
del /f /q "%TMP_PS1%" >nul 2>&1

if not "%EXIT_CODE%"=="0" (
    echo.
    echo Setup gagal. Coba klik kanan file ini lalu Run as administrator.
    pause
)

exit /b %EXIT_CODE%
__PS_SCRIPT_BELOW__
BATCH;

        return $batchScript.PHP_EOL.$powerShellScript;
    }

    private function sanitizeFileToken(string $value): string
    {
        $token = strtolower(trim($value));
        $token = preg_replace('/[^a-z0-9\-]+/', '-', $token) ?? '';
        $token = trim($token, '-');

        return $token !== '' ? $token : 'tenant';
    }

    private function escapePowerShellSingleQuotedString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function sanitizeHexColor(string $value, string $fallback): string
    {
        $normalized = strtoupper(trim($value));
        if (preg_match('/^#[A-F0-9]{6}$/', $normalized) === 1) {
            return $normalized;
        }

        return $fallback;
    }

    private function resolveAbsoluteUrl(string $url, Request $request): string
    {
        $normalized = trim($url);
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '/')) {
            return rtrim((string) $request->getSchemeAndHttpHost(), '/').$normalized;
        }

        return $normalized;
    }
}

param([switch]$Offline)

$ErrorActionPreference = 'Stop'
$projectDir = Split-Path $PSScriptRoot -Parent

# Prefer the configured JDK, with the Gradle-managed JDK 17 as a fallback.
if (-not $env:JAVA_HOME) {
    $gradleJdk = Get-ChildItem -Path "$env:USERPROFILE/.gradle/jdks" -Directory -Filter '*17*' -ErrorAction SilentlyContinue |
        Where-Object { Test-Path (Join-Path $_.FullName 'bin/java.exe') } |
        Select-Object -First 1
    if ($gradleJdk) { $env:JAVA_HOME = $gradleJdk.FullName }
}
if (-not $env:ANDROID_HOME -and -not $env:ANDROID_SDK_ROOT) {
    $sdkDir = Join-Path $env:LOCALAPPDATA 'Android/Sdk'
    if (Test-Path $sdkDir) { $env:ANDROID_HOME = $sdkDir }
}

$previousNodeEnv = $env:NODE_ENV
$env:NODE_ENV = 'production'
Push-Location (Join-Path $projectDir 'android')
try {
    $gradleArgs = @(':app:assembleRelease', '--console=plain')
    if ($Offline) { $gradleArgs += '--offline' }
    & ./gradlew.bat @gradleArgs
    if ($LASTEXITCODE -ne 0) { throw 'Android release build failed.' }
    Write-Host "APK: $projectDir/android/app/build/outputs/apk/release/app-release.apk"
} finally {
    Pop-Location
    $env:NODE_ENV = $previousNodeEnv
}

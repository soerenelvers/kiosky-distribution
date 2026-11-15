param([Parameter(Mandatory=$true)][string]$Profile,[Parameter(Mandatory=$true)][string]$Target)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
Get-Command tizen -ErrorAction Stop | Out-Null
function Invoke-Tizen([string[]]$Arguments) {
 & tizen @Arguments
 if ($LASTEXITCODE -ne 0) { throw 'Tizen-Schritt fehlgeschlagen. Installation abgebrochen; README.md prüfen.' }
}
$tizenWork = Join-Path ([IO.Path]::GetTempPath()) ('kiosky-tizen-' + [guid]::NewGuid().ToString())
try {
 $build = Join-Path $tizenWork 'build'
 $packages = Join-Path $tizenWork 'signed'
 New-Item -ItemType Directory -Path $build,$packages -Force | Out-Null
 Invoke-Tizen -Arguments @('build-web','--output',$build,'--','app')
 Invoke-Tizen -Arguments @('package','-t','wgt','-s',$Profile,'-o',$packages,'--',$build)
 $files = @(Get-ChildItem -LiteralPath $packages -Filter '*.wgt' -File)
 if ($files.Count -ne 1) { throw 'Kein eindeutiges signiertes WGT erzeugt.' }
 New-Item -ItemType Directory -Path 'signed' -Force | Out-Null
 Copy-Item -LiteralPath $files[0].FullName -Destination 'signed/Kiosky-Player.wgt' -Force
 Invoke-Tizen -Arguments @('install','-n','Kiosky-Player.wgt','-t',$Target,'--','signed')
 Invoke-Tizen -Arguments @('run','-p','KioskyTv01.Player','-t',$Target)
} finally {
 if (Test-Path -LiteralPath $tizenWork) { Remove-Item -LiteralPath $tizenWork -Recurse -Force }
}

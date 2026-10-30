$ErrorActionPreference = 'Stop'

$repo  = 'iyanrsaleh/sekat'
$asset = 'sekat-windows-x64.zip'
$url   = "https://github.com/$repo/releases/latest/download/$asset"

$tmp = Join-Path $env:TEMP ("sekat-install-" + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tmp -Force | Out-Null

try {
    $zip = Join-Path $tmp 'sekat.zip'
    Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
    Expand-Archive -Path $zip -DestinationPath $tmp -Force

    $exe = Join-Path $tmp 'sekat.exe'
    if (-not (Test-Path -LiteralPath $exe)) {
        $exe = Get-ChildItem -Path $tmp -Recurse -Filter 'sekat.exe' |
            Select-Object -First 1 -ExpandProperty FullName
    }
    if (-not $exe -or -not (Test-Path -LiteralPath $exe)) {
        throw "Arsip rilis tidak berisi sekat.exe."
    }

    & $exe install --global
}
finally {
    Remove-Item -Recurse -Force $tmp -ErrorAction SilentlyContinue
}
# ============================================================
# Genera despliegue\reservas-ilcapo.zip listo para subir por FTP.
# Requisitos: Docker encendido con el contenedor reservas-app.
# Uso: clic derecho -> Ejecutar con PowerShell (o desde terminal)
# ============================================================

$ErrorActionPreference = 'Stop'
$carpetaDespliegue = $PSScriptRoot
$zipFinal = Join-Path $carpetaDespliegue 'reservas-ilcapo.zip'

Write-Host '1/4 Preparando una copia limpia dentro del contenedor...'
docker exec reservas-app sh -c @'
rm -rf /tmp/paquete /tmp/reservas-ilcapo.zip &&
cp -a /var/www/html /tmp/paquete &&
cd /tmp/paquete &&
rm -rf tests node_modules .git .env database/database.sqlite storage/logs/*.log bootstrap/cache/*.php storage/framework/views/*.php storage/framework/sessions/* storage/framework/cache/data/*
'@
if ($LASTEXITCODE -ne 0) { throw 'Fallo preparando la copia en el contenedor' }

Write-Host '2/4 Instalando dependencias de produccion (sin paquetes de desarrollo)...'
docker exec reservas-app sh -c 'cd /tmp/paquete && composer install --no-dev --optimize-autoloader --no-interaction --quiet'
if ($LASTEXITCODE -ne 0) { throw 'Fallo composer install' }

Write-Host '3/4 Añadiendo la configuracion de produccion...'
docker cp (Join-Path $carpetaDespliegue 'env.produccion') reservas-app:/tmp/paquete/.env
if ($LASTEXITCODE -ne 0) { throw 'Fallo copiando env.produccion' }

Write-Host '4/4 Creando el ZIP (dentro de Linux, para que las rutas sean correctas)...'
docker exec reservas-app sh -c 'cd /tmp/paquete && zip -rq9 /tmp/reservas-ilcapo.zip .'
if ($LASTEXITCODE -ne 0) { throw 'Fallo creando el zip' }
if (Test-Path $zipFinal) { Remove-Item $zipFinal -Force }
docker cp reservas-app:/tmp/reservas-ilcapo.zip $zipFinal
docker exec reservas-app sh -c 'rm -rf /tmp/paquete /tmp/reservas-ilcapo.zip'

$tamano = [math]::Round((Get-Item $zipFinal).Length / 1MB, 1)
Write-Host ""
Write-Host "Listo: $zipFinal ($tamano MB)" -ForegroundColor Green
Write-Host 'Recuerda: el ZIP contiene un archivo .env (oculto) con los datos RELLENAR pendientes.'

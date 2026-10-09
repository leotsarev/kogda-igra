<#
.SYNOPSIS
Снимает схему и справочники с dev-базы в db/init/.

.DESCRIPTION
Перезаписывает:

  db/init/01-schema.sql          — структура (pg_dump --schema-only);
  db/init/02-reference-data.sql  — только справочники (pg_dump --data-only --table=...).

Пользовательский контент (игры, пользователи с адресами почты) не снимается и в репозиторий
не попадает.

.NOTES
Пароль читается из переменной окружения KOGDA_DEV_PASSWORD, чтобы не попадать
в историю команд:

    $env:KOGDA_DEV_PASSWORD = '...'
    ./utils/migrate-db/fetch-dev-schema.ps1

pg_dump запускается в контейнере, локальная установка Postgres не нужна.
Если pg_dump ругается на server version mismatch — передать -PostgresImage с нужной версией.
#>

[CmdletBinding()]
param(
    # Версия pg_dump не может быть ниже версии сервера. Dev/prod живут в Yandex Cloud
    # на PostgreSQL 15.18; если сервер поднимут — поднять и это значение.
    [string]$PostgresImage = 'postgres:15.18'
)

$ErrorActionPreference = 'Stop'

$dbHost = 'rc1b-1omkout6a9ifyold.mdb.yandexcloud.net'
$port = 6432
$database = 'kogda-dev'
$user = 'kogda-dev'

# Справочники: наполняются вручную и нужны сайту, чтобы вообще отрисовать страницу.
$referenceTables = @(
    'ki_regions',
    'ki_sub_regions',
    'ki_game_types',
    'ki_status',
    'ki_update_types',
    'privs'
)

$password = $env:KOGDA_DEV_PASSWORD
if ([string]::IsNullOrWhiteSpace($password))
{
    throw 'Не задана переменная окружения KOGDA_DEV_PASSWORD. См. комментарий в начале скрипта.'
}

$initDir = Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'db/init'

function Invoke-PgDump([string[]]$DumpArguments, [string]$OutputName)
{
    $connection = "postgresql://${user}@${dbHost}:${port}/${database}?sslmode=require"

    # pg_dump пишет прямо в примонтированный каталог, а не в stdout. Через конвейер
    # PowerShell вывод пропускать нельзя: он декодирует его кодировкой консоли
    # ([Console]::OutputEncoding), и на не-UTF-8 консоли кириллица превращается
    # в псевдографику (UTF-8, прочитанный как CP866).
    docker run --rm `
        -e "PGPASSWORD=$password" `
        -v "${initDir}:/out" `
        $PostgresImage `
        pg_dump $connection --no-owner --no-privileges --encoding=UTF8 `
        --file "/out/$OutputName" $DumpArguments

    if ($LASTEXITCODE -ne 0)
    {
        throw "pg_dump завершился с кодом $LASTEXITCODE"
    }

    # Страховка: псевдографика в русском тексте — верный признак, что кодировка поехала.
    $content = [System.IO.File]::ReadAllText((Join-Path $initDir $OutputName), [System.Text.Encoding]::UTF8)
    if ($content.Contains([char]0x2568) -or $content.Contains([char]0x2564))
    {
        throw "В $OutputName попала испорченная кодировка (псевдографика вместо кириллицы)."
    }

    Write-Host "Записан db/init/$OutputName"
}

Write-Host 'Снимаю схему...'
Invoke-PgDump @('--schema-only') '01-schema.sql'

Write-Host 'Снимаю справочные данные...'
$tableArguments = $referenceTables | ForEach-Object { '--table'; $_ }
Invoke-PgDump (@('--data-only') + $tableArguments) '02-reference-data.sql'

Write-Host 'Готово. Проверить: docker compose down -v; docker compose up --build'

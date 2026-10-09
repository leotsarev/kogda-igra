# Схема БД

`db/init/` монтируется в `/docker-entrypoint-initdb.d` контейнера Postgres (см. `docker-compose.yml`).
Скрипты выполняются через `psql` по алфавиту, **только при первом старте на пустом томе**:

| Файл | Что внутри |
|---|---|
| `01-schema.sql` | Структура: таблицы, индексы, последовательности |
| `02-reference-data.sql` | Справочники: регионы, субрегионы, типы игр, статусы, типы обновлений, привилегии |

## Откуда взялись

Это снимок живой dev-базы (`pg_dump --schema-only` и `pg_dump --data-only` по справочникам),
снятый скриптом `utils/migrate-db/fetch-dev-schema.ps1`. Dev и prod — две базы на одном сервере
PostgreSQL 15.18, структура у них одинаковая. Переснять (нужен пароль от dev):

```powershell
$env:KOGDA_DEV_PASSWORD = '<пароль kogda-dev>'
./utils/migrate-db/fetch-dev-schema.ps1
```

## Кто ещё пользуется

`db/init/` копируется в Docker-образ сайта как `/db-init`. Compat-тесты
[kogda-igra-net](https://github.com/leotsarev/kogda-igra-net) берут схему оттуда — из того же
образа, против которого гоняются. Поэтому схема в этом каталоге должна соответствовать коду в
той же ревизии: колонка, которую код уже использует, но которой нет в `01-schema.sql`, уронит
тесты.

Схема досталась от конвертации из MySQL через pgloader, отсюда имена индексов вида
`idx_16887_primary`, повсеместные `bigint`/`text` и nullable-колонки. Таблицы `news`, `news_tags`,
`tags`, `old_games` — наследие bastilia, код их не использует. Всё это оставлено как есть, чтобы
схема совпадала с продом: чистить — только миграцией, которая накатится и на прод.

Файлы — вывод `pg_dump`, а не чистый SQL (`\restrict`, `COPY ... FROM stdin`): выполнять их
нужно через `psql`.

## Пересоздать локальную базу

```bash
docker compose down -v   # удалит том с данными
docker compose up --build
```

## Стать админом локально

Войти на сайт через JoinRPG — пользователь создастся сам. Затем выдать себе права:

```bash
docker exec kogda-db psql -U kogdauser -d kogdaigra -c \
  "INSERT INTO user_privs (uid, pid) SELECT user_id, p.id FROM users, privs p WHERE email = '<ваш email>'"
```

Привилегии — в таблице `privs` (`EDIT_GAMES` = 4, `USERS_CONTROL` = 2 и т.д.).

## Миграции

Изменение схемы — это **две правки**:

1. `db-migrations/add_<description>.sql` — накатывается вручную на dev и prod;
2. то же изменение в `db/init/01-schema.sql` — чтобы новая пустая база получалась сразу актуальной.

Пишите миграции идемпотентно (`ADD COLUMN IF NOT EXISTS`).

# Агенты и роли

В проекте задействованы две роли Kilo: **planner** и **scout**. Ниже — что
каждой разрешено и как их использовать.

## planner

Используется вручную для проработки плана: собирает контекст, формирует
декомпозицию задач, фиксирует артефакты в `docs/plan/`, `docs/spec/`,
`docs/intent/`. Не меняет код и не коммитит; выход — текстовый план и
ссылки на артефакты.

Допустимо:
- читать файлы, глиб, греп по всему репозиторию;
- запускать read-only bash-команды из allowlist;
- писать только в `docs/intent/`, `docs/spec/`, `docs/plan/`,
  `docs/agents/` и аналогичные `docs/*` для артефактов;
- созваниваться с пользователем через `question` для уточнений.

Недопустимо:
- править `backend/**`, `frontend/**`, `db/**`, `tests/**`,
  `backend/config/**`, инфраструктуру;
- коммитить, пушить, открывать PR;
- трогать `.env*`, запускать `scripts/reset_db.sh`.

## scout

Быстрый обход кодовой базы по запросу. Возвращает структурированный отчёт
с путями, номерами строк и пояснением, что именно в каждом месте происходит.
Ничего на диске не меняет.

Допустимо:
- читать файлы, греп, глиб по всему репозиторию;
- запускать read-only bash-команды из allowlist;
- формировать текстовый отчёт как единственный результат задачи.

Недопустимо:
- любые изменения файлов, включая документацию;
- команды, которые пишут или удаляют данные;
- коммиты, пуши, открытие PR.

## Связь с правками

Когда агенту `code` нужно что-то поправить в коде по результатам scout —
scout только подсвечивает места, а саму правку делает `code` в рамках
своих разрешений из `kilo.jsonc` (блок `permission`) и
`docs/agent-rules.md`.

## Что вернул scout по пробегу

Запрос: «найди все места, где читается пробег (mileage)». Сводка ниже —
единственный артефакт scout по этому запросу; код не менялся.

### Backend

**`backend/src/Domain/ApplicationValidator.php`**
- `:22` — PHPDoc контракта: `array{... mileage:int, ...}`
- `:43` — `$mileage = (int) ($payload['mileage'] ?? -1);` — единственное
  место фактического чтения из входного payload
- `:44-45` — использование в проверке диапазона и в формировании
  `$errors['mileage']`
- `:78` — запись нормализованного значения в выходной массив

**`backend/src/Repository/ApplicationRepository.php`**
- `:19` — PHPDoc параметра `save()`
- `:38` — SQL `INSERT ... mileage_km ...`
- `:39` — placeholder `:mileage`
- `:45` — `':mileage' => $input['mileage']` — чтение из массива для
  записи в БД
- `:68` — `find()` возвращает колонку `v.mileage_km` (под ключом
  `mileage_km`)

### Frontend

**`frontend/index.html`**
- `:30` — `<label for="mileage">Пробег, км</label>`
- `:31` — `<input id="mileage" name="mileage" ...>` — `name="mileage"`
  определяет ключ в FormData/payload

**`frontend/app.js`**
- `:8` — `'mileage'` в `NUMERIC_FIELDS` → каст через `Number()` при
  формировании JSON-payload
- `:14` — фактическая точка чтения значения поля формы в payload

### DB

- `db/schema.sql:22` — колонка `mileage_km INT UNSIGNED NOT NULL`
- `db/seed.sql:31` — `INSERT ... mileage_km, market_value` (24
  синтетических значения 20 000–296 000)

### Tests

- `tests/Unit/ApplicationValidatorTest.php:34` — `'mileage' => 84000,`
  в фикстуре
- `tests/Unit/AssessmentServiceTest.php:38` — `'mileage' => 96000,`
  в фикстуре
- Ассертов именно по `mileage` нет ни в одном тесте

### Config

- `backend/config/rules.php:23` — `'max_mileage_km' => 500000,`
  (используется только в `ApplicationValidator`)

### Наблюдения scout

1. Заявка передаётся как ассоциативный массив, сквозного DTO нет.
   PHPDoc-контракт фиксируется в `ApplicationValidator.php:22` и
   `ApplicationRepository.php:19`.
2. В payload и PHP-коде ключ `mileage`, в БД — `mileage_km`. Маппинг
   живёт только в `ApplicationRepository::save` (`:38`–`:46`).
3. `mileage` читается ровно в двух местах: `ApplicationValidator:43`
   (валидация входа) и `ApplicationRepository:45` (INSERT).
   Контроллер, `AssessmentService`, `LtvCalculator`, `DecisionEngine`,
   `Json` поле не трогают.
4. На решение approve/review/reject пробег сейчас не влияет — в
   `LtvCalculator`/`DecisionEngine` он не передаётся.
5. Расхождение в репозитории: `find()` отдаёт `mileage_km`,
   `listApplications()` выбирает только `vin, production_year` — пробег
   в списке заявок отсутствует.

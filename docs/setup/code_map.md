# Карта кода: как считается решение approve / review / reject

Дата: 2026-09-24. Источник: `backend/src/Domain/` и `backend/config/rules.php`. Никакие файлы не изменялись.

## Участники

| Файл | Роль |
|---|---|
| `backend/src/Domain/AssessmentService.php` | Оркестратор: валидация -> LTV -> решение -> лимит |
| `backend/src/Domain/ApplicationValidator.php` | Проверка входных полей заявки, нормализация |
| `backend/src/Domain/VinValidator.php` | Проверка VIN (длина, запрещённые символы) |
| `backend/src/Domain/VehicleAge.php` | Возраст авто в годах |
| `backend/src/Domain/LtvCalculator.php` | LTV = сумма / стоимость * 100 |
| `backend/src/Domain/DecisionEngine.php` | Пороги approve / review / reject по LTV |
| `backend/config/rules.php` | Все пороги и лимиты (числа не хардкодятся в коде) |

## Порядок вызовов

Точка входа — `AssessmentService::assess(array $payload)` (AssessmentService.php:28):

1. `ApplicationValidator::validate($payload)` (ApplicationValidator.php:24)
   - `VinValidator::isValid($vin)`
   - `VehicleAge::inYears($year)` — для проверки возраста
   - проверяет vin, year, mileage, market_value, requested_amount, term_months
   - при ошибках бросает `ValidationException`; иначе возвращает нормализованный массив,
     включая `mileage` (int)
2. `LtvCalculator::calculate($input['requested_amount'], $input['market_value'])`
   (LtvCalculator.php:15) — LTV в процентах, округление до 2 знаков
3. `DecisionEngine::decide($ltv)` (DecisionEngine.php:30):
   - `LTV < approve_max (60.0)` -> `approve`
   - `LTV <= review_max (85.0)` -> `review`
   - иначе -> `reject`
4. Формирование ответа: `vehicle_age` (`VehicleAge::inYears`), `ltv`, `decision`,
   `approved_limit` (запрошенная сумма при approve, иначе 0).

Пороги 60.0 / 85.0 берутся из `rules.php` (`ltv.approve_max`, `ltv.review_max`) и передаются
в конструктор `DecisionEngine`.

## Куда встанет правило «пробег <= 400 000 км, иначе review»

Правило влияет на решение, а не на валидность заявки, поэтому логичное место —
не `ApplicationValidator` (он либо пропускает заявку, либо бросает исключение),
а ветка решения. Возможные варианты, которые код допускает сегодня:

- **`DecisionEngine::decide()`** — сейчас принимает только `float $ltv` и не знает
  пробег. Правило «иначе review» сюда встанет, только если пробег передать в метод.
- **`AssessmentService::assess()`** — после `$decision = $this->decisionEngine->decide($ltv)`
  (строка 33): если `$input['mileage'] > 400000` — заменить/понизить решение на
  `DecisionEngine::REVIEW`.

Точка вставки — `AssessmentService::assess()`, сразу после строки 33:

```php
        $ltv = $this->ltvCalculator->calculate($input['requested_amount'], $input['market_value']);
        $decision = $this->decisionEngine->decide($ltv);
```
(строки 32–33 файла backend/src/Domain/AssessmentService.php)

### Что уже есть из входных данных

- `$input['mileage']` — нормализованный int-пробег, возвращается валидатором
  (ApplicationValidator.php:78) и доступен в `assess()`.
- Константа `DecisionEngine::REVIEW` — есть.
- Порог 400 000 — в `rules.php` нет. По конвенции проекта его нужно добавить
  в `backend/config/rules.php` (например, отдельный ключ в `vehicle`), а не хардкодить.
  Существующий `vehicle.max_mileage_km = 500000` — другое правило (верхняя граница
  валидного пробега), переиспользовать его для порога «review» нельзя без изменения
  смысла.

### Чего не хватает

- Передачи пробега в `DecisionEngine::decide()` (если правило класть туда) — метод
  сейчас принимает только LTV.
- Ключа с порогом 400 000 в `rules.php`.
- Приоритезации правил (что делать, если по LTV `reject`, а по пробегу `review`)
  в коде нет — нужно определить в правиле.
- Тестов на это правило нет.

## Что уже сейчас проверяется про пробег

Только одно: `ApplicationValidator::validate()` (ApplicationValidator.php:43-46) —
пробег должен быть от 0 до `vehicle.max_mileage_km` (500 000 км из rules.php:23).
При нарушении заявка отклоняется как невалидная (`ValidationException`), а не получает
`review`. На решение approve/review/reject пробег сейчас не влияет никак — нет.

## Точка вставки (3–5 строк контекста)

`backend/src/Domain/AssessmentService.php`, строки 30–36:

```php
        $input = $this->validator->validate($payload);

        $ltv = $this->ltvCalculator->calculate($input['requested_amount'], $input['market_value']);
        $decision = $this->decisionEngine->decide($ltv);

        return [
```

Вариант в `DecisionEngine::decide()` (backend/src/Domain/DecisionEngine.php, строки 30–33):

```php
    public function decide(float $ltv): string
    {
        if ($ltv < $this->approveMax) {
            return self::APPROVE;
```

# Offer integration kit (Binom)

Готовый шаблон, чтобы на **каждый оффер** ставить одну и ту же интеграцию:

- `form_assets/` — intlTelInput + валидация + AJAX на `send.php`
- `<form>` — все tracking/UTM hidden + имя/фамилия/email/phone
- `send.php` — ACPA push, Telegram, Binom lead postback, redirect на success
- `success.php` — **FB Pixel Lead** (+ PageView) с fallback из cookie

Плюс **`SKILL.md`** — описание для обучения агента (можно положить в Cursor Skills).

## Скачать

Архив: `offer-integration-kit.zip` (рядом с этой папкой / в artifacts).

## Как применить на оффер (вручную, 5 мин)

1. Скопируй `template/form_assets`, `send.php`, `success.php`, `antifraud.php` в папку лендера.
2. В `index.html`:
   - в `<head>` — CSS из `snippets/head-assets.html` + `snippets/tracking-scripts.html`
   - форма из `snippets/form.html`
   - перед `</body>` — `snippets/footer-assets.html` (поставь `initialCountry`)
3. В `send.php` заполни блок **CONFIG** (ACPA id/name, sub5, country, buyer keys, slug, TG).
4. Проверь: лид → `success.php?pixel=…` → в Network событие Lead; в Binom conversion `lead`.

## Обучить агента

1. Добавь `SKILL.md` как skill (или вложи в промпт агента целиком).
2. Скажи агенту: «интегрируй оффер X по binom-offer-integration skill, geo=IL, buyer=Hex».
3. Агент обязан пройти checklist из SKILL (pixel Lead + Binom postback).

## Важно

- В `send.php` шаблона стоят **плейсхолдеры** (`ACPA_KEY_*`, `TELEGRAM_*`) — подставь боевые из вашего рабочего `send.php`.
- `_shared/binom_lead_postback.php` на трекере уже есть; в zip лежит копия для справки.
- Не стреляй Lead на странице формы — только на `success.php` после сабмита.

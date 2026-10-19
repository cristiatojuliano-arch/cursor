# Per-offer QA checklist

## Files
- [ ] `form_assets/` copied
- [ ] `send.php` CONFIG filled (no `ACPA_KEY_` / `TELEGRAM_` placeholders left)
- [ ] `success.php` present
- [ ] `antifraud.php` present (optional)

## HTML
- [ ] tracking-scripts in `<head>`
- [ ] form CSS linked
- [ ] form has clickid, subid, pixel, buyer_mark, utm_*
- [ ] fields: firstname, lastname, email, phone
- [ ] `BINOM_FORM_CONFIG.initialCountry` = geo
- [ ] form.js / intl / utils loaded after form

## Runtime
- [ ] Invalid phone blocked (red error, no submit)
- [ ] Valid submit → JSON redirect → success
- [ ] success URL contains real `pixel=` digits
- [ ] FB pixel Lead in Network (or Pixel Helper)
- [ ] Binom shows conversion `lead` for clickid
- [ ] Telegram lead message received
- [ ] ACPA/CRM accepted (debug in TG)

## Campaign
- [ ] append_to_url passes pixel, buyer, clickid, utms
- [ ] If Protect VPN on → white configured (else blank 307)

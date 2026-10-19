---
name: binom-offer-integration
description: >-
  Integrate a Binom lander (quiz/LP/panet) with form_assets validation, send.php
  (ACPA+TG+Binom postback), success.php FB Pixel Lead, and full UTM/clickid/pixel
  params. Use when adding or adapting an offer lander for Hex/Bristol/etc.
---

# Binom offer integration

Copy `template/` into the lander folder (or merge into an existing creative).
Every offer must end with working: **form → send.php → success.php (fbq Lead)** + **Binom cnv lead**.

## Hard requirements (do not skip)

1. **`form_assets/`** — entire folder (css, js, intl, img).
2. **`send.php`** — from template; fill CONFIG only.
3. **`success.php`** — fires `fbq('track','Lead')` + PageView; pixel from query or `_tk_pixel`.
4. **`<form>`** — hidden tracking fields + `firstname` `lastname` `email` `phone` (see `snippets/form.html`).
5. **Tracking scripts** — `snippets/tracking-scripts.html` in `<head>` (persist pixel/clickid/buyer).
6. **Footer assets** — `BINOM_FORM_CONFIG` + intlTelInput + `form.js` (see `snippets/footer-assets.html`).
7. **Binom postback** — `require` `_shared/binom_lead_postback.php` (already on tracker). Sends `cnv_id={clickid}&cnv_status=lead`.
8. **Campaign append_to_url** must pass at least: `pixel`, `buyer`, `clickid`/`subid`, utm_*, `fbclid`.

## Per-offer CONFIG (`send.php`)

| Variable | Meaning |
|---|---|
| `$acpa_offer_id` / `$acpa_offer_name` | CRM offer |
| `$acpa_sub5` / `$acpa_country` | sub5 tag + ISO country |
| `$default_buyer` | Hex / Bristol / … fallback |
| `$buyers_config` | mark → ACPA key + flow |
| `$lander_slug` | folder name under `/landers/` |
| `$project_name` | success.php title |
| `$tg_*` | Telegram notify |

`form.js`: set `window.BINOM_FORM_CONFIG.initialCountry` to geo (`il`, `sa`, `de`…).

## Form contract

**Hidden (macros filled by Binom + tracking scripts):**  
`subid`, `clickid`, `fbclid`, `extr`, `pixel`, `bpixel`, `place`, `gclid`, `buyer_mark`, all `utm_*`.

**Visible:** `firstname`, `lastname`, `email`, `phone`  
`form.js` POSTs AJAX to `send.php` with E.164 `phone` and returns JSON `{redirectUrl}`.

**Do not** change field names. Aliases accepted in `send.php`: `first_name`/`fname`, `full_number`, etc.

## FB Pixel Lead (correct path)

1. Campaign URL / append: `pixel={t9}` (or token that holds FB pixel id).
2. `tracking-scripts` stores `_tk_pixel` cookie + localStorage.
3. `send.php` reads POST `pixel` or cookie → redirects  
   `/landers/{slug}/success.php?pixel=PIXEL&project=NAME`
4. `success.php` `fbq('init', PIXEL); fbq('track','Lead');`  
   + fallback script if query empty.

Never fire Lead on the form page before submit. Lead only on **success.php** after CRM accept.

## Binom conversion

After ACPA push, include postback so tracker shows lead (not only FB):

- Uses POST `clickid` / `subid` (priority over bare-domain `bcid` cookie).
- URL shape: `/click?cnv_id={clickid}&cnv_status=lead&payout=0`

If FB shows leads but Binom shows ~0: postback missing, wrong clickid, or empty phone rejected before postback.

## Integration checklist (agent must verify)

- [ ] `form_assets/` present; `form.js` loads after intl utils
- [ ] `BINOM_FORM_CONFIG.initialCountry` matches geo
- [ ] Form has all tracking hiddens + 4 visible fields
- [ ] `tracking-scripts.html` in head
- [ ] `send.php` CONFIG filled; empty-phone guard kept
- [ ] `success.php` Lead + pixel fallback present
- [ ] Redirect path matches real lander folder slug
- [ ] Buyer default + `$buyers_config` include the campaign buyer mark
- [ ] Live test: submit → success URL has `?pixel=` → Network shows `facebook.com/tr` Lead
- [ ] Binom click gets conversion `lead` for same `clickid`

## Anti-patterns

- Direct `action="https://crm..."` bypassing `send.php`
- Pixel only in index head without Lead on success
- Landing id 0 + Protect VPN and **no white** → empty 307 (blank screen)
- Renaming `phone` / removing `clickid`
- Hardcoding buyer (must read `buyer_mark` / `{buyer}`)

## Folder layout after integrate

```
landers/{geo}---{offer}/
  index.html          # creative + form + snippets
  form_assets/        # from template
  send.php
  success.php
  antifraud.php       # optional but recommended
```

## Related Binom registration

After files are on disk (`binom_landers_data`):

- Landing: `landers/{slug}/index.html`, type `integrated`, language, group Hex/Bristol…
- Offer: `/landers/{slug}/index.html`, country ISO, same group
- Path: LP = landing id, offer = offer id (or direct with care)
- Protect: if Proxy/VPN on → set white LP or expect blank filter page

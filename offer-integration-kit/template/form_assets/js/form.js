// BINOM offer form handler — set window.BINOM_FORM_CONFIG before this script
// Example in index.html:
//   <script>window.BINOM_FORM_CONFIG={ initialCountry:"il", allowDropdown:false };</script>
(function(){
  var cfg = window.BINOM_FORM_CONFIG || {};
  window.__BINOM_INITIAL_COUNTRY = (cfg.initialCountry || "il").toLowerCase();
  window.__BINOM_ALLOW_DROPDOWN = !!cfg.allowDropdown;
})();

// form.js (гео -> { country, area, city } + фикс двойного "?" + без site_*/page_* полей)

document.addEventListener('DOMContentLoaded', function () {
	// Fix for local utils.js loading (v24 check)
	if (window.intlTelInputUtils && !window.intlTelInput.utils) {
		window.intlTelInput.utils = window.intlTelInputUtils
	}

	// ---------- УТИЛИТЫ ----------
	function sanitizeParam(val) {
		if (val == null) return ''
		val = String(val).trim()
		if (val === '') return ''
		try {
			val = decodeURIComponent(val)
		} catch (e) {}
		val = val.replace(/\/+index\.php$/i, '')
		return val.trim()
	}

	function getSanitized(formElem, inputName, urlParams, ...aliases) {
		const inputEl = formElem.querySelector(`input[name="${inputName}"]`)
		const rawFromInput = inputEl ? inputEl.value : null

		if (rawFromInput != null && rawFromInput !== '') {
			return sanitizeParam(rawFromInput)
		}

		let raw = urlParams.get(inputName)
		if (raw != null && raw !== '') return sanitizeParam(raw)

		for (const alt of aliases) {
			raw = urlParams.get(alt)
			if (raw != null && raw !== '') return sanitizeParam(raw)
		}

		return ''
	}

	// --- Мердж параметров в итоговый URL (без двойного "?") ---

	function landerBase() {
		const b = document.querySelector('base')
		if (b && b.href) return b.href
		const path = window.location.pathname
		if (path.endsWith('/')) return window.location.origin + path
		return window.location.origin + path.replace(/\/[^/]*$/, '/')
	}

	function resolveLanderUrl(url) {
		try {
			return new URL(String(url || ''), landerBase()).href
		} catch (e) {
			return url
		}
	}
	function mergeQueryIntoUrl(url, extraObj) {
		const u = new URL(url, landerBase())
		const q = new URLSearchParams(u.search)
		const current = new URLSearchParams(window.location.search)

		current.forEach((v, k) => {
			const kl = k.toLowerCase()
			if (kl === '_lp' || kl === '_token') return
			if (!q.has(k)) q.set(k, v)
		})

		if (extraObj) {
			Object.entries(extraObj).forEach(([k, v]) => {
				if (v !== undefined && v !== null && v !== '') q.set(k, String(v))
			})
		}

		u.search = q.toString()
		return u.pathname + u.search + (u.hash || '')
	}

	// ---------- ГЕО ПО IP (фоллбэки + таймаут) ----------
	async function fetchJson(url) {
		const res = await fetch(url, {
			headers: { Accept: 'application/json' },
			cache: 'no-store',
		})
		if (!res.ok) throw new Error('HTTP ' + res.status)
		return res.json()
	}

	async function geoFromIpapi() {
		const j = await fetchJson('https://ipapi.co/json/')
		return {
			ip: j.ip || '',
			country_code: j.country_code || '', // ISO-2
			region: j.region || '',
			city: j.city || '',
		}
	}

	async function geoFromIpwho() {
		const j = await fetchJson('https://ipwho.is/')
		if (j && j.success === false) throw new Error('ipwho fail')
		return {
			ip: j.ip || '',
			country_code: j.country_code || '', // ISO-2
			region: j.region || '',
			city: j.city || '',
		}
	}

	async function geoFromGeojs() {
		const j = await fetchJson('https://get.geojs.io/v1/ip/geo.json')
		return {
			ip: j.ip || '',
			country_code: j.country_code || '', // ISO-2
			region: j.region || '',
			city: j.city || '',
		}
	}

	function withTimeout(promise, ms) {
		return Promise.race([
			promise,
			new Promise((_, reject) =>
				setTimeout(() => reject(new Error('timeout')), ms)
			),
		])
	}

	async function fetchGeoSafe() {
		const chain = [geoFromIpapi, geoFromIpwho, geoFromGeojs]
		for (const fn of chain) {
			try {
				const data = await fn()
				if (data && (data.country_code || data.region || data.city)) return data
			} catch (_) {}
		}
		return null
	}

	// Стартуем префетч заранее
	const geoPromise = fetchGeoSafe().catch(() => null)

	// ---------- НОРМАЛИЗАЦИЯ hidden-инпутов ----------
	function normalizeHiddenInputsInDOM(formElem, urlParams) {
		const namesToNormalize = [
			['subid'],
			['fbclid'],
			['extr', 'click_id'],
			['pixel'],
			['bpixel'],
			['place'],
			['gclid'],
			['utm_term', 'utm_term', 'urm_term'],
			['utm_creative'],
			['utm_campaign'],
			['utm_position'],
			['utm_network'],
			['utm_target'],
			['utm_placement'],
			['utm_match'],
			['utm_source'],
			['utm_medium'],
			['utm_content'],
			['oi'],
			['click_id'],
			['clickid'],
		]

		namesToNormalize.forEach(([name, ...aliases]) => {
			const el = formElem.querySelector(`input[name="${name}"]`)
			if (!el) return
			const clean = getSanitized(formElem, name, urlParams, ...aliases)
			if (clean !== '') el.value = clean
		})

		// Проставим domain, если есть соответствующий hidden
		const domainEl = formElem.querySelector('input[name="domain"]')
		if (domainEl) domainEl.value = window.location.hostname
	}



	// ---------- ТЕЛЕФОНЫ (intl-tel-input) ----------
	function setPhoneInputsCountry() {
		const phoneInputs = document.querySelectorAll('input[name="phone"]')

		phoneInputs.forEach(input => {
			const iti = window.intlTelInput(input, {
				initialCountry: (window.__BINOM_INITIAL_COUNTRY || 'il'),
				geoIpLookup: function (callback) {
					// Пытаемся определить страну по IP
					geoPromise
						.then(geo => {
							const countryCode = (geo?.country_code || window.__BINOM_INITIAL_COUNTRY || 'il').toLowerCase()
							callback(countryCode)
						})
						.catch(() => callback(window.__BINOM_INITIAL_COUNTRY || 'il'))
				},
				// utilsScript: "js/utils.js", // Если нужно загрузить динамически
				allowDropdown: !!window.__BINOM_ALLOW_DROPDOWN,
				separateDialCode: true,
				strictMode: true,
				autoPlaceholder: "aggressive", // Показывает маску сразу
			})
			input._iti = iti // Сохраняем ссылку на инстанс
		})
	}

	// ---------- ИНИЦИАЛИЗАЦИЯ ----------
	setPhoneInputsCountry()


	const forms = document.querySelectorAll('form')
	const urlParams = new URLSearchParams(window.location.search)

	forms.forEach(form => {
		normalizeHiddenInputsInDOM(form, urlParams)
		;(function (formElem) {
			const firstnameInput = formElem.querySelector('input[name="firstname"]'),
				lastnameInput = formElem.querySelector('input[name="lastname"]'),
				emailInput = formElem.querySelector('input[name="email"]'),
				phoneInput = formElem.querySelector('input[name="phone"]'),
				commentsInput = formElem.querySelector('input[name="comments"]'),
				commentInput = formElem.querySelector('input[name="comment"]')

			const validateText = el => {
				if (el === null) return true
				const value = (el.value || '').trim()
				if (value.length < 3) {
					el.classList.add('error')
					el.classList.remove('correct')
					return false
				} else {
					el.classList.remove('error')
					el.classList.add('correct')
					return true
				}
			}

			const validateEmail = el => {
				const value = (el.value || '').trim()
				const regex =
					/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}(\.[0-9]{1,3}){3}\])|(([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}))$/
				if (regex.test(value)) {
					el.classList.remove('error')
					el.classList.add('correct')
					return true
				} else {
					el.classList.add('error')
					el.classList.remove('correct')
					return false
				}
			}

			const validatePhone = (el, showUI = false) => {
				if (!el._iti) {
					console.warn('intlTelInput instance not found on element', el);
					return true // Если библиотека не загрузилась, пропускаем
				}

				// Find error message element
				let errorEl = el.closest('.iti') ? el.closest('.iti').nextElementSibling : el.nextElementSibling;
				if (!errorEl || !errorEl.classList.contains('validation-error')) {
					errorEl = document.createElement('div');
					errorEl.className = 'validation-error';
					if (el.closest('.iti')) {
						el.closest('.iti').after(errorEl);
					} else {
						el.after(errorEl);
					}
				}
				
				// Map error codes
				const getErrorMessage = (code) => {
					switch (code) {
						case 1: return "رمز الدولة غير صالح"; // INVALID_COUNTRY_CODE
						case 2: return "Too short"; // TOO_SHORT
						case 3: return "Too long"; // TOO_LONG
						case 4: return "Invalid number"; // IS_POSSIBLE_LOCAL_ONLY
						case 5: return "Invalid length"; // INVALID_LENGTH
						default: return "Invalid number";
					}
				};
				
				// Using isValidNumberPrecise()
				const isValid = el._iti.isValidNumberPrecise ? el._iti.isValidNumberPrecise() : el._iti.isValidNumber();
				const errorCode = el._iti.getValidationError();

				// numberType: 1 = MOBILE, 2 = FIXED_LINE_OR_MOBILE (некоторые страны не различают)
				const numberType = el._iti.getNumberType();
				const isMobileType = numberType === 1 || numberType === 2;
				const isValidMobile = isValid && isMobileType;

				if (isValidMobile) {
					el.classList.remove('error');
					el.classList.add('correct');
					errorEl.style.display = 'none';
					errorEl.textContent = '';
				} else {
					el.classList.remove('correct');
					if (showUI) {
						el.classList.add('error');
						if (el.value.trim() !== '') {
							const msg = isValid && !isMobileType
								? "أدخل رقم جوالك"
								: getErrorMessage(errorCode);
							errorEl.textContent = msg;
							errorEl.style.display = 'block';
						}
					} else {
						el.classList.remove('error');
						errorEl.style.display = 'none';
					}
				}
				return isValidMobile;
			}


			if (phoneInput) {
				phoneInput.oninput = () => {
					// While typing, we check validation silently to remove errors if fixed
					validatePhone(phoneInput, false);
				};
			}

			formElem.addEventListener('submit', async function (evt) {
				evt.preventDefault()

				const isFirstnameValid = validateText(firstnameInput)
				const isEmailValid = validateEmail(emailInput)
				// Show UI on submit
				const isPhoneValid = phoneInput ? validatePhone(phoneInput, true) : true

				if (isFirstnameValid && isEmailValid && isPhoneValid) {
					// Show loading state using submittersSelector defined inside the listener or globally
					const submittersSelector = [
						'input[type=submit]',
						'button[type=submit]',
						'button:not([type])',
						'a[data-form-handler]',
					].join(',')
					formElem.querySelectorAll(submittersSelector).forEach(submitter => submitter.classList.add('mc--loading'))

					// PHONE HANDLING: Use getNumber() to normalize (e.g. remove leading 0)
					let phoneE164 = ''
					if (phoneInput && phoneInput._iti) {
						// getNumber() returns E.164 standard (e.g. +33612345678)
						// This automatically handles national trunk prefixes (leading 0)
						phoneE164 = phoneInput._iti.getNumber()
					} else {
						phoneE164 = phoneInput ? phoneInput.value : ''
					}
					
					const ua = navigator.userAgent || ''
					
					console.log('Phone raw:', phoneE164) // Для отладки

					// Гео: ждём префетч не дольше 800 мс
					let geo = null
					try {
						geo = await withTimeout(geoPromise, 800)
					} catch (_) {
						geo = null
					}

					// Преобразование к полям CRM: country(ISO-2), area(region), city
					const countryCode = (geo?.country_code || '')
						.toString()
						.trim()
						.toUpperCase()
					// если регион пуст, но есть город — дублируем город в area
					let areaVal = (geo?.region || '').toString().trim()
					const cityVal = (geo?.city || '').toString().trim()
					if (!areaVal && cityVal) areaVal = cityVal

					const dataToSend = {
						firstname: (firstnameInput?.value || '').trim(),
						lastname: (lastnameInput?.value || '').trim(),
						email: (emailInput?.value || '').trim(),
						phone: phoneE164,
						ua,
						comments: (commentsInput?.value || '').trim(),
						comment: (commentInput?.value || '').trim(),

						subid: getSanitized(formElem, 'subid', urlParams),
						fbclid: getSanitized(formElem, 'fbclid', urlParams),
						extr: getSanitized(formElem, 'extr', urlParams, 'click_id'),
						click_id: getSanitized(formElem, 'click_id', urlParams, 'extr'),
						clickid: getSanitized(
							formElem,
							'clickid',
							urlParams,
							'click_id',
							'extr'
						),

						pixel: getSanitized(formElem, 'pixel', urlParams),
						buyer_mark: (function(){
							let b = getSanitized(formElem, 'buyer_mark', urlParams, 'buyer');
							if (b) return b;
							try { b = localStorage.getItem('_tk_buyer') || ''; } catch(e) {}
							if (b) return b;
							try {
								const m = document.cookie.match(/(?:^|; )_tk_buyer=([^;]*)/);
								if (m) return decodeURIComponent(m[1]);
							} catch(e) {}
							return '';
						})(),
						buyer: getSanitized(formElem, 'buyer', urlParams, 'buyer_mark'),
						bpixel: getSanitized(formElem, 'bpixel', urlParams),
						place: getSanitized(formElem, 'place', urlParams),

						gclid: getSanitized(formElem, 'gclid', urlParams),

						utm_source: getSanitized(formElem, 'utm_source', urlParams),
						utm_campaign: getSanitized(formElem, 'utm_campaign', urlParams),
						utm_content: getSanitized(formElem, 'utm_content', urlParams),
						utm_creative: getSanitized(formElem, 'utm_creative', urlParams),
						utm_term: getSanitized(formElem, 'utm_term', urlParams, 'urm_term'),
						utm_medium: getSanitized(formElem, 'utm_medium', urlParams),
						utm_position: getSanitized(formElem, 'utm_position', urlParams),
						utm_network: getSanitized(formElem, 'utm_network', urlParams),
						utm_target: getSanitized(formElem, 'utm_target', urlParams),
						utm_placement: getSanitized(formElem, 'utm_placement', urlParams),
						utm_match: getSanitized(formElem, 'utm_match', urlParams),

						oi: getSanitized(formElem, 'oi', urlParams),

						language: document.documentElement.lang || '',

						// Только домен
						domain: window.location.hostname,

						// Гео для CRM (по IP посетителя; маска телефона — из BINOM_FORM_CONFIG.initialCountry)
						country: countryCode, // ISO-2, напр. "FR"
						area: areaVal || '',
						city: cityVal || '',
					}

					try {
						const response = await fetch(resolveLanderUrl('send.php'), {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
								'X-Requested-With': 'XMLHttpRequest',
							},
							body: new URLSearchParams(dataToSend).toString(),
						}).finally(() => {
							const submittersSelector = [
								'input[type=submit]',
								'button[type=submit]',
								'button:not([type])',
								'a[data-form-handler]',
							].join(',')
							formElem
								.querySelectorAll(submittersSelector)
								.forEach(submitter => {
									submitter.classList.remove('mc--loading')
								})
						})

						if (!response.ok) {
							const errorText = await response.text()
							throw Error(
								`HTTP error! Status: ${response.status}, Message: ${errorText}`
							)
						}
						// send.php возвращает сырой ответ CRM при успехе или {error:...} при ошибке
						const rawText = await response.text()
						let result
						try {
							result = JSON.parse(rawText)
						} catch (e) {
							throw Error('Invalid JSON response: ' + rawText.slice(0, 200))
						}

						const urlParamsLocal = new URLSearchParams(window.location.search)
						if (!result.error) {
							if (result.autologin && result.autologin.trim() !== '') {
								sessionStorage.setItem('autologin_url', result.autologin)
							}
							const finalUrl = mergeQueryIntoUrl(
								result?.redirectUrl ?? 'success.php',
								{ s: 1 }
							)
							window.location.href = resolveLanderUrl(finalUrl)
						} else {
							const finalUrl = mergeQueryIntoUrl(
								result?.redirectUrl ?? 'success.php',
								{ s: 0 }
							)
							window.location.href = resolveLanderUrl(finalUrl)
						}
					} catch (error) {
						console.error('Ошибка при отправке данных:', error)
						if (typeof n === 'function') {
							n('حدث خطأ. يرجى التحقق من النموذج والمحاولة مرة أخرى.')
						}
					} finally {
						/* keep E.164 value from iti; do not restore stale raw */
					}
				}
			})

			// Привязываем валидацию к событиям ввода
			firstnameInput?.addEventListener('input', () =>
				validateText(firstnameInput)
			)
			if (lastnameInput) {
				lastnameInput.addEventListener('input', () =>
					validateText(lastnameInput)
				)
			}
			if (phoneInput) {
				phoneInput.addEventListener('input', () => validatePhone(phoneInput))
			}
		})(form)
	})

	// Обработка диалогов и кнопок сабмита
	;(function () {
		document.querySelectorAll('a[href^="#"]').forEach(btn => {
			btn.onclick = () => {
				const targetEl = document.getElementById(btn.href.split('#')[1])
				if (targetEl !== null && targetEl.className.includes('dialog')) {
					targetEl.classList.add('u-dialog-open')
					document.documentElement.style.overflow = 'hidden'
				}
			}
		})

		const submittersSelector = [
			'input[type=submit]',
			'button[type=submit]',
			'button:not([type])',
			'a[data-form-handler]',
		].join(',')

		document.querySelectorAll('form').forEach(form => {
			const submitters = form.querySelectorAll(submittersSelector)
			/* 
			form.onsubmit = () => {
				submitters.forEach(submitter => submitter.classList.add('mc--loading'))
			}
			*/
		})
	})()
})

// Закрытие диалогов
document.querySelectorAll('.u-dialog-close-button').forEach(btn => {
	btn.onclick = () => {
		document.documentElement.style.overflow = ''
		btn.closest('.u-dialog-open').classList.remove('u-dialog-open')
	}
})

import { createI18n } from 'vue-i18n'
import en from './en.js'
import sw from './sw.js'
import additionsEn from './additions.en.js'
import additionsSw from './additions.sw.js'
import examxEn from './examx.en.js'
import examxSw from './examx.sw.js'
import printingEn from './printing.en.js'
import printingSw from './printing.sw.js'
import purchasingEn from './purchasing.en.js'
import purchasingSw from './purchasing.sw.js'
import expensexEn from './expensex.en.js'
import expensexSw from './expensex.sw.js'
import attendxEn from './attendx.en.js'
import attendxSw from './attendx.sw.js'
import budgetxEn from './budgetx.en.js'
import budgetxSw from './budgetx.sw.js'

const saved = localStorage.getItem('shulepay_lang') || 'en'

/**
 * Fill in what `base` lacks from `extra`, keeping every message `base` already has.
 * scripts/check-i18n.mjs builds the messages the same way — keep the two in step.
 */
function withMissing(base, extra) {
  const out = { ...base }
  for (const [k, v] of Object.entries(extra)) {
    if (v && typeof v === 'object' && base[k] && typeof base[k] === 'object') out[k] = withMissing(base[k], v)
    else if (!(k in out)) out[k] = v
  }
  return out
}

// A <name>.en.js / <name>.sw.js pair is the namespace `name`; the additions pair is merged at the root.
export const messages = {
  en: { ...withMissing(en, additionsEn), examx: examxEn, printing: printingEn, purchasing: purchasingEn, expensex: expensexEn, budgetx: budgetxEn, attendx: attendxEn },
  sw: { ...withMissing(sw, additionsSw), examx: examxSw, printing: printingSw, purchasing: purchasingSw, expensex: expensexSw, budgetx: budgetxSw, attendx: attendxSw },
}

export const i18n = createI18n({
  legacy: false,
  locale: saved,
  fallbackLocale: 'en',
  messages,
})

export function setLocale(lang) {
  i18n.global.locale.value = lang
  localStorage.setItem('shulepay_lang', lang)
  document.documentElement.setAttribute('lang', lang)
}

/** Month name in the language the app is showing ('long' = January / Januari, 'short' = Jan). */
export function formatMonth(month, style = 'long') {
  const lang = i18n.global.locale.value === 'sw' ? 'sw-TZ' : 'en-GB'
  return new Date(2000, Number(month) - 1, 1).toLocaleString(lang, { month: style })
}

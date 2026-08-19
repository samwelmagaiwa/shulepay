import { createI18n } from 'vue-i18n'
import en from './en.js'
import sw from './sw.js'

const saved = localStorage.getItem('shulepay_lang') || 'en'

export const i18n = createI18n({
  legacy: false,
  locale: saved,
  fallbackLocale: 'en',
  messages: { en, sw },
})

export function setLocale(lang) {
  i18n.global.locale.value = lang
  localStorage.setItem('shulepay_lang', lang)
  document.documentElement.setAttribute('lang', lang)
}

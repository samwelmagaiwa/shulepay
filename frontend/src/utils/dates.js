import { i18n } from '@/i18n'

/**
 * Locale for formatting dates with Date#toLocale*String.
 *
 * Month and weekday names come from the browser's own tables, so passing a
 * fixed locale ('sw-TZ', 'en-GB') kept them in that language whatever the user
 * had chosen in the language switcher. Read inside a template or computed, this
 * is reactive: the page redraws in the new language as soon as it is switched.
 */
export function dateLocale() {
  return i18n.global.locale.value === 'sw' ? 'sw-TZ' : 'en-GB'
}

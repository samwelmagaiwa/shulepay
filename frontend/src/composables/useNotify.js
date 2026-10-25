import { reactive } from 'vue'
import { i18n } from '@/i18n'

/**
 * App-wide notifications and confirmations, shown in one clean modal card
 * (see components/NotifyModal.vue, mounted once in the layout).
 *
 *   const notify = useNotify()
 *   notify.success('Marks saved')
 *   await notify.fromError(err)                      // explains any API error
 *   if (await notify.confirm({ title, message })) …  // resolves true / false
 *
 * State is module-level on purpose: every caller talks to the same single card.
 */
const t = (key, params) => i18n.global.t(key, params)

const state = reactive({
  visible: false,
  kind: 'info', // success | error | warning | info
  title: '',
  message: '',
  items: [],
  confirmMode: false,
  confirmText: '',
  cancelText: '',
  danger: false,
})

let resolver = null
let timer = null

function settle(value) {
  clearTimeout(timer)
  timer = null
  state.visible = false
  const done = resolver
  resolver = null
  done?.(value)
}

function open(config, { autoClose = 0 } = {}) {
  // A new card replaces whatever is showing; an unanswered confirm counts as "no".
  if (resolver) settle(false)
  clearTimeout(timer)

  Object.assign(state, {
    kind: 'info',
    title: '',
    message: '',
    items: [],
    confirmMode: false,
    confirmText: t('exams.notify.ok'),
    cancelText: t('exams.notify.cancel'),
    danger: false,
    ...config,
    visible: true,
  })

  if (autoClose) timer = setTimeout(() => settle(true), autoClose)
}

/** Flatten a Laravel `errors` object into a de-duplicated list of messages. */
function collectMessages(errors) {
  return [...new Set(Object.values(errors || {}).flat().filter(Boolean))]
}

/** Business-rule details worth listing (for example which classes still lack marks). */
function collectDetails(details) {
  if (!details?.by_class?.length) return []
  return details.by_class.map((c) => `${c.class}: ${t('exams.results.incomplete', { count: c.missing })}`)
}

/** Blob responses (failed PDF downloads) carry their JSON error as a Blob. */
async function readBody(err) {
  const data = err?.response?.data
  if (data instanceof Blob) {
    try {
      return JSON.parse(await data.text())
    } catch {
      return {}
    }
  }
  return data && typeof data === 'object' ? data : {}
}

export function useNotify() {
  const show = (kind, message, { title, items, autoClose } = {}) =>
    open({ kind, message, title: title ?? t(`exams.notify.${kind}Title`), items: items ?? [] }, { autoClose })

  return {
    state,

    success: (message, opts = {}) => show('success', message, { autoClose: 2500, ...opts }),
    error: (message, opts = {}) => show('error', message, opts),
    warning: (message, opts = {}) => show('warning', message, opts),
    info: (message, opts = {}) => show('info', message, { title: opts.title ?? '', ...opts }),

    /** Resolves true when the user confirms, false when they cancel or dismiss. */
    confirm({ title, message, confirmText, cancelText, danger = false } = {}) {
      return new Promise((resolve) => {
        open({
          kind: danger ? 'warning' : 'info',
          title,
          message,
          confirmMode: true,
          confirmText: confirmText ?? t('exams.notify.ok'),
          cancelText: cancelText ?? t('exams.notify.cancel'),
          danger,
        })
        resolver = resolve
      })
    },

    /** Turn any failed API call into a clear, specific message. */
    async fromError(err, { title } = {}) {
      const status = err?.response?.status
      const body = await readBody(err)
      let message
      let items = []

      if (!err?.response) {
        message = t('exams.notify.network')
      } else if (status === 422) {
        items = collectMessages(body.errors)
        message = items.length ? t('exams.notify.validationTitle') : body.message || t('exams.notify.generic')
        // A single business-rule error (no field errors) reads better as the message itself.
        if (!items.length) items = collectDetails(body.details)
      } else if (status === 403) {
        message = t('exams.notify.forbidden')
      } else if (status === 404) {
        message = body.message && !/^No query results/i.test(body.message) ? body.message : t('exams.notify.notFound')
      } else if (status === 429) {
        message = t('exams.notify.throttled')
      } else if (status >= 500) {
        message = t('exams.notify.server')
      } else {
        message = body.message || t('exams.notify.generic')
        items = collectDetails(body.details)
      }

      // A rule the user can fix (validation, wrong workflow state) is a warning; only
      // genuine failures (permissions, missing record, server, network) are errors.
      const fixable = status === 422 || status === 409
      open({
        kind: fixable ? 'warning' : 'error',
        title: title ?? t(fixable ? 'exams.notify.warningTitle' : 'exams.notify.errorTitle'),
        message,
        items,
      })
    },

    dismiss: () => settle(false),
    accept: () => settle(true),
  }
}

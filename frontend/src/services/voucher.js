import api from '@/services/api'

/**
 * Opens the payment voucher (a PDF) of an approved supplier payment, an approved expense or a paid salary
 * in a new tab, where the browser's own viewer can print or save it.
 * The tab is opened first, in answer to the click, so a pop-up blocker does not stop it while the document is made.
 *
 * @param {'supplier-payment'|'expense'|'payroll'} type
 * @param {number} id
 * @returns {Promise<string|null>} an error message to show, or null when it opened
 */
export async function openVoucher(type, id) {
  const tab = window.open('', '_blank')
  try {
    const res = await api.get(`/vouchers/${type}/${id}`, { responseType: 'blob' })
    const href = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    if (tab) tab.location.href = href
    else window.location.href = href
    return null
  } catch (e) {
    tab?.close()
    // an error answer comes back as a blob too; read the message out of it
    try {
      const text = await e?.response?.data?.text?.()
      return JSON.parse(text)?.message || null
    } catch {
      return e?.response?.data?.message || 'Hitilafu'
    }
  }
}

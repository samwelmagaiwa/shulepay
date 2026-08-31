import api from '@/services/api'

/**
 * Receipt printing / downloading.
 *
 * The PDF lives behind `auth:sanctum`, and the token is attached by the axios
 * interceptor — NOT by the browser. So pointing an iframe or a new tab straight
 * at /api/receipts/{id}/download sends no Authorization header, the request is
 * rejected as unauthenticated, and the user sees a server error instead of a
 * receipt. Fetch the PDF through axios and work from the resulting blob.
 */

let activeFrame = null
let activeUrl = null

function releaseFrame() {
  if (activeFrame) {
    activeFrame.remove()
    activeFrame = null
  }
  if (activeUrl) {
    URL.revokeObjectURL(activeUrl)
    activeUrl = null
  }
}

async function fetchPdfBlob(receiptId) {
  const { data } = await api.get(`/receipts/${receiptId}/download`, {
    responseType: 'blob',
  })
  // Some error responses still arrive as a blob; make sure this really is a PDF.
  if (data.type && !data.type.includes('pdf')) {
    throw new Error('Server did not return a PDF')
  }
  return data
}

/** Open the print dialog for a receipt. Resolves once the dialog is requested. */
export async function printReceipt(receiptId) {
  if (!receiptId) return
  releaseFrame()

  const blob = await fetchPdfBlob(receiptId)
  const url = URL.createObjectURL(blob)
  activeUrl = url

  // A blob: URL is same-origin, so the iframe can be printed directly — the
  // user gets the branded PDF, not a screen-styled copy, and no extra tab.
  const frame = document.createElement('iframe')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  frame.src = url
  frame.onload = () => {
    try {
      frame.contentWindow.focus()
      frame.contentWindow.print()
    } catch {
      window.open(url, '_blank', 'noopener')
    }
  }
  document.body.appendChild(frame)
  activeFrame = frame
}

/**
 * Print a single consolidated receipt covering every invoice a student has
 * — all terms' debts, payments made, and the remaining balance on one
 * printout, instead of one receipt per invoice.
 */
export async function printStudentStatement(studentId) {
  if (!studentId) return
  releaseFrame()

  const { data } = await api.get(`/students/${studentId}/statement-receipt`, {
    responseType: 'blob',
  })
  if (data.type && !data.type.includes('pdf')) {
    throw new Error('Server did not return a PDF')
  }

  const url = URL.createObjectURL(data)
  activeUrl = url

  const frame = document.createElement('iframe')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  frame.src = url
  frame.onload = () => {
    try {
      frame.contentWindow.focus()
      frame.contentWindow.print()
    } catch {
      window.open(url, '_blank', 'noopener')
    }
  }
  document.body.appendChild(frame)
  activeFrame = frame
}

/**
 * Print every invoice matching a status filter (Partial / Unpaid) as one
 * job — reuses whatever school/class/term filters the Invoices list is
 * currently showing, so what gets printed matches what's on screen.
 */
export async function printBulkInvoices(params) {
  releaseFrame()

  const { data } = await api.get('/invoices/bulk-receipt', {
    params,
    responseType: 'blob',
  })
  if (data.type && !data.type.includes('pdf')) {
    throw new Error('Server did not return a PDF')
  }

  const url = URL.createObjectURL(data)
  activeUrl = url

  const frame = document.createElement('iframe')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  frame.src = url
  frame.onload = () => {
    try {
      frame.contentWindow.focus()
      frame.contentWindow.print()
    } catch {
      window.open(url, '_blank', 'noopener')
    }
  }
  document.body.appendChild(frame)
  activeFrame = frame
}

/** Save the receipt as a file. */
export async function downloadReceipt(receiptId, receiptNumber = null) {
  if (!receiptId) return

  const blob = await fetchPdfBlob(receiptId)
  const url = URL.createObjectURL(blob)

  const a = document.createElement('a')
  a.href = url
  a.download = `Risiti-${receiptNumber || receiptId}.pdf`
  document.body.appendChild(a)
  a.click()
  a.remove()

  // Give the browser a moment to start the save before revoking.
  setTimeout(() => URL.revokeObjectURL(url), 10000)
}

/** Drop any iframe still held open (call from onBeforeUnmount / modal close). */
export function cleanupReceiptFrame() {
  releaseFrame()
}

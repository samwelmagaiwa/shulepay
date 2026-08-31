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

/**
 * responseType: 'blob' means an error response's JSON body (validation
 * messages, "too many invoices" 422s, etc.) arrives as a Blob too — axios
 * only auto-parses JSON on the success path. Decode it back onto the error
 * so callers reading e.response.data.message get the real backend message
 * instead of undefined, which fell through to a generic "could not open
 * the receipt" no matter what actually went wrong.
 */
async function decodeBlobErrorBody(e) {
  if (e.response?.data instanceof Blob && e.response.data.type?.includes('json')) {
    try {
      e.response.data = JSON.parse(await e.response.data.text())
    } catch {
      // Not actually JSON — leave the Blob as-is, caller falls back to its default message.
    }
  }
  throw e
}

async function fetchPdfBlob(receiptId) {
  let data
  try {
    ;({ data } = await api.get(`/receipts/${receiptId}/download`, {
      responseType: 'blob',
    }))
  } catch (e) {
    await decodeBlobErrorBody(e)
  }
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

  let data
  try {
    ;({ data } = await api.get(`/students/${studentId}/statement-receipt`, {
      responseType: 'blob',
    }))
  } catch (e) {
    await decodeBlobErrorBody(e)
  }
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

  let data
  try {
    ;({ data } = await api.get('/invoices/bulk-receipt', {
      params,
      responseType: 'blob',
    }))
  } catch (e) {
    await decodeBlobErrorBody(e)
  }
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

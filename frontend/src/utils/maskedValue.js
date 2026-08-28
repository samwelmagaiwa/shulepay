/**
 * How a withheld money figure is drawn.
 *
 * The backend does not send these values while the dashboard is locked, so there
 * is nothing here to hide — the dots stand in for data that never arrived. Kept
 * in one place so every card, on every page, masks identically; a card that
 * forgot to mask would show `0` and read as "the school collected nothing".
 */
export const MASK = '••••••'

/**
 * @param {boolean} locked   whether the payload came back redacted
 * @param {string|number} value  what to show when it did not
 * @param {string} prefix    e.g. 'TZS ' — dropped while masked, since a
 *                           currency prefix on dots just looks broken
 */
export function maskMoney(locked, value, prefix = '') {
  return locked ? MASK : `${prefix}${value}`
}

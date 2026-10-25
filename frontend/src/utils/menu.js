/**
 * Where a table row's "⋮" menu opens: downward, except on the last row of a table long enough to have room
 * above it for the whole menu (a short table opening upward would run up under the page's top bar).
 */
export const menuPlacement = (index, total) => (total >= 5 && index === total - 1 ? 'top-end' : 'bottom-end')

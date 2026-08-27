/**
 * Cleans up modal artefacts that CoreUI leaves behind.
 *
 * Two defects in @coreui/vue v5 CModal cause the UI to appear frozen until the
 * page is reloaded:
 *
 * 1. Orphaned backdrops. CBackdrop is rendered inside a <Transition> that uses
 *    CSS classes. If the leave transition is interrupted — a second modal opens
 *    or closes mid-animation, which is easy on pages that mount several modals —
 *    the transition never completes and the element is never removed. It is left
 *    as:
 *        position:fixed; inset:0; z-index:1050; opacity:0; pointer-events:auto
 *    An invisible sheet over the whole viewport that swallows every click while
 *    the page looks completely normal.
 *
 * 2. A stale scroll lock. CModal writes `modal-open` and `overflow:hidden`
 *    straight onto <body> in its Transition enter hook and only undoes that in
 *    the matching leave hook. It has no onUnmounted, so navigating away while a
 *    modal is open leaves the page permanently unable to scroll. The same gap
 *    leaks a window `mousedown` and `keydown` listener per occurrence.
 *
 * Rather than fork the dependency, reconcile the DOM: the number of backdrops
 * should never exceed the number of visible modals, and with no modal visible
 * there should be no backdrop and no scroll lock.
 */

function visibleModals() {
  return [...document.querySelectorAll('.modal')].filter(
    (m) => getComputedStyle(m).display !== 'none',
  )
}

function releaseScrollLock() {
  document.body.classList.remove('modal-open')
  document.body.style.removeProperty('overflow')
  document.body.style.removeProperty('padding-right')
  if (document.body.className === '') document.body.removeAttribute('class')
}

/**
 * Remove backdrops that no longer belong to a visible modal.
 * Returns the number removed, so callers can log or test it.
 */
export function reconcileModalArtifacts() {
  const open = visibleModals().length
  const backdrops = [...document.querySelectorAll('.modal-backdrop')]

  if (open === 0) {
    backdrops.forEach((b) => b.remove())
    releaseScrollLock()
    return backdrops.length
  }

  // A backdrop still carrying Vue's leave-transition class after the debounce
  // window is one whose transition never completed — it is on its way out and
  // stuck. Those are the click-blockers, so drop them first rather than trusting
  // document order.
  const stuck = backdrops.filter(
    (b) => b.classList.contains('v-leave-active') || getComputedStyle(b).opacity === '0',
  )
  const remove = stuck.length ? stuck : backdrops.slice(open)

  // Never strip every backdrop while a modal is genuinely open, or the open
  // modal would lose its dimmed background.
  const keep = Math.max(0, backdrops.length - open)
  const doomed = remove.slice(0, keep)
  doomed.forEach((b) => b.remove())
  return doomed.length
}

/**
 * Install the guard.
 *
 * Backdrops are teleported to <body>, so a MutationObserver on body's child list
 * sees every add and removal. Reconciling on a short delay lets a legitimate
 * transition finish first — we only want to catch the ones that never do.
 */
export function installModalGuard(router) {
  if (typeof window === 'undefined' || window.__modalGuardInstalled) return
  window.__modalGuardInstalled = true

  let timer = null
  const schedule = () => {
    clearTimeout(timer)
    // Longer than CoreUI's fade (150ms) so a normal close is never interfered with.
    timer = setTimeout(reconcileModalArtifacts, 500)
  }

  // childList alone is not enough: CModal defaults to unmountOnClose=false, so
  // closing a modal only toggles display via v-show — no nodes are added or
  // removed. Watch class/style too, debounced, so hiding a modal also triggers
  // a reconcile.
  new MutationObserver(schedule).observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['class', 'style'],
  })

  // A route change unmounts the view that owned the modal, so CModal's leave
  // hook never runs. Reconcile immediately rather than waiting on the observer.
  router?.afterEach(() => {
    reconcileModalArtifacts()
    setTimeout(reconcileModalArtifacts, 300)
  })

  // Last resort: if a click actually lands on a backdrop, something is already
  // wrong. Clean up synchronously so the user's next click works instead of
  // leaving them stuck until they reload.
  document.addEventListener(
    'click',
    (e) => {
      if (e.target instanceof Element && e.target.classList.contains('modal-backdrop')) {
        reconcileModalArtifacts()
      }
    },
    true,
  )
}

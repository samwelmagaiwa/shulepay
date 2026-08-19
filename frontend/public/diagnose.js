/**
 * ShulePay Login Diagnostics
 * Run in browser console: copy-paste everything, press Enter
 */
(async function diagnose() {
  const BASE = '/api'
  const log  = (icon, label, msg, data) => {
    const style = icon === '✅' ? 'color:green;font-weight:bold'
                : icon === '❌' ? 'color:red;font-weight:bold'
                : icon === '⚠️' ? 'color:orange;font-weight:bold'
                : 'color:#555;font-weight:bold'
    console.group(`%c${icon} ${label}`, style)
    if (msg)  console.log(msg)
    if (data) console.log(data)
    console.groupEnd()
  }

  console.clear()
  console.log('%c🔍 ShulePay Login Diagnostics', 'font-size:16px;font-weight:bold;color:#007f3e')
  console.log('━'.repeat(50))

  // ── 1. LocalStorage ──────────────────────────────────
  const rawToken = localStorage.getItem('shulepay_token')
  const rawUser  = localStorage.getItem('shulepay_user')

  log(rawToken ? '✅' : '⚠️', 'LocalStorage: shulepay_token',
    rawToken ? `Token present (${rawToken.length} chars)` : 'No token stored', rawToken)

  let parsedUser = null
  try {
    if (rawUser && rawUser !== 'undefined' && rawUser !== 'null') {
      parsedUser = JSON.parse(rawUser)
      log('✅', 'LocalStorage: shulepay_user', 'User parsed OK', parsedUser)
    } else {
      log('⚠️', 'LocalStorage: shulepay_user', `Value is: "${rawUser}" — will be null`)
    }
  } catch (e) {
    log('❌', 'LocalStorage: shulepay_user', `JSON.parse failed: ${e.message}`, rawUser)
  }

  // ── 2. Backend reachability ───────────────────────────
  console.log('\n' + '━'.repeat(50))
  try {
    const t0  = Date.now()
    const res = await fetch(`${BASE}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ email: 'accountant@gmail.com', password: '12345678' }),
    })
    const ms   = Date.now() - t0
    const json = await res.json()

    if (res.ok) {
      log('✅', `Login API (${ms}ms)`, 'POST /api/auth/login → 200', json)
      const testToken = json.token

      // ── 3. Auth /me with fresh token ─────────────────
      const meRes  = await fetch(`${BASE}/auth/me`, {
        headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${testToken}` }
      })
      const meJson = await meRes.json()
      if (meRes.ok) {
        log('✅', '/auth/me', `Status ${meRes.status}`, meJson)
      } else {
        log('❌', '/auth/me', `Status ${meRes.status}`, meJson)
      }

      // ── 4. Protected routes with fresh token ──────────
      const routes = ['/schools', '/invoices', '/students']
      for (const route of routes) {
        const r = await fetch(`${BASE}${route}`, {
          headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${testToken}` }
        })
        const body = await r.json().catch(() => null)
        log(r.ok ? '✅' : '❌', `GET ${route}`, `Status ${r.status}`, body)
      }

      // ── 5. Stored token vs fresh token ────────────────
      console.log('\n' + '━'.repeat(50))
      if (rawToken && rawToken !== testToken) {
        log('⚠️', 'Token mismatch', 'Stored token differs from a fresh login token')
        // Test stored token
        const storedRes = await fetch(`${BASE}/auth/me`, {
          headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${rawToken}` }
        })
        log(storedRes.ok ? '✅' : '❌', 'Stored token /me check', `Status ${storedRes.status}`,
          storedRes.ok ? 'Stored token is still valid' : '⛔ Stored token is INVALID — this causes auto-logout')
      } else if (!rawToken) {
        log('ℹ️', 'Token check', 'No stored token to compare')
      } else {
        log('✅', 'Token match', 'Stored token matches fresh login token')
      }

    } else {
      log('❌', `Login API (${ms}ms)`, `POST /api/auth/login → ${res.status}`, json)
    }
  } catch (e) {
    log('❌', 'Network error', e.message)
    log('⚠️', 'Hint', 'Is the backend running? Try: php artisan serve')
  }

  // ── 6. Vite proxy check ───────────────────────────────
  console.log('\n' + '━'.repeat(50))
  try {
    const probe = await fetch(`${BASE}/auth/login`, { method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: '{}' })
    const origin = probe.headers.get('x-powered-by') || probe.headers.get('server') || 'unknown'
    log('✅', 'Proxy', `Vite proxy is working → server: ${origin}`)
  } catch {
    log('❌', 'Proxy', 'Cannot reach /api — Vite proxy may be down')
  }

  // ── 7. CORS headers ───────────────────────────────────
  try {
    const cors = await fetch(`${BASE}/auth/login`, { method: 'OPTIONS',
      headers: { 'Origin': window.location.origin, 'Access-Control-Request-Method': 'POST' } })
    const allow = cors.headers.get('access-control-allow-origin')
    log(allow ? '✅' : '⚠️', 'CORS preflight', allow ? `Allowed: ${allow}` : 'No CORS headers (OK if using proxy)')
  } catch {}

  // ── 8. Summary ────────────────────────────────────────
  console.log('\n' + '━'.repeat(50))
  console.log('%c📋 SUMMARY', 'font-size:14px;font-weight:bold;color:#003082')
  console.table({
    'Backend reachable':    'check above',
    'Token in localStorage': rawToken ? '✅ Yes' : '❌ No',
    'User in localStorage':  parsedUser ? '✅ Yes' : '❌ No',
    'User role':             parsedUser?.role || 'N/A',
    'User email':            parsedUser?.email || 'N/A',
  })
  console.log('%c✔ Diagnostics complete', 'color:#007f3e;font-weight:bold')
})()

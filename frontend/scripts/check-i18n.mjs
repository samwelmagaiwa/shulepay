/**
 * Guards the two language files against drifting apart.
 *
 *   npm run i18n:check
 *
 * Fails when:
 *  - a key exists in en.js but not sw.js (or the reverse) — the user would see the
 *    raw key or the fallback language;
 *  - a message does not compile (a stray "@", "|" or brace only breaks at render time);
 *  - the two languages use different {placeholders} for the same key;
 *  - a screen calls t('some.literal.key') that neither language defines.
 */
import { baseCompile } from '@intlify/message-compiler'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { fileURLToPath } from 'node:url'

const SRC = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'src')

function load(file) {
  const code = fs.readFileSync(file, 'utf8').replace('export default', 'module.exports =')
  const m = { exports: {} }
  vm.runInNewContext(code, { module: m })
  return m.exports
}

function flatten(obj, prefix = '', out = {}) {
  for (const [k, v] of Object.entries(obj)) {
    const key = prefix ? `${prefix}.${k}` : k
    if (v && typeof v === 'object') flatten(v, key, out)
    else out[key] = v
  }
  return out
}

function sourceFiles(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name)
    if (fs.statSync(full).isDirectory()) {
      if (name !== 'i18n') sourceFiles(full, out)
    } else if (/\.(vue|js)$/.test(name)) out.push(full)
  }
  return out
}

const en = flatten(load(path.join(SRC, 'i18n/en.js')))
const sw = flatten(load(path.join(SRC, 'i18n/sw.js')))
const problems = []

for (const k of Object.keys(en)) if (!(k in sw)) problems.push(`missing in sw.js: ${k}`)
for (const k of Object.keys(sw)) if (!(k in en)) problems.push(`missing in en.js: ${k}`)

for (const [lang, dict] of [['en', en], ['sw', sw]]) {
  for (const [k, v] of Object.entries(dict)) {
    const errors = []
    try {
      baseCompile(String(v), { onError: (e) => errors.push(e.message) })
    } catch (e) {
      errors.push(e.message)
    }
    if (errors.length) problems.push(`does not compile (${lang}) ${k}: ${errors[0]}`)
  }
}

const params = (s) => [...String(s).matchAll(/\{([A-Za-z_]\w*)\}/g)].map((m) => m[1]).sort().join(',')
for (const k of Object.keys(en)) {
  if (k in sw && params(en[k]) !== params(sw[k])) {
    problems.push(`placeholders differ for ${k}: en {${params(en[k])}} sw {${params(sw[k])}}`)
  }
}

// Namespaces that are read as a whole (t/te/tm on a bare group) rather than as one message.
const WHOLE_GROUPS = new Set(['payroll.months'])
const groups = new Set([...Object.keys(en), ...Object.keys(sw)].flatMap((k) => {
  const parts = k.split('.')
  return parts.slice(1).map((_, i) => parts.slice(0, i + 1).join('.'))
}))

const call = /(?<![\w.])(?:\$t|t)\(\s*(['"`])((?:\\.|(?!\1).)*)\1/g
for (const file of sourceFiles(SRC)) {
  const text = fs.readFileSync(file, 'utf8')
  let m
  while ((m = call.exec(text))) {
    const key = m[2]
    if (m[1] === '`' && key.includes('${')) continue // built at run time
    if (key.endsWith('.') || WHOLE_GROUPS.has(key)) continue // prefix + value, or a guarded group
    if (!/^[A-Za-z]+(\.[A-Za-z0-9_]+)+$/.test(key)) continue // not a message key
    if (!(key in en) && !groups.has(key)) {
      const line = text.slice(0, m.index).split('\n').length
      problems.push(`${path.relative(SRC, file)}:${line} uses an undefined key: ${key}`)
    }
  }
}

if (problems.length) {
  console.error(problems.map((p) => `  ✗ ${p}`).join('\n'))
  console.error(`\n${problems.length} i18n problem(s).`)
  process.exit(1)
}
console.log(`i18n ok — ${Object.keys(en).length} keys in both languages, all messages compile.`)

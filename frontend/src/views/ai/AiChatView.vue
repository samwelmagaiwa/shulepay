<script setup>
import { ref, nextTick, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import api from '@/services/api'

const { t, locale } = useI18n()
const auth     = useAuthStore()
const branding = useBrandingStore()

// ── State ─────────────────────────────────────────────────────────────────────
const messages    = ref([])
const draft       = ref('')
const thinking    = ref(false)
const bottomEl    = ref(null)
const inputEl     = ref(null)
const error       = ref(null)
const usage       = ref(null)

const firstName = computed(() => auth.user?.name?.split(' ')[0] || '')

const usagePercent = computed(() => {
  if (!usage.value) return 0
  return Math.round((usage.value.requests_today / usage.value.requests_limit) * 100)
})

const usageColor = computed(() => {
  if (usagePercent.value >= 90) return '#dc3545'
  if (usagePercent.value >= 70) return '#fd7e14'
  return '#007f3e'
})

const tokensPercent = computed(() => {
  if (!usage.value) return 0
  return Math.round((usage.value.tokens_today / usage.value.tokens_limit) * 100)
})

const usageLabel = computed(() => {
  if (!usage.value) return null
  const rem = usage.value.requests_limit - usage.value.requests_today
  if (rem <= 0) return t('ai.limitReached')
  if (usagePercent.value >= 90) return t('ai.nLeft', { n: rem })
  if (usagePercent.value >= 70) return t('ai.nRemaining', { n: rem })
  return null
})

// The assistant is for staff only (parents never see this page); finance answers are for owner/accountant.
const isFinance = computed(() => auth.isOwner || auth.isAccountant || auth.isSuperAdmin)

const suggestions = computed(() => {
  const base = [
    { icon: '🎓', text: t('ai.sg.students') },
    { icon: '📋', text: t('ai.sg.classes') },
    { icon: '✅', text: t('ai.sg.absent') },
  ]
  if (isFinance.value) {
    base.push({ icon: '💰', text: t('ai.sg.collected') })
    base.push({ icon: '📄', text: t('ai.sg.unpaid') })
    base.push({ icon: '💼', text: t('ai.sg.expenses') })
    base.push({ icon: '👷', text: t('ai.sg.payroll') })
  }
  base.push({ icon: '🏫', text: t('ai.sg.overview') })
  return base.slice(0, 6)
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function newId() { return Date.now() + Math.random() }

function scrollToBottom() {
  nextTick(() => bottomEl.value?.scrollIntoView({ behavior: 'smooth' }))
}

function renderMarkdown(text) {
  if (!text) return ''

  // Escape HTML first
  let out = text
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

  // Tables: detect blocks of pipe-rows (header | separator | rows)
  out = out.replace(
    /^(\|.+\|\n\|[-| :]+\|\n(?:\|.+\|\n?)+)/gm,
    (block) => {
      const rows = block.trim().split('\n')
      const headers = rows[0].split('|').filter((_, i, a) => i > 0 && i < a.length - 1).map(h => h.trim())
      const body = rows.slice(2) // skip separator
      const thead = '<thead><tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr></thead>'
      const tbody = '<tbody>' + body.map(row => {
        const cells = row.split('|').filter((_, i, a) => i > 0 && i < a.length - 1).map(c => c.trim())
        return '<tr>' + cells.map(c => `<td>${c}</td>`).join('') + '</tr>'
      }).join('') + '</tbody>'
      return `<div class="ai-table-wrap"><table class="ai-table">${thead}${tbody}</table></div>\n`
    }
  )

  // Headings, bold, lists
  out = out
    .replace(/^### (.+)$/gm, '<h6 class="mb-1 mt-2 fw-bold">$1</h6>')
    .replace(/^## (.+)$/gm,  '<h5 class="mb-1 mt-2 fw-semibold">$1</h5>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/^\s*[-*] (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>)+/gs, '<ul class="mb-1 ps-3">$&</ul>')
    .replace(/\n/g, '<br>')

  return out
}

function formatTime(ts) {
  return new Date(ts).toLocaleTimeString(locale.value === 'sw' ? 'sw-TZ' : 'en-TZ', { hour: '2-digit', minute: '2-digit' })
}

// ── Send message ──────────────────────────────────────────────────────────────
async function send(text) {
  const content = (text ?? draft.value).trim()
  if (!content || thinking.value) return

  draft.value = ''
  error.value = null

  messages.value.push({ id: newId(), role: 'user', content, ts: Date.now() })
  scrollToBottom()
  thinking.value = true

  try {
    const { data } = await api.post('/ai/chat', {
      messages: messages.value.map(m => ({ role: m.role, content: m.content })),
    })
    messages.value.push({ id: newId(), role: 'assistant', content: data.reply ?? data.error ?? t('ai.noResponse'), ts: Date.now() })
    if (data.usage) usage.value = data.usage
  } catch (e) {
    const msg = e.response?.data?.error ?? e.response?.data?.message ?? t('ai.errorGeneric')
    error.value = msg
    messages.value.push({ id: newId(), role: 'assistant', content: '⚠️ ' + msg, ts: Date.now() })
  } finally {
    thinking.value = false
    scrollToBottom()
    nextTick(() => inputEl.value?.focus())
  }
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send() }
}

function clearChat() {
  if (messages.value.length && confirm(t('ai.clearConfirm'))) messages.value = []
}

// Track which capability groups are expanded (default: all collapsed)
const openGroups = ref({ everyone: false, teaching: false, finance: false, locked: false })
const toggleGroup = (key) => { openGroups.value[key] = !openGroups.value[key] }

onMounted(() => {
  inputEl.value?.focus()
  // Force the CContainer ancestor to pass its real flex-computed height down to this page
  document.body.classList.add('ai-chat-active')
})
onUnmounted(() => {
  document.body.classList.remove('ai-chat-active')
})
</script>

<template>
  <div class="ai-page">

    <!-- ══════════════════════════════════════════════════════ LEFT — Chat ══ -->
    <div class="ai-chat-col">

      <!-- Header -->
      <div class="ai-chat-header">
        <div class="ai-header-left">
          <div class="ai-logo-circle">🤖</div>
          <div>
            <div class="ai-header-title">ShuleAI</div>
            <div class="ai-header-sub">{{ t('ai.subtitle', { name: firstName || t('ai.your') }) }}</div>
          </div>
        </div>
        <div class="ai-header-right">
          <span class="ai-live-badge">● {{ t('ai.live') }}</span>
          <button v-if="messages.length" class="ai-clear-btn" @click="clearChat" :title="t('ai.clearChat')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </div>
      </div>

      <!-- Messages -->
      <div class="ai-messages" ref="messagesEl">

        <!-- Welcome state — compact, no vertical scroll needed -->
        <div v-if="messages.length === 0" class="ai-welcome">
          <div class="ai-welcome-row">
            <div class="ai-welcome-icon">🤖</div>
            <div>
              <h4 class="ai-welcome-title">{{ t('ai.hi', { name: firstName || t('ai.there') }) }}</h4>
              <p class="ai-welcome-sub">{{ t('ai.intro') }}</p>
            </div>
          </div>
          <div class="ai-suggest-section">
            <div class="ai-suggest-label">{{ t('ai.tryAsking') }}</div>
            <div class="ai-suggest-list">
              <button v-for="s in suggestions" :key="s.text" class="ai-suggest-item" @click="send(s.text)">
                <span class="ai-suggest-icon">{{ s.icon }}</span>
                <span class="ai-suggest-text">{{ s.text }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Message list -->
        <template v-else>
          <div
            v-for="msg in messages"
            :key="msg.id"
            class="ai-msg-row"
            :class="msg.role === 'user' ? 'ai-msg-user' : 'ai-msg-ai'"
          >
            <div v-if="msg.role === 'assistant'" class="ai-bubble-avatar">🤖</div>

            <div class="ai-bubble-col">
              <div class="ai-bubble" :class="msg.role === 'user' ? 'ai-bubble-u' : 'ai-bubble-a'" v-html="renderMarkdown(msg.content)" />
              <div class="ai-ts">{{ formatTime(msg.ts) }}</div>
            </div>

            <div v-if="msg.role === 'user'" class="ai-bubble-avatar ai-user-av">
              {{ auth.user?.name?.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() }}
            </div>
          </div>
        </template>

        <!-- Thinking -->
        <div v-if="thinking" class="ai-msg-row ai-msg-ai">
          <div class="ai-bubble-avatar">🤖</div>
          <div class="ai-bubble ai-bubble-a ai-thinking">
            <span class="ai-dot"/><span class="ai-dot"/><span class="ai-dot"/>
          </div>
        </div>

        <div ref="bottomEl"/>
      </div>

      <!-- Quick suggestions after first message -->
      <div v-if="messages.length > 0 && !thinking" class="ai-quick-chips">
        <button v-for="s in suggestions.slice(0, 4)" :key="s.text" class="ai-quick-chip" @click="send(s.text)">
          {{ s.text }}
        </button>
      </div>

      <!-- Input bar -->
      <div class="ai-input-bar">
        <div class="ai-input-wrap">
          <textarea
            ref="inputEl"
            v-model="draft"
            @keydown="onKeydown"
            :placeholder="t('ai.placeholder')"
            rows="1"
            class="ai-input"
            :disabled="thinking"
          />
          <button class="ai-send" :disabled="!draft.trim() || thinking" @click="send()">
            <svg v-if="!thinking" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            <CSpinner v-else size="sm"/>
          </button>
        </div>
        <div class="ai-input-hint">{{ t('ai.hint') }}</div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════ RIGHT — Sidebar ══ -->
    <div class="ai-sidebar">

      <!-- ── Who you are ───────────────────────────────────────────────────── -->
      <div class="ai-panel ai-panel-user">
        <div class="ai-user-card">
          <div class="ai-user-av-lg">
            {{ auth.user?.name?.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase() }}
          </div>
          <div>
            <div class="ai-user-name">{{ auth.user?.name }}</div>
            <div class="ai-user-role">{{ auth.user?.roles?.[0] ?? t('ai.staff') }}</div>
            <div class="ai-user-school">{{ branding.name || t('ai.yourSchool') }}</div>
          </div>
        </div>
      </div>

      <!-- ── What I can access (grouped by module, collapsible) ──────────────── -->
      <div class="ai-panel">
        <div class="ai-panel-title">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          {{ t('ai.dataAccess') }}
        </div>

        <!-- Everyone -->
        <div class="ai-cap-group">
          <button class="ai-cap-group-toggle" @click="toggleGroup('everyone')">
            <span>{{ t('ai.groupEveryone') }}</span>
            <svg class="ai-chevron" :class="{ open: openGroups.everyone }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div v-show="openGroups.everyone" class="ai-cap-items">
            <div class="ai-cap-item"><span>🏫</span> {{ t('ai.cap.overview') }}</div>
            <div class="ai-cap-item"><span>🎓</span> {{ t('ai.cap.students') }}</div>
            <div class="ai-cap-item"><span>📚</span> {{ t('ai.cap.subjects') }}</div>
            <div class="ai-cap-item"><span>📅</span> {{ t('ai.cap.years') }}</div>
          </div>
        </div>

        <!-- Attendance (every staff role) -->
        <div class="ai-cap-group">
          <button class="ai-cap-group-toggle" @click="toggleGroup('teaching')">
            <span>{{ t('ai.groupTeaching') }}</span>
            <svg class="ai-chevron" :class="{ open: openGroups.teaching }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div v-show="openGroups.teaching" class="ai-cap-items">
            <div class="ai-cap-item"><span>✅</span> {{ t('ai.cap.attendanceDaily') }}</div>
            <div class="ai-cap-item"><span>📊</span> {{ t('ai.cap.attendanceStudent') }}</div>
            <div class="ai-cap-item"><span>🚫</span> {{ t('ai.cap.absentees') }}</div>
            <div class="ai-cap-item"><span>⚠️</span> {{ t('ai.cap.lowAttendance') }}</div>
          </div>
        </div>

        <!-- Finance -->
        <div v-if="isFinance" class="ai-cap-group">
          <button class="ai-cap-group-toggle" @click="toggleGroup('finance')">
            <span>{{ t('ai.groupFinance') }}</span>
            <svg class="ai-chevron" :class="{ open: openGroups.finance }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div v-show="openGroups.finance" class="ai-cap-items">
            <div class="ai-cap-item"><span>💰</span> {{ t('ai.cap.fees') }}</div>
            <div class="ai-cap-item"><span>📄</span> {{ t('ai.cap.unpaid') }}</div>
            <div class="ai-cap-item"><span>💼</span> {{ t('ai.cap.expenses') }}</div>
            <div class="ai-cap-item"><span>📈</span> {{ t('ai.cap.revenue') }}</div>
            <div class="ai-cap-item"><span>🗂️</span> {{ t('ai.cap.budget') }}</div>
            <div class="ai-cap-item"><span>🎁</span> {{ t('ai.cap.discounts') }}</div>
            <div class="ai-cap-item"><span>↩️</span> {{ t('ai.cap.refunds') }}</div>
            <div class="ai-cap-item"><span>👷</span> {{ t('ai.cap.payroll') }}</div>
            <div class="ai-cap-item"><span>👥</span> {{ t('ai.cap.employees') }}</div>
            <div class="ai-cap-item"><span>🧾</span> {{ t('ai.cap.studentFinance') }}</div>
            <div class="ai-cap-item"><span>👨‍👩‍👧</span> {{ t('ai.cap.guardians') }}</div>
          </div>
        </div>

        <!-- Locked for non-finance -->
        <div v-if="!isFinance" class="ai-cap-group ai-cap-locked">
          <button class="ai-cap-group-toggle ai-cap-group-toggle-locked" @click="toggleGroup('locked')">
            <span>🔒 {{ t('ai.groupLocked') }}</span>
            <svg class="ai-chevron" :class="{ open: openGroups.locked }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div v-show="openGroups.locked" class="ai-cap-items">
            <div class="ai-cap-item ai-locked"><span>💰</span> {{ t('ai.lock.fees') }}</div>
            <div class="ai-cap-item ai-locked"><span>👷</span> {{ t('ai.lock.payroll') }}</div>
            <div class="ai-cap-item ai-locked"><span>📊</span> {{ t('ai.lock.budget') }}</div>
          </div>
        </div>
      </div>

      <!-- ── Daily AI Usage ─────────────────────────────────────────────────── -->
      <div class="ai-panel">
        <div class="ai-panel-title">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
          {{ t('ai.usageTitle') }}
        </div>

        <div class="ai-quota-metric">
          <div class="ai-quota-label">
            <span>{{ t('ai.requestsToday') }}</span>
            <span :style="{ color: usageColor, fontWeight: 700 }">
              {{ usage ? usage.requests_today : '0' }} / {{ usage ? usage.requests_limit : '1,500' }}
            </span>
          </div>
          <div class="ai-qbar-bg">
            <div class="ai-qbar-fill" :style="{ width: usagePercent + '%', background: usageColor }"/>
          </div>
        </div>

        <div class="ai-quota-metric">
          <div class="ai-quota-label">
            <span>{{ t('ai.tokensToday') }}</span>
            <span :style="{ color: tokensPercent >= 80 ? '#dc3545' : tokensPercent >= 60 ? '#fd7e14' : '#007f3e', fontWeight: 700 }">
              {{ usage ? '~' + Math.round(usage.tokens_today / 1000) + 'k' : '0k' }} / 1,000k
            </span>
          </div>
          <div class="ai-qbar-bg">
            <div class="ai-qbar-fill" :style="{ width: tokensPercent + '%', background: tokensPercent >= 80 ? '#dc3545' : tokensPercent >= 60 ? '#fd7e14' : '#007f3e' }"/>
          </div>
        </div>

        <div v-if="usageLabel" class="ai-quota-warn" :style="{ color: usageColor }">{{ usageLabel }}</div>
        <div v-else class="ai-quota-hint">{{ t('ai.resetsDaily') }}</div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* ── Layout ─────────────────────────────────────────────────────────────────── */
.ai-page {
  display: flex;
  gap: 0;
  /* Fill whatever height the parent layout gives us — no fixed calc */
  height: 100%;
  min-height: 0;
  overflow: hidden;
}

/* ── Chat column ─────────────────────────────────────────────────────────────── */
.ai-chat-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;      /* critical: allows flex child to shrink below content size */
  overflow: hidden;   /* clip so children (messages) scroll, not the whole column */
  border-right: 1px solid var(--cui-border-color, #dee2e6);
}

/* Header */
.ai-chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid var(--cui-border-color, #dee2e6);
  flex-shrink: 0;
  background: var(--cui-body-bg, #fff);
}
.ai-header-left { display: flex; align-items: center; gap: 12px; }
.ai-header-right { display: flex; align-items: center; gap: 10px; }
.ai-logo-circle {
  width: 40px; height: 40px;
  background: linear-gradient(135deg, #007f3e 0%, #16a34a 100%);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; flex-shrink: 0;
}
.ai-header-title { font-weight: 700; font-size: .95rem; color: #007f3e; }
.ai-header-sub { font-size: .72rem; color: var(--cui-secondary-color, #6c757d); }
.ai-live-badge {
  font-size: .65rem; font-weight: 700; letter-spacing: .06em;
  color: #007f3e; background: rgba(0,127,62,.1);
  padding: 2px 8px; border-radius: 20px;
  border: 1px solid rgba(0,127,62,.2);
}
.ai-clear-btn {
  background: none; border: 1px solid var(--cui-border-color, #dee2e6);
  border-radius: 6px; padding: 4px 7px; cursor: pointer;
  color: var(--cui-secondary-color, #888);
  display: flex; align-items: center;
}
.ai-clear-btn:hover { border-color: #dc3545; color: #dc3545; }

/* Messages */
.ai-messages {
  flex: 1;
  min-height: 0;    /* allows this flex child to shrink so input bar stays visible */
  overflow-y: auto;
  padding: 20px;
  scroll-behavior: smooth;
}

/* Welcome — compact, single-screen, no scroll */
.ai-welcome { padding: 20px 20px 0; }
.ai-welcome-row {
  display: flex; align-items: flex-start; gap: 14px;
  margin-bottom: 18px;
}
.ai-welcome-icon { font-size: 2.4rem; flex-shrink: 0; line-height: 1; margin-top: 2px; }
.ai-welcome-title { font-weight: 700; color: #007f3e; font-size: 1.05rem; margin: 0 0 4px; }
.ai-welcome-sub { color: var(--cui-secondary-color, #6c757d); font-size: .82rem; line-height: 1.5; margin: 0; }

/* Suggestion list */
.ai-suggest-section { border-top: 1px solid var(--cui-border-color, #eee); padding-top: 14px; }
.ai-suggest-label {
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: var(--cui-secondary-color, #999); margin-bottom: 8px;
}
.ai-suggest-list { display: flex; flex-direction: column; gap: 1px; }
.ai-suggest-item {
  display: flex; align-items: center; gap: 10px;
  background: none; border: none; padding: 7px 8px;
  border-radius: 7px; cursor: pointer; text-align: left; width: 100%;
  font-size: .84rem; color: var(--cui-body-color, #333);
  transition: background .12s, color .12s;
}
.ai-suggest-item:hover { background: rgba(0,127,62,.08); color: #007f3e; }
.ai-suggest-icon { font-size: 1rem; flex-shrink: 0; width: 22px; text-align: center; }
.ai-suggest-text { flex: 1; line-height: 1.3; }

/* Message rows */
.ai-msg-row {
  display: flex; align-items: flex-end; gap: 8px;
  margin-bottom: 14px;
}
.ai-msg-user { flex-direction: row-reverse; }

.ai-bubble-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; flex-shrink: 0;
}
.ai-user-av {
  background: #007f3e; color: #fff;
  font-size: .65rem; font-weight: 700;
}

.ai-bubble-col { display: flex; flex-direction: column; max-width: min(78%, 640px); }
.ai-msg-user .ai-bubble-col { align-items: flex-end; }
.ai-msg-ai  .ai-bubble-col { align-items: flex-start; }

.ai-bubble {
  padding: 10px 14px; border-radius: 18px;
  font-size: .87rem; line-height: 1.58; word-break: break-word;
}
.ai-bubble-u {
  background: #007f3e; color: #fff;
  border-bottom-right-radius: 4px;
}
.ai-bubble-a {
  background: var(--cui-tertiary-bg, #f8f9fa);
  border: 1px solid var(--cui-border-color, #dee2e6);
  color: var(--cui-body-color, #212529);
  border-bottom-left-radius: 4px;
}
.ai-ts {
  font-size: .63rem;
  color: var(--cui-secondary-color, #aaa);
  margin-top: 3px; padding: 0 4px;
}

/* Tables inside AI bubbles */
.ai-table-wrap { overflow-x: auto; margin: 6px 0; border-radius: 6px; }
.ai-table {
  border-collapse: collapse; width: 100%; font-size: .8rem;
  border: 1px solid var(--cui-border-color, #dee2e6);
}
.ai-table th {
  background: rgba(0,127,62,.1); color: #007f3e;
  font-weight: 700; padding: 7px 12px; text-align: left;
  border-bottom: 2px solid rgba(0,127,62,.25);
  white-space: nowrap;
}
.ai-table td {
  padding: 6px 12px;
  border-bottom: 1px solid var(--cui-border-color, #eee);
  vertical-align: top;
}
.ai-table tr:last-child td { border-bottom: none; }
.ai-table tr:nth-child(even) td { background: rgba(0,0,0,.02); }

/* Thinking */
.ai-thinking { display: flex; align-items: center; gap: 5px; padding: 14px 18px; }
.ai-dot { width: 7px; height: 7px; background: #007f3e; border-radius: 50%; animation: ai-bounce 1.2s infinite ease-in-out; }
.ai-dot:nth-child(2) { animation-delay: .2s; }
.ai-dot:nth-child(3) { animation-delay: .4s; }
@keyframes ai-bounce {
  0%, 60%, 100% { transform: translateY(0); opacity: .5; }
  30%            { transform: translateY(-6px); opacity: 1; }
}

/* Quick chips */
.ai-quick-chips {
  padding: 8px 20px 0;
  display: flex; flex-wrap: wrap; gap: 6px;
  flex-shrink: 0;
}
.ai-quick-chip {
  background: transparent; border: 1px solid var(--cui-border-color, #dee2e6);
  color: var(--cui-secondary-color, #555); border-radius: 12px;
  font-size: .72rem; padding: 4px 11px; cursor: pointer;
  transition: background .15s; white-space: nowrap;
}
.ai-quick-chip:hover { background: rgba(0,127,62,.07); border-color: rgba(0,127,62,.3); color: #007f3e; }

/* Input bar */
.ai-input-bar {
  padding: 12px 20px 10px;
  border-top: 1px solid var(--cui-border-color, #dee2e6);
  flex-shrink: 0;
  background: var(--cui-body-bg, #fff);
}
.ai-input-wrap { display: flex; gap: 8px; align-items: flex-end; }
.ai-input {
  flex: 1; border: 1.5px solid var(--cui-border-color, #dee2e6);
  border-radius: 22px; padding: 10px 16px; font-size: .88rem;
  line-height: 1.4; resize: none; overflow-y: auto; max-height: 120px;
  background: var(--cui-body-bg, #fff); color: var(--cui-body-color, #212529);
  outline: none; transition: border-color .15s, box-shadow .15s;
}
.ai-input:focus { border-color: #007f3e; box-shadow: 0 0 0 3px rgba(0,127,62,.12); }
.ai-input:disabled { opacity: .6; }
.ai-send {
  width: 44px; height: 44px; border-radius: 50%;
  background: #007f3e; color: #fff; border: none;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; transition: background .15s;
}
.ai-send:hover:not(:disabled) { background: #005f2e; }
.ai-send:disabled { background: #b0bec5; cursor: default; }
.ai-input-hint { text-align: center; font-size: .63rem; color: var(--cui-secondary-color, #aaa); margin-top: 5px; }

/* ── Sidebar ─────────────────────────────────────────────────────────────────── */
.ai-sidebar {
  width: 300px;
  flex-shrink: 0;
  /* Scroll independently — constrained to the flex row, not page content height */
  align-self: stretch;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  background: var(--cui-tertiary-bg, #f8f9fa);
  border-left: 1px solid var(--cui-border-color, #dee2e6);
}

.ai-panel {
  padding: 14px 16px;
  border-bottom: 1px solid var(--cui-border-color, #dee2e6);
}
.ai-panel-title {
  display: flex; align-items: center; gap: 6px;
  font-size: .7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--cui-secondary-color, #6c757d);
  margin-bottom: 10px;
}

/* User card */
.ai-panel-user { background: linear-gradient(135deg, rgba(0,127,62,.08) 0%, transparent 100%); }
.ai-user-card { display: flex; align-items: center; gap: 12px; }
.ai-user-av-lg {
  width: 42px; height: 42px; border-radius: 50%;
  background: #007f3e; color: #fff;
  font-size: .8rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.ai-user-name { font-weight: 700; font-size: .85rem; color: var(--cui-body-color, #1a1a1a); }
.ai-user-role { font-size: .72rem; color: #007f3e; font-weight: 600; text-transform: capitalize; }
.ai-user-school { font-size: .7rem; color: var(--cui-secondary-color, #888); margin-top: 1px; }

/* Capabilities grouped */
.ai-cap-group { margin-bottom: 4px; }
.ai-cap-group:last-child { margin-bottom: 0; }
.ai-cap-group-toggle {
  display: flex; align-items: center; justify-content: space-between;
  width: 100%; background: none; border: none; padding: 5px 6px;
  border-radius: 6px; cursor: pointer;
  font-size: .68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: var(--cui-secondary-color, #888);
  transition: background .12s, color .12s;
}
.ai-cap-group-toggle:hover { background: rgba(0,127,62,.08); color: #007f3e; }
.ai-cap-group-toggle-locked { color: #dc354580; }
.ai-cap-group-toggle-locked:hover { color: #dc3545; background: rgba(220,53,69,.06); }
.ai-chevron { transition: transform .2s; flex-shrink: 0; }
.ai-chevron.open { transform: rotate(180deg); }
.ai-cap-items { padding: 2px 6px 6px; }
.ai-cap-item {
  display: flex; align-items: center; gap: 7px;
  font-size: .78rem; color: var(--cui-body-color, #333);
  padding: 3px 0;
}
.ai-cap-item span:first-child { font-size: .9rem; flex-shrink: 0; }
.ai-locked { opacity: .45; }

/* Try asking */
.ai-try-list { display: flex; flex-direction: column; gap: 2px; }
.ai-try-item {
  display: flex; align-items: flex-start; gap: 7px;
  background: none; border: none; padding: 5px 6px;
  border-radius: 7px; cursor: pointer; text-align: left;
  font-size: .77rem; color: var(--cui-body-color, #333);
  transition: background .12s; width: 100%;
}
.ai-try-item:hover { background: rgba(0,127,62,.09); color: #007f3e; }
.ai-try-item span:first-child { flex-shrink: 0; font-size: .88rem; margin-top: 1px; }

/* Tips */
.ai-tips { display: flex; flex-direction: column; gap: 5px; }
.ai-tip { font-size: .77rem; color: var(--cui-secondary-color, #666); line-height: 1.4; }

/* Quota */
.ai-quota-metric { margin-bottom: 10px; }
.ai-quota-label { display: flex; justify-content: space-between; font-size: .74rem; margin-bottom: 5px; color: var(--cui-body-color, #444); }
.ai-qbar-bg { height: 5px; background: var(--cui-border-color, #e9ecef); border-radius: 4px; overflow: hidden; }
.ai-qbar-fill { height: 100%; border-radius: 4px; transition: width .4s, background .4s; }
.ai-quota-models { display: flex; align-items: center; flex-wrap: wrap; gap: 3px; margin-top: 10px; margin-bottom: 6px; }
.ai-qm-label { font-size: .65rem; color: var(--cui-secondary-color, #999); margin-right: 2px; width: 100%; margin-bottom: 3px; }
.ai-qm-tag { font-size: .65rem; background: rgba(0,127,62,.1); color: #007f3e; border: 1px solid rgba(0,127,62,.2); border-radius: 4px; padding: 1px 5px; font-weight: 600; }
.ai-qm-arrow { font-size: .65rem; color: var(--cui-secondary-color, #aaa); }
.ai-quota-warn { font-size: .72rem; font-weight: 600; margin-top: 6px; }
.ai-quota-hint { font-size: .7rem; color: var(--cui-secondary-color, #999); margin-top: 6px; line-height: 1.4; }

/* Dark mode */
:root[data-coreui-theme="dark"] .ai-bubble-a,
.dark .ai-bubble-a { background: #1e2a1e; border-color: #2d4a2d; color: #e8f5e9; }
:root[data-coreui-theme="dark"] .ai-sidebar,
.dark .ai-sidebar { background: #161c24; }
:root[data-coreui-theme="dark"] .ai-chat-header,
:root[data-coreui-theme="dark"] .ai-input-bar,
.dark .ai-chat-header, .dark .ai-input-bar { background: #1a1e24; }
:root[data-coreui-theme="dark"] .ai-input,
.dark .ai-input { background: #1e2530; color: #e2e8f0; border-color: #2d3748; }
:root[data-coreui-theme="dark"] .ai-panel-user,
.dark .ai-panel-user { background: linear-gradient(135deg, rgba(0,127,62,.15) 0%, transparent 100%); }

/* Responsive */
@media (max-width: 900px) {
  .ai-sidebar { width: 260px; }
}
@media (max-width: 700px) {
  .ai-page { flex-direction: column; height: auto; }
  .ai-chat-col { height: 70vh; }
  .ai-sidebar { width: 100%; height: auto; border-left: none; border-top: 1px solid var(--cui-border-color, #dee2e6); }
}
</style>

<!-- Global: lock the full layout chain to viewport height so .ai-page can be constrained -->
<style>
/* 1. Wrapper: change min-vh-100 to exact 100vh so flex children get a real height */
body.ai-chat-active .wrapper {
  height: 100vh !important;
  overflow: hidden !important;
}
/* 2. Body (flex-grow-1): must not overflow — its computed height is now viewport minus header/footer */
body.ai-chat-active .body {
  overflow: hidden !important;
  min-height: 0 !important;
}
/* 3. CContainer: pass that height down, strip default padding so the chat fills edge-to-edge */
body.ai-chat-active .dashboard-shell {
  height: 100% !important;
  overflow: hidden !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}
</style>

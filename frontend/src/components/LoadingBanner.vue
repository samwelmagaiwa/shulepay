<template>
  <div class="loading-overlay">
    <div class="loader-card">

      <!-- 3D School building illustration -->
      <div class="school-orb">
        <div class="orb-ring orb-ring-1"></div>
        <div class="orb-ring orb-ring-2"></div>
        <div class="orb-ring orb-ring-3"></div>

        <div class="orb-core">
          <!-- School SVG icon -->
          <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" class="school-icon">
            <!-- Roof -->
            <polygon points="10,34 40,10 70,34" fill="url(#roofGrad)" />
            <!-- Roof ridge -->
            <polygon points="10,34 40,14 70,34 70,36 40,16 10,36" fill="rgba(255,255,255,0.25)" />
            <!-- Building body -->
            <rect x="14" y="34" width="52" height="32" rx="2" fill="url(#wallGrad)" />
            <!-- Door -->
            <rect x="33" y="48" width="14" height="18" rx="3" fill="url(#doorGrad)" />
            <!-- Door knob -->
            <circle cx="44" cy="57" r="1.5" fill="rgba(255,220,100,0.9)" />
            <!-- Windows -->
            <rect x="18" y="40" width="12" height="10" rx="2" fill="url(#winGrad)" />
            <rect x="50" y="40" width="12" height="10" rx="2" fill="url(#winGrad)" />
            <!-- Window shine -->
            <rect x="19" y="41" width="4" height="3" rx="1" fill="rgba(255,255,255,0.5)" />
            <rect x="51" y="41" width="4" height="3" rx="1" fill="rgba(255,255,255,0.5)" />
            <!-- Flag pole -->
            <line x1="40" y1="10" x2="40" y2="4" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" />
            <!-- Flag -->
            <polygon points="40,4 50,7 40,10" fill="url(#flagGrad)" />
            <!-- Ground line -->
            <rect x="10" y="66" width="60" height="2" rx="1" fill="rgba(255,255,255,0.2)" />

            <defs>
              <linearGradient id="roofGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#5b8ff9" />
                <stop offset="100%" stop-color="#3a6fd8" />
              </linearGradient>
              <linearGradient id="wallGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#ffffff" stop-opacity="0.95" />
                <stop offset="100%" stop-color="#e8f0fe" stop-opacity="0.85" />
              </linearGradient>
              <linearGradient id="doorGrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#4285f4" />
                <stop offset="100%" stop-color="#1a56db" />
              </linearGradient>
              <linearGradient id="winGrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#93c5fd" stop-opacity="0.9" />
                <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.7" />
              </linearGradient>
              <linearGradient id="flagGrad" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#fbbf24" />
                <stop offset="100%" stop-color="#f59e0b" />
              </linearGradient>
            </defs>
          </svg>

          <!-- Orbiting dot -->
          <div class="orbit-dot"></div>
        </div>
      </div>

      <!-- Brand label -->
      <div class="brand-name">ShulePay</div>

      <!-- Loading text -->
      <div class="loading-text">Inapakia data ya shule...</div>

      <!-- Dots -->
      <div class="dot-track">
        <span class="dot d1"></span>
        <span class="dot d2"></span>
        <span class="dot d3"></span>
        <span class="dot d4"></span>
        <span class="dot d5"></span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Overlay ────────────────────────────────────────────── */
.loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  justify-content: center;
  align-items: center;
  background: radial-gradient(ellipse at 50% 40%, #1e3a8a 0%, #0f172a 100%);
  backdrop-filter: blur(6px);
}

/* ── Card ───────────────────────────────────────────────── */
.loader-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 2.5rem 3rem;
  border-radius: 2rem;
  background: linear-gradient(145deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
  border: 1px solid rgba(255,255,255,0.12);
  box-shadow:
    0 32px 80px rgba(0,0,0,0.5),
    0 0 0 1px rgba(255,255,255,0.05) inset,
    0 1px 0 rgba(255,255,255,0.15) inset;
  backdrop-filter: blur(20px);
}

/* ── Orb ────────────────────────────────────────────────── */
.school-orb {
  position: relative;
  width: 150px;
  height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.orb-ring {
  position: absolute;
  border-radius: 50%;
  border: 1.5px solid rgba(99,179,237,0.25);
  animation: spin-ring linear infinite;
}
.orb-ring-1 { width: 150px; height: 150px; animation-duration: 8s; border-color: rgba(99,179,237,0.2); }
.orb-ring-2 { width: 120px; height: 120px; animation-duration: 6s; animation-direction: reverse; border-color: rgba(167,139,250,0.25); }
.orb-ring-3 { width: 90px;  height: 90px;  animation-duration: 4s; border-color: rgba(251,191,36,0.2); }

@keyframes spin-ring {
  to { transform: rotate(360deg); }
}

.orb-core {
  position: relative;
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: linear-gradient(145deg, #2563eb, #1e40af);
  box-shadow:
    0 0 0 3px rgba(96,165,250,0.3),
    0 0 30px rgba(37,99,235,0.6),
    0 8px 32px rgba(0,0,0,0.4),
    inset 0 1px 0 rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  animation: orb-pulse 3s ease-in-out infinite;
}

@keyframes orb-pulse {
  0%, 100% { box-shadow: 0 0 0 3px rgba(96,165,250,0.3), 0 0 30px rgba(37,99,235,0.6), 0 8px 32px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.2); }
  50%       { box-shadow: 0 0 0 6px rgba(96,165,250,0.15), 0 0 50px rgba(37,99,235,0.8), 0 8px 32px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.2); }
}

.school-icon {
  width: 46px;
  height: 46px;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* Orbiting dot around the orb */
.orbit-dot {
  position: absolute;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #fbbf24;
  box-shadow: 0 0 10px #fbbf24, 0 0 20px rgba(251,191,36,0.5);
  top: -4px;
  left: calc(50% - 4px);
  transform-origin: 4px calc(36px + 4px);
  animation: orbit-dot 2s linear infinite;
}

@keyframes orbit-dot {
  to { transform: rotate(360deg); }
}

/* ── Brand ──────────────────────────────────────────────── */
.brand-name {
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: 2px;
  background: linear-gradient(90deg, #93c5fd, #ffffff, #fbbf24);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-transform: uppercase;
}

/* ── Text ───────────────────────────────────────────────── */
.loading-text {
  color: rgba(255,255,255,0.6);
  font-size: 0.85rem;
  letter-spacing: 0.5px;
  font-weight: 400;
}

/* ── Dot track ──────────────────────────────────────────── */
.dot-track {
  display: flex;
  gap: 6px;
  margin-top: 0.25rem;
}

.dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: rgba(255,255,255,0.3);
  animation: dot-wave 1.4s ease-in-out infinite;
}

.d1 { animation-delay: 0s; }
.d2 { animation-delay: 0.14s; }
.d3 { animation-delay: 0.28s; }
.d4 { animation-delay: 0.42s; }
.d5 { animation-delay: 0.56s; }

@keyframes dot-wave {
  0%, 80%, 100% { background: rgba(255,255,255,0.25); transform: scale(1); }
  40%            { background: #60a5fa; transform: scale(1.4); box-shadow: 0 0 8px rgba(96,165,250,0.7); }
}
</style>

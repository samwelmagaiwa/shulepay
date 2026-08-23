<template>
  <div class="loading-overlay">
    <div class="loader-wrap">

      <!-- Circular spinner ring -->
      <div class="ring-wrap">
        <svg class="spinner-svg" viewBox="0 0 120 120">
          <!-- Track -->
          <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="6" />
          <!-- Animated arc -->
          <circle
            cx="60" cy="60" r="52"
            fill="none"
            stroke="url(#arcGrad)"
            stroke-width="6"
            stroke-linecap="round"
            stroke-dasharray="326"
            stroke-dashoffset="80"
            class="spinner-arc"
          />
          <defs>
            <linearGradient id="arcGrad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#38bdf8" stop-opacity="0" />
              <stop offset="60%" stop-color="#38bdf8" />
              <stop offset="100%" stop-color="#818cf8" />
            </linearGradient>
          </defs>
        </svg>

        <!-- Center logo -->
        <div class="center-logo">
          <svg viewBox="0 0 56 56" xmlns="http://www.w3.org/2000/svg" width="56" height="56">
            <!-- Building -->
            <rect x="10" y="28" width="36" height="22" rx="2" fill="white" fill-opacity="0.15"/>
            <!-- Roof -->
            <polygon points="6,28 28,10 50,28" fill="white" fill-opacity="0.9"/>
            <!-- Door -->
            <rect x="23" y="36" width="10" height="14" rx="2" fill="white" fill-opacity="0.4"/>
            <!-- Left window -->
            <rect x="13" y="32" width="8" height="7" rx="1.5" fill="white" fill-opacity="0.5"/>
            <!-- Right window -->
            <rect x="35" y="32" width="8" height="7" rx="1.5" fill="white" fill-opacity="0.5"/>
            <!-- Flag pole -->
            <line x1="28" y1="10" x2="28" y2="4" stroke="white" stroke-width="1.5" stroke-opacity="0.8"/>
            <polygon points="28,4 36,7 28,10" fill="#fbbf24"/>
          </svg>
        </div>
      </div>

      <!-- Text -->
      <p class="loading-label">Inapakia...</p>

      <!-- 3-dot pulse -->
      <div class="pulse-dots">
        <span></span><span></span><span></span>
      </div>

    </div>
  </div>
</template>

<style scoped>
.loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(10, 18, 42, 0.88);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.loader-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

/* ── Ring ── */
.ring-wrap {
  position: relative;
  width: 120px;
  height: 120px;
}

.spinner-svg {
  width: 120px;
  height: 120px;
  animation: rotate 1.4s linear infinite;
}

@keyframes rotate {
  to { transform: rotate(360deg); }
}

.spinner-arc {
  transform-origin: 60px 60px;
}

.center-logo {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ── Text ── */
.loading-label {
  color: rgba(255, 255, 255, 0.65);
  font-size: 0.88rem;
  font-weight: 500;
  letter-spacing: 0.8px;
  margin: 0;
  text-transform: uppercase;
}

/* ── Dots ── */
.pulse-dots {
  display: flex;
  gap: 6px;
}

.pulse-dots span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #38bdf8;
  animation: pulse 1.2s ease-in-out infinite;
}

.pulse-dots span:nth-child(2) { animation-delay: 0.2s; }
.pulse-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes pulse {
  0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
  40%            { opacity: 1;   transform: scale(1.2); }
}
</style>

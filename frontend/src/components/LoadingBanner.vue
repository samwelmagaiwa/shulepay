<template>
  <div class="loading-overlay">
    <div class="scene-card">
      <svg viewBox="0 0 400 440" xmlns="http://www.w3.org/2000/svg" class="scene-svg">
        <defs>
          <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#c8eaff"/>
            <stop offset="100%" stop-color="#e8f7f0"/>
          </linearGradient>
          <linearGradient id="grass" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#5cb85c"/>
            <stop offset="100%" stop-color="#3d8b3d"/>
          </linearGradient>
          <linearGradient id="bldgWall" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#b0bec5"/>
            <stop offset="100%" stop-color="#90a4ae"/>
          </linearGradient>
          <linearGradient id="busBody" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#e53935"/>
            <stop offset="100%" stop-color="#ef5350"/>
          </linearGradient>
          <linearGradient id="ribbon" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#f9a825"/>
            <stop offset="100%" stop-color="#fbc02d"/>
          </linearGradient>
          <filter id="shadow" x="-10%" y="-10%" width="120%" height="130%">
            <feDropShadow dx="0" dy="3" stdDeviation="3" flood-opacity="0.18"/>
          </filter>
        </defs>

        <!-- ── Sky ── -->
        <rect width="400" height="260" fill="url(#sky)"/>

        <!-- Faint chalkboard math hints in sky -->
        <text x="30" y="40" font-size="11" fill="#a8d5c2" opacity="0.5" font-family="monospace">A+L = e²⁷</text>
        <text x="290" y="55" font-size="10" fill="#a8d5c2" opacity="0.4" font-family="monospace">∑x = n</text>
        <text x="50" y="90" font-size="9" fill="#a8d5c2" opacity="0.35" font-family="monospace">y=mx+c</text>

        <!-- Sun -->
        <circle cx="340" cy="48" r="28" fill="#fff176" opacity="0.9"/>
        <circle cx="340" cy="48" r="22" fill="#ffee58"/>
        <circle cx="340" cy="48" r="16" fill="#fdd835"/>

        <!-- Clouds -->
        <g opacity="0.92" class="cloud-1">
          <ellipse cx="90" cy="58" rx="38" ry="18" fill="white"/>
          <ellipse cx="68" cy="64" rx="22" ry="14" fill="white"/>
          <ellipse cx="120" cy="61" rx="24" ry="13" fill="white"/>
        </g>
        <g opacity="0.75" class="cloud-2">
          <ellipse cx="230" cy="72" rx="30" ry="14" fill="white"/>
          <ellipse cx="212" cy="77" rx="17" ry="11" fill="white"/>
          <ellipse cx="254" cy="75" rx="18" ry="11" fill="white"/>
        </g>

        <!-- Stars -->
        <text x="170" y="38" font-size="13" fill="#fdd835" class="star-twinkle">★</text>
        <text x="310" y="90" font-size="10" fill="#fdd835" class="star-twinkle" style="animation-delay:.4s">★</text>
        <text x="148" y="78" font-size="8"  fill="#fdd835" class="star-twinkle" style="animation-delay:.8s">✦</text>
        <text x="360" y="120" font-size="9" fill="#fdd835" class="star-twinkle" style="animation-delay:1.2s">★</text>

        <!-- ── Golden Banner Ribbon ── -->
        <g filter="url(#shadow)">
          <path d="M70,22 Q200,10 330,22 L330,46 Q200,58 70,46 Z" fill="url(#ribbon)"/>
          <path d="M70,22 L55,34 L70,46 Z" fill="#e65100"/>
          <path d="M330,22 L345,34 L330,46 Z" fill="#e65100"/>
          <text x="200" y="39" text-anchor="middle" font-size="15" font-weight="900"
                fill="#1a237e" font-family="Arial,sans-serif" letter-spacing="2">ShulePay</text>
        </g>

        <!-- ── Pine Trees (far left) ── -->
        <g>
          <polygon points="18,190 35,135 52,190" fill="#2e7d32"/>
          <polygon points="22,205 35,160 48,205" fill="#388e3c"/>
          <rect x="32" y="205" width="6" height="16" fill="#5d4037"/>
        </g>
        <g>
          <polygon points="2,200 14,152 26,200" fill="#388e3c" opacity=".8"/>
          <rect x="11" y="200" width="5" height="12" fill="#5d4037"/>
        </g>

        <!-- ── Pine Trees (far right) ── -->
        <g>
          <polygon points="348,190 365,135 382,190" fill="#2e7d32"/>
          <polygon points="352,205 365,160 378,205" fill="#388e3c"/>
          <rect x="362" y="205" width="6" height="16" fill="#5d4037"/>
        </g>
        <g>
          <polygon points="374,200 386,152 398,200" fill="#388e3c" opacity=".8"/>
          <rect x="383" y="200" width="5" height="12" fill="#5d4037"/>
        </g>

        <!-- ── School Building ── -->
        <g filter="url(#shadow)">
          <!-- Clock tower -->
          <rect x="56" y="115" width="22" height="40" fill="#78909c"/>
          <circle cx="67" cy="128" r="7" fill="white" stroke="#546e7a" stroke-width="1.5"/>
          <line x1="67" y1="128" x2="67" y2="123" stroke="#333" stroke-width="1.2" stroke-linecap="round"/>
          <line x1="67" y1="128" x2="71" y2="128" stroke="#333" stroke-width="1.2" stroke-linecap="round"/>
          <!-- Main body -->
          <rect x="30" y="130" width="110" height="90" fill="url(#bldgWall)"/>
          <!-- Roof -->
          <polygon points="22,130 85,95 148,130" fill="#546e7a"/>
          <!-- Flag -->
          <line x1="85" y1="95" x2="85" y2="72" stroke="#9e9e9e" stroke-width="2"/>
          <rect x="85" y="72" width="18" height="11" fill="#c62828" rx="1"/>
          <!-- Sign board -->
          <rect x="42" y="140" width="86" height="20" fill="#fdd835" rx="3"/>
          <text x="85" y="154" text-anchor="middle" font-size="7.5" font-weight="800"
                fill="#1a237e" font-family="Arial,sans-serif" letter-spacing="1.5">MAGRETH PRIMARY</text>
          <!-- Windows -->
          <rect x="38" y="168" width="22" height="18" fill="#a8d8f0" rx="3" stroke="#546e7a" stroke-width="1"/>
          <rect x="38" y="168" width="22" height="18" fill="none" stroke="white" stroke-width=".5" rx="3"/>
          <line x1="49" y1="168" x2="49" y2="186" stroke="#546e7a" stroke-width=".8"/>
          <rect x="74" y="168" width="22" height="18" fill="#a8d8f0" rx="3" stroke="#546e7a" stroke-width="1"/>
          <line x1="85" y1="168" x2="85" y2="186" stroke="#546e7a" stroke-width=".8"/>
          <rect x="110" y="168" width="22" height="18" fill="#fdd835" rx="3" stroke="#546e7a" stroke-width="1"/>
          <!-- Door -->
          <rect x="74" y="192" width="22" height="28" fill="#3e2723" rx="3 3 0 0"/>
          <circle cx="93" cy="207" r="2" fill="#fdd835"/>
        </g>

        <!-- ── Wooden Fence ── -->
        <g fill="#a1887f" stroke="#795548" stroke-width="0.8">
          <!-- Left fence -->
          <rect x="155" y="218" width="6" height="22" rx="1"/>
          <rect x="164" y="218" width="6" height="22" rx="1"/>
          <rect x="173" y="218" width="6" height="22" rx="1"/>
          <rect x="154" y="222" width="28" height="4" rx="1"/>
          <rect x="154" y="230" width="28" height="4" rx="1"/>
        </g>
        <g fill="#a1887f" stroke="#795548" stroke-width="0.8">
          <!-- Right fence -->
          <rect x="265" y="218" width="6" height="22" rx="1"/>
          <rect x="274" y="218" width="6" height="22" rx="1"/>
          <rect x="283" y="218" width="6" height="22" rx="1"/>
          <rect x="264" y="222" width="28" height="4" rx="1"/>
          <rect x="264" y="230" width="28" height="4" rx="1"/>
        </g>

        <!-- ── Grass / Ground ── -->
        <rect x="0" y="248" width="400" height="60" fill="url(#grass)"/>
        <!-- Ground flowers -->
        <g>
          <circle cx="160" cy="252" r="5" fill="#ff7043"/>
          <circle cx="155" cy="248" r="3" fill="#ffb300"/>
          <circle cx="165" cy="248" r="3" fill="#ff7043"/>
          <circle cx="160" cy="244" r="3" fill="#ffcc02"/>
          <line x1="160" y1="257" x2="160" y2="268" stroke="#388e3c" stroke-width="1.5"/>
        </g>
        <g>
          <circle cx="250" cy="253" r="4" fill="#e91e63"/>
          <circle cx="246" cy="249" r="3" fill="#ff69b4"/>
          <circle cx="254" cy="249" r="3" fill="#e91e63"/>
          <circle cx="250" cy="245" r="3" fill="#fce4ec"/>
          <line x1="250" y1="257" x2="250" y2="266" stroke="#388e3c" stroke-width="1.5"/>
        </g>

        <!-- ── Student (cartoon girl, center) ── -->
        <g class="student-walk" transform="translate(190,110)">
          <!-- Backpack -->
          <rect x="22" y="46" width="18" height="26" rx="4" fill="#8d6e63"/>
          <rect x="24" y="50" width="14" height="18" rx="2" fill="#6d4c41"/>
          <rect x="27" y="55" width="8" height="2" rx="1" fill="#a1887f"/>
          <!-- Hair / head -->
          <circle cx="18" cy="18" r="19" fill="#2c1a0e"/>
          <circle cx="18" cy="20" r="17" fill="#ffc8a2"/>
          <!-- Hair top -->
          <path d="M1 16 Q18 -2 35 16 Q32 2 18 1 Q4 2 1 16" fill="#2c1a0e"/>
          <!-- Braids -->
          <path d="M3 24 Q-3 42 1 58 Q2 62 4 58 Q2 46 5 30" fill="#2c1a0e"/>
          <path d="M33 24 Q39 42 35 58 Q34 62 32 58 Q34 46 31 30" fill="#2c1a0e"/>
          <!-- Braid tips -->
          <circle cx="2" cy="60" r="3" fill="#fdd835"/>
          <circle cx="34" cy="60" r="3" fill="#fdd835"/>
          <!-- Eyes -->
          <ellipse cx="12" cy="21" rx="3.5" ry="4" fill="white"/>
          <ellipse cx="24" cy="21" rx="3.5" ry="4" fill="white"/>
          <circle cx="12" cy="22" r="2.5" fill="#4e342e"/>
          <circle cx="24" cy="22" r="2.5" fill="#4e342e"/>
          <circle cx="13" cy="21" r="1" fill="white"/>
          <circle cx="25" cy="21" r="1" fill="white"/>
          <!-- Cheeks -->
          <circle cx="8"  cy="27" r="4" fill="#ff8a80" opacity="0.55"/>
          <circle cx="28" cy="27" r="4" fill="#ff8a80" opacity="0.55"/>
          <!-- Smile -->
          <path d="M11 30 Q18 36 25 30" stroke="#333" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          <!-- Neck -->
          <rect x="14" y="37" width="8" height="8" fill="#ffc8a2"/>
          <!-- Shirt (white) -->
          <rect x="3" y="44" width="30" height="36" rx="5" fill="#eceff1"/>
          <!-- Tie (dark blue) -->
          <path d="M16 44 L18 44 L20 57 L18 68 L16 57 Z" fill="#1565c0"/>
          <rect x="15" y="44" width="6" height="5" rx="1" fill="#1e88e5"/>
          <!-- Left arm (holding books) -->
          <path d="M3 52 Q-8 64 -12 78" stroke="#ffc8a2" stroke-width="8" fill="none" stroke-linecap="round"/>
          <!-- Right arm -->
          <path d="M33 52 Q42 60 40 72" stroke="#ffc8a2" stroke-width="8" fill="none" stroke-linecap="round"/>
          <!-- Books held (left arm) -->
          <rect x="-22" y="72" width="20" height="26" rx="2" fill="#ef5350"/>
          <rect x="-19" y="75" width="20" height="26" rx="2" fill="#26c6da"/>
          <text x="-14" y="85" font-size="5" fill="white" font-weight="700">MATH</text>
          <text x="-15" y="93" font-size="4.5" fill="white" font-weight="600">SCIENCE</text>
          <!-- Skirt (blue) -->
          <path d="M3 80 L33 80 L38 112 L-2 112 Z" fill="#1976d2"/>
          <!-- Legs + socks -->
          <rect x="5"  y="112" width="10" height="32" rx="4" fill="#eceff1"/>
          <rect x="21" y="112" width="10" height="32" rx="4" fill="#eceff1" class="leg-r"/>
          <!-- Shoes -->
          <ellipse cx="10" cy="146" rx="10" ry="5" fill="#1a1a2e"/>
          <ellipse cx="26" cy="146" rx="10" ry="5" fill="#1a1a2e"/>
        </g>

        <!-- ── School Bus ── -->
        <g class="bus-move" filter="url(#shadow)">
          <!-- Body -->
          <rect x="286" y="215" width="90" height="40" rx="5" fill="url(#busBody)"/>
          <!-- Roof stripe -->
          <rect x="286" y="215" width="90" height="10" rx="5" fill="#b71c1c"/>
          <!-- Windows -->
          <rect x="292" y="220" width="16" height="12" rx="2" fill="#e3f2fd" stroke="#1565c0" stroke-width="1"/>
          <rect x="312" y="220" width="16" height="12" rx="2" fill="#e3f2fd" stroke="#1565c0" stroke-width="1"/>
          <rect x="332" y="220" width="16" height="12" rx="2" fill="#e3f2fd" stroke="#1565c0" stroke-width="1"/>
          <!-- Windshield -->
          <rect x="352" y="218" width="20" height="18" rx="3" fill="#b3e5fc" stroke="#1565c0" stroke-width="1"/>
          <!-- Yellow stripe -->
          <rect x="286" y="235" width="90" height="6" fill="#fdd835"/>
          <!-- Wheels -->
          <circle cx="306" cy="256" r="10" fill="#212121"/>
          <circle cx="306" cy="256" r="5"  fill="#616161"/>
          <circle cx="306" cy="256" r="2"  fill="#bdbdbd"/>
          <circle cx="352" cy="256" r="10" fill="#212121"/>
          <circle cx="352" cy="256" r="5"  fill="#616161"/>
          <circle cx="352" cy="256" r="2"  fill="#bdbdbd"/>
          <!-- Door -->
          <rect x="372" y="228" width="10" height="24" rx="1" fill="#c62828"/>
          <!-- Bumper -->
          <rect x="370" y="252" width="8" height="3" rx="1" fill="#9e9e9e"/>
          <!-- Headlight -->
          <circle cx="376" cy="237" r="3" fill="#fff9c4"/>
        </g>

        <!-- ── Floating Pencils (image 1 style) ── -->
        <!-- Pencil 1 - red -->
        <g class="float1" transform="translate(32,155) rotate(-15)">
          <rect x="0" y="0" width="7" height="52" rx="2" fill="#ef5350"/>
          <rect x="0" y="0" width="7" height="8"  rx="2" fill="#ffcdd2"/>
          <polygon points="0,52 7,52 3.5,62" fill="#ffc8a2"/>
          <polygon points="1,58 6,58 3.5,62" fill="#5d4037"/>
          <rect x="0" y="46" width="7" height="3" fill="#fdd835"/>
        </g>
        <!-- Pencil 2 - green -->
        <g class="float2" transform="translate(355,158) rotate(12)">
          <rect x="0" y="0" width="7" height="52" rx="2" fill="#43a047"/>
          <rect x="0" y="0" width="7" height="8"  rx="2" fill="#c8e6c9"/>
          <polygon points="0,52 7,52 3.5,62" fill="#ffc8a2"/>
          <polygon points="1,58 6,58 3.5,62" fill="#5d4037"/>
          <rect x="0" y="46" width="7" height="3" fill="#fdd835"/>
        </g>
        <!-- Pencil 3 - blue (floating mid) -->
        <g class="float3" transform="translate(18,195) rotate(-8)">
          <rect x="0" y="0" width="6" height="44" rx="2" fill="#1e88e5"/>
          <rect x="0" y="0" width="6" height="7"  rx="2" fill="#bbdefb"/>
          <polygon points="0,44 6,44 3,53" fill="#ffc8a2"/>
          <polygon points="1,50 5,50 3,53" fill="#5d4037"/>
          <rect x="0" y="39" width="6" height="3" fill="#e91e63"/>
        </g>
        <!-- Pencil 4 - orange (right side) -->
        <g class="float1" transform="translate(374,195) rotate(10)" style="animation-delay:.5s">
          <rect x="0" y="0" width="6" height="44" rx="2" fill="#fb8c00"/>
          <rect x="0" y="0" width="6" height="7"  rx="2" fill="#ffe0b2"/>
          <polygon points="0,44 6,44 3,53" fill="#ffc8a2"/>
          <polygon points="1,50 5,50 3,53" fill="#5d4037"/>
          <rect x="0" y="39" width="6" height="3" fill="#ab47bc"/>
        </g>

        <!-- ── Apple ── -->
        <g class="float2" transform="translate(152, 138)" style="animation-delay:.3s">
          <ellipse cx="12" cy="16" rx="12" ry="14" fill="#e53935"/>
          <ellipse cx="12" cy="16" rx="12" ry="14" fill="none" stroke="#c62828" stroke-width="1"/>
          <ellipse cx="8" cy="12" rx="4" ry="6" fill="#ef9a9a" opacity="0.5"/>
          <path d="M12 4 Q14 -2 18 0" stroke="#388e3c" stroke-width="2" fill="none" stroke-linecap="round"/>
          <ellipse cx="20" cy="-1" rx="5" ry="3" fill="#43a047" transform="rotate(-20 20 -1)"/>
        </g>

        <!-- ── Notebook (right of student) ── -->
        <g class="float3" transform="translate(320,145)" style="animation-delay:.6s">
          <rect x="0" y="0" width="28" height="36" rx="3" fill="#1565c0"/>
          <rect x="3" y="0" width="25" height="36" rx="3" fill="#1976d2"/>
          <rect x="0" y="0" width="5" height="36" rx="2" fill="#0d47a1"/>
          <!-- Spiral rings -->
          <circle cx="2" cy="6"  r="2.5" fill="none" stroke="#90caf9" stroke-width="1.2"/>
          <circle cx="2" cy="14" r="2.5" fill="none" stroke="#90caf9" stroke-width="1.2"/>
          <circle cx="2" cy="22" r="2.5" fill="none" stroke="#90caf9" stroke-width="1.2"/>
          <circle cx="2" cy="30" r="2.5" fill="none" stroke="#90caf9" stroke-width="1.2"/>
          <!-- Lines -->
          <line x1="8" y1="10" x2="24" y2="10" stroke="rgba(255,255,255,0.3)" stroke-width="1"/>
          <line x1="8" y1="16" x2="24" y2="16" stroke="rgba(255,255,255,0.3)" stroke-width="1"/>
          <line x1="8" y1="22" x2="24" y2="22" stroke="rgba(255,255,255,0.3)" stroke-width="1"/>
          <line x1="8" y1="28" x2="24" y2="28" stroke="rgba(255,255,255,0.3)" stroke-width="1"/>
        </g>

        <!-- ── Ruler ── -->
        <g class="float1" transform="translate(45,235) rotate(-5)" style="animation-delay:.9s">
          <rect x="0" y="0" width="55" height="9" rx="2" fill="#fdd835" stroke="#f9a825" stroke-width="1"/>
          <line x1="8"  y1="0" x2="8"  y2="5" stroke="#333" stroke-width="0.8"/>
          <line x1="14" y1="0" x2="14" y2="4" stroke="#333" stroke-width="0.8"/>
          <line x1="20" y1="0" x2="20" y2="5" stroke="#333" stroke-width="0.8"/>
          <line x1="26" y1="0" x2="26" y2="4" stroke="#333" stroke-width="0.8"/>
          <line x1="32" y1="0" x2="32" y2="5" stroke="#333" stroke-width="0.8"/>
          <line x1="38" y1="0" x2="38" y2="4" stroke="#333" stroke-width="0.8"/>
          <line x1="44" y1="0" x2="44" y2="5" stroke="#333" stroke-width="0.8"/>
        </g>

        <!-- ── Confetti dots (image 3 style) ── -->
        <circle cx="155" cy="110" r="4" fill="#ef5350" class="confetti" />
        <rect x="170" cy="95"  width="6" height="6" rx="1" fill="#1e88e5" transform="rotate(20 170 95)" class="confetti" style="animation-delay:.3s"/>
        <polygon points="350,110 354,120 346,120" fill="#fdd835" class="confetti" style="animation-delay:.6s"/>
        <circle cx="280" cy="100" r="3" fill="#43a047" class="confetti" style="animation-delay:.9s"/>
        <rect x="130" y="145" width="5" height="5" rx="1" fill="#ab47bc" transform="rotate(-15 130 145)" class="confetti" style="animation-delay:.4s"/>
        <circle cx="360" cy="140" r="4" fill="#ef5350" class="confetti" style="animation-delay:.7s"/>
        <polygon points="30,140 34,150 26,150" fill="#1e88e5" class="confetti" style="animation-delay:.2s"/>

        <!-- ── Bottom brand bar (inside SVG) ── -->
        <rect x="0" y="310" width="400" height="130" fill="#0a2240"/>
        <!-- Subtle chalk lines in footer -->
        <line x1="0" y1="314" x2="400" y2="314" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
        <!-- Brand text -->
        <text x="130" y="348" font-size="26" font-weight="900"
              font-family="Arial,sans-serif" letter-spacing="2" fill="#fcd116">Shule</text>
        <text x="206" y="348" font-size="26" font-weight="900"
              font-family="Arial,sans-serif" letter-spacing="2" fill="white">Pay</text>
        <!-- Tagline -->
        <text x="200" y="370" text-anchor="middle" font-size="10" fill="rgba(255,255,255,0.45)"
              font-family="Arial,sans-serif" letter-spacing="1.5">MFUMO WA FEDHA ZA SHULE</text>
        <!-- Loading text -->
        <text x="200" y="395" text-anchor="middle" font-size="11" fill="rgba(255,255,255,0.55)"
              font-family="Arial,sans-serif">Inapakia data ya shule...</text>
        <!-- Progress track -->
        <rect x="100" y="408" width="200" height="4" rx="2" fill="rgba(255,255,255,0.1)"/>
        <rect x="100" y="408" width="80"  height="4" rx="2" fill="url(#ribbon)" class="progress-bar"/>
      </svg>
    </div>
  </div>
</template>

<style scoped>
.loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
}

.scene-card {
  width: min(400px, 96vw);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 40px 100px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.07);
}

.scene-svg { display: block; width: 100%; }

/* ── Clouds ── */
.cloud-1 { animation: drift1 14s ease-in-out infinite alternate; }
.cloud-2 { animation: drift1 18s ease-in-out infinite alternate-reverse; }
@keyframes drift1 { from{transform:translateX(0)} to{transform:translateX(20px)} }

/* ── Stars ── */
.star-twinkle { animation: twinkle 2s ease-in-out infinite; }
@keyframes twinkle { 0%,100%{opacity:1} 50%{opacity:0.3} }

/* ── Student walk ── */
.student-walk { animation: student-bob 1.6s ease-in-out infinite; }
@keyframes student-bob {
  0%,100%{ transform:translate(190px,110px) }
  50%    { transform:translate(190px,107px) }
}
/* Leg swing on right leg -->  */
.leg-r { animation: leg-swing .5s ease-in-out infinite alternate; transform-origin: 26px 112px; }
@keyframes leg-swing { from{transform:rotate(-10deg)} to{transform:rotate(10deg)} }

/* ── Stationery floats ── */
.float1 { animation: flt 2.4s ease-in-out infinite; }
.float2 { animation: flt 3s ease-in-out infinite; }
.float3 { animation: flt 2.8s ease-in-out infinite; }
@keyframes flt {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-8px) }
}
/* Float with rotation for items with rotate() already applied */
.float1[transform*="rotate"] { animation-name: flt-r1; }
.float2[transform*="rotate"] { animation-name: flt-r2; }
.float3[transform*="rotate"] { animation-name: flt-r3; }
@keyframes flt-r1 {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-8px) }
}
@keyframes flt-r2 {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-7px) }
}
@keyframes flt-r3 {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-6px) }
}

/* ── Bus ── */
.bus-move { animation: bus 6s ease-in-out infinite; }
@keyframes bus {
  0%,100%{ transform:translateX(0) }
  50%    { transform:translateX(-8px) }
}

/* ── Confetti ── */
.confetti { animation: conf 2.5s ease-in-out infinite; }
@keyframes conf {
  0%,100%{ transform:translateY(0) rotate(0deg); opacity:.9 }
  50%    { transform:translateY(-10px) rotate(20deg); opacity:1 }
}

/* ── Progress bar ── */
.progress-bar { animation: prog 2s linear infinite; transform-origin: left center; }
@keyframes prog {
  0%  { transform:translateX(-120px) }
  100%{ transform:translateX(320px)  }
}
</style>

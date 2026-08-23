<template>
  <div class="loading-overlay">
    <div class="scene-wrap">

      <!-- Sky + clouds -->
      <div class="sky">
        <div class="cloud cloud-1"></div>
        <div class="cloud cloud-2"></div>
        <div class="sun"></div>
      </div>

      <!-- School building -->
      <div class="building">
        <div class="building-roof"></div>
        <div class="building-body">
          <div class="building-flag-pole"><div class="building-flag"></div></div>
          <div class="building-windows">
            <div class="win"></div>
            <div class="win"></div>
            <div class="win"></div>
          </div>
          <div class="building-door"></div>
          <div class="building-sign">SHULE</div>
        </div>
      </div>

      <!-- Ground -->
      <div class="ground">
        <div class="grass"></div>

        <!-- Tree left -->
        <div class="tree tree-l">
          <div class="tree-top"></div>
          <div class="trunk"></div>
        </div>

        <!-- Tree right -->
        <div class="tree tree-r">
          <div class="tree-top"></div>
          <div class="trunk"></div>
        </div>

        <!-- Teacher figure -->
        <div class="person teacher">
          <div class="head"></div>
          <div class="body teacher-body">
            <div class="book-held"></div>
          </div>
          <div class="legs">
            <div class="leg"></div><div class="leg"></div>
          </div>
        </div>

        <!-- Student 1 -->
        <div class="person student s1">
          <div class="head s-head"></div>
          <div class="body student-body">
            <div class="backpack"></div>
          </div>
          <div class="legs">
            <div class="leg anim-walk"></div><div class="leg anim-walk-r"></div>
          </div>
        </div>

        <!-- Student 2 -->
        <div class="person student s2">
          <div class="head s-head"></div>
          <div class="body student-body s2-body"></div>
          <div class="legs">
            <div class="leg anim-walk-r"></div><div class="leg anim-walk"></div>
          </div>
        </div>

        <!-- Floating items -->
        <div class="float-item pen"></div>
        <div class="float-item book bk1"></div>
        <div class="float-item book bk2"></div>
        <div class="float-item pencil"></div>
      </div>

      <!-- Bottom bar -->
      <div class="bottom-bar">
        <div class="brand">
          <span class="brand-s">Shule</span><span class="brand-p">Pay</span>
        </div>
        <div class="loading-msg">Inapakia data ya shule...</div>
        <div class="progress-track"><div class="progress-fill"></div></div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* ── Overlay ─────────────────────────────────────────── */
.loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0f172a;
}

.scene-wrap {
  width: 380px;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 40px 100px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.06);
  background: #fff;
  display: flex;
  flex-direction: column;
}

/* ── Sky ─────────────────────────────────────────────── */
.sky {
  position: relative;
  height: 90px;
  background: linear-gradient(180deg, #bfdbfe 0%, #eff6ff 100%);
  overflow: hidden;
}

.sun {
  position: absolute;
  top: 14px; right: 28px;
  width: 36px; height: 36px;
  border-radius: 50%;
  background: radial-gradient(circle, #fef08a 40%, #fde68a 70%, transparent 100%);
  box-shadow: 0 0 18px 6px rgba(253,224,71,0.4);
  animation: sun-pulse 3s ease-in-out infinite;
}
@keyframes sun-pulse {
  0%,100%{ box-shadow: 0 0 18px 6px rgba(253,224,71,0.4); }
  50%    { box-shadow: 0 0 28px 12px rgba(253,224,71,0.25); }
}

.cloud {
  position: absolute;
  border-radius: 50px;
  background: white;
  opacity: 0.9;
}
.cloud::before, .cloud::after {
  content:''; position: absolute;
  background: white; border-radius: 50%;
}
.cloud-1 {
  width: 70px; height: 22px;
  top: 18px; left: 30px;
  animation: drift 12s linear infinite;
}
.cloud-1::before { width:28px;height:28px; top:-14px; left:10px; }
.cloud-1::after  { width:20px;height:20px; top:-10px; left:32px; }
.cloud-2 {
  width: 50px; height: 16px;
  top: 30px; left: 160px;
  animation: drift 18s linear infinite;
  opacity: 0.7;
}
.cloud-2::before { width:22px;height:22px; top:-11px; left:8px; }
.cloud-2::after  { width:16px;height:16px; top:-8px;  left:24px; }
@keyframes drift {
  from { transform: translateX(0); }
  to   { transform: translateX(40px); }
}

/* ── Building ────────────────────────────────────────── */
.building {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: -10px;
  z-index: 2;
  position: relative;
}
.building-roof {
  width: 0;
  height: 0;
  border-left: 70px solid transparent;
  border-right: 70px solid transparent;
  border-bottom: 44px solid #1d4ed8;
  filter: drop-shadow(0 -2px 4px rgba(29,78,216,0.3));
}
.building-body {
  width: 140px;
  background: linear-gradient(180deg, #dbeafe 0%, #eff6ff 100%);
  border: 2px solid #bfdbfe;
  border-top: none;
  border-radius: 0 0 6px 6px;
  padding: 6px 8px 8px;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.building-flag-pole {
  position: absolute;
  top: -52px; left: 50%; transform: translateX(-50%);
  width: 2px; height: 28px;
  background: #94a3b8;
}
.building-flag {
  position: absolute;
  top: 0; left: 2px;
  width: 18px; height: 11px;
  background: linear-gradient(90deg, #ef4444, #f97316);
  clip-path: polygon(0 0, 100% 40%, 0 80%);
  animation: flag-wave 1.5s ease-in-out infinite;
  transform-origin: left center;
}
@keyframes flag-wave {
  0%,100%{ transform: scaleX(1); }
  50%    { transform: scaleX(0.8) skewY(5deg); }
}
.building-windows {
  display: flex; gap: 8px;
}
.win {
  width: 26px; height: 20px;
  background: linear-gradient(135deg, #bfdbfe, #93c5fd);
  border: 1.5px solid #60a5fa;
  border-radius: 3px 3px 0 0;
  position: relative;
}
.win::after {
  content:''; position:absolute;
  top:2px; left:2px;
  width:8px; height:6px;
  background: rgba(255,255,255,0.5);
  border-radius: 2px;
}
.building-door {
  width: 24px; height: 28px;
  background: linear-gradient(180deg,#1d4ed8,#1e40af);
  border-radius: 4px 4px 0 0;
  border: 1.5px solid #1e40af;
}
.building-sign {
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 2px;
  color: #1d4ed8;
  margin-top: 2px;
}

/* ── Ground ──────────────────────────────────────────── */
.ground {
  position: relative;
  height: 130px;
  background: #f0fdf4;
  overflow: hidden;
}
.grass {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 18px;
  background: linear-gradient(180deg, #4ade80 0%, #22c55e 100%);
  border-radius: 40% 40% 0 0;
}

/* ── Trees ───────────────────────────────────────────── */
.tree {
  position: absolute;
  bottom: 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.tree-l { left: 12px; }
.tree-r { right: 12px; }
.tree-top {
  width: 32px; height: 42px;
  background: linear-gradient(180deg, #16a34a, #15803d);
  clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
}
.trunk {
  width: 8px; height: 14px;
  background: #92400e;
  border-radius: 2px;
}

/* ── People ──────────────────────────────────────────── */
.person {
  position: absolute;
  bottom: 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.teacher { left: 55px; }
.student.s1 { left: 130px; animation: walk 1.8s ease-in-out infinite; }
.student.s2 { left: 195px; animation: walk 1.8s ease-in-out infinite 0.3s; }

@keyframes walk {
  0%,100%{ transform: translateY(0); }
  50%    { transform: translateY(-3px); }
}

.head {
  width: 18px; height: 18px;
  border-radius: 50%;
  background: #fcd34d;
  border: 2px solid #f59e0b;
  margin-bottom: 1px;
}
.s-head {
  width: 15px; height: 15px;
  border-color: #f59e0b;
}
.body {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}
.teacher-body {
  width: 22px; height: 28px;
  background: #1d4ed8;
  position: relative;
}
.student-body {
  width: 18px; height: 22px;
  background: #16a34a;
  border-radius: 3px;
  position: relative;
}
.s2-body { background: #dc2626; }

.backpack {
  position: absolute;
  right: -7px; top: 2px;
  width: 9px; height: 13px;
  background: #7c3aed;
  border-radius: 2px;
  border: 1px solid #6d28d9;
}

.book-held {
  position: absolute;
  left: -8px; top: 6px;
  width: 10px; height: 13px;
  background: #ef4444;
  border-radius: 1px;
  border-left: 2px solid #b91c1c;
}

.legs {
  display: flex; gap: 4px;
}
.leg {
  width: 6px; height: 14px;
  background: #374151;
  border-radius: 0 0 3px 3px;
}
.anim-walk   { animation: leg-a 0.5s ease-in-out infinite alternate; }
.anim-walk-r { animation: leg-a 0.5s ease-in-out infinite alternate-reverse; }
@keyframes leg-a {
  from { transform: rotate(-10deg); }
  to   { transform: rotate(10deg); }
}

/* ── Floating items ──────────────────────────────────── */
.float-item {
  position: absolute;
  animation: float-up 2.5s ease-in-out infinite;
}
.pen {
  bottom: 55px; left: 90px;
  width: 4px; height: 22px;
  background: linear-gradient(180deg, #fbbf24, #f59e0b);
  border-radius: 2px;
  animation-delay: 0s;
}
.pen::after {
  content:''; position:absolute;
  bottom:-4px; left:-1px;
  width:6px; height:5px;
  background: #f87171;
  clip-path: polygon(50% 100%, 0 0, 100% 0);
}
.pencil {
  bottom: 60px; right: 80px;
  width: 4px; height: 20px;
  background: linear-gradient(180deg, #818cf8, #6366f1);
  border-radius: 2px;
  transform: rotate(15deg);
  animation-delay: 0.7s;
}
.book {
  width: 18px; height: 14px;
  border-radius: 1px 3px 3px 1px;
  border-left: 3px solid rgba(0,0,0,0.2);
}
.bk1 {
  bottom: 70px; left: 260px;
  background: #f97316;
  animation-delay: 0.4s;
}
.bk2 {
  bottom: 52px; left: 288px;
  background: #06b6d4;
  animation-delay: 1.1s;
  transform: rotate(-8deg);
}
@keyframes float-up {
  0%,100%{ transform: translateY(0) rotate(0deg);   opacity:0.9; }
  50%    { transform: translateY(-8px) rotate(5deg); opacity:1;   }
}
.bk2 { animation-name: float-tilt; }
@keyframes float-tilt {
  0%,100%{ transform: translateY(0) rotate(-8deg);   opacity:0.9; }
  50%    { transform: translateY(-7px) rotate(-3deg); opacity:1;   }
}

/* ── Bottom bar ──────────────────────────────────────── */
.bottom-bar {
  background: #1e293b;
  padding: 14px 20px 18px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.brand {
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: 1px;
}
.brand-s { color: #38bdf8; }
.brand-p { color: #ffffff; }

.loading-msg {
  font-size: 0.78rem;
  color: rgba(255,255,255,0.5);
  letter-spacing: 0.5px;
}

.progress-track {
  width: 160px;
  height: 3px;
  background: rgba(255,255,255,0.1);
  border-radius: 10px;
  overflow: hidden;
  margin-top: 2px;
}
.progress-fill {
  height: 100%;
  width: 45%;
  background: linear-gradient(90deg, #38bdf8, #818cf8);
  border-radius: 10px;
  animation: slide 1.6s ease-in-out infinite;
}
@keyframes slide {
  0%  { transform: translateX(-120%); }
  100%{ transform: translateX(320%); }
}
</style>

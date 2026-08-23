<template>
  <div class="loading-overlay">
    <div class="scene-card">

      <!-- Sky -->
      <div class="sky">
        <div class="sun"></div>
        <div class="cloud c1"></div>
        <div class="cloud c2"></div>
      </div>

      <!-- School building -->
      <div class="building-wrap">
        <div class="roof"></div>
        <div class="facade">
          <div class="flag-pole"><div class="flag"></div></div>
          <div class="sign-board">
            <span>SHULE</span>
          </div>
          <div class="windows-row">
            <div class="win"></div>
            <div class="win"></div>
            <div class="win"></div>
          </div>
          <div class="door"></div>
        </div>
      </div>

      <!-- Ground scene -->
      <div class="ground">
        <!-- Path -->
        <div class="path"></div>
        <!-- Trees -->
        <div class="tree tl"><div class="leaves"></div><div class="trunk"></div></div>
        <div class="tree tr"><div class="leaves"></div><div class="trunk"></div></div>
        <!-- Teacher -->
        <div class="figure teacher">
          <div class="fig-head"></div>
          <div class="fig-body t-shirt">
            <div class="t-book"></div>
          </div>
          <div class="fig-legs"><div class="fig-leg"></div><div class="fig-leg"></div></div>
        </div>
        <!-- Student A -->
        <div class="figure stu-a">
          <div class="fig-head"></div>
          <div class="fig-body s-shirt"></div>
          <div class="fig-legs walk">
            <div class="fig-leg la"></div><div class="fig-leg lb"></div>
          </div>
          <div class="s-bag"></div>
        </div>
        <!-- Student B -->
        <div class="figure stu-b">
          <div class="fig-head"></div>
          <div class="fig-body s-shirt sb2"></div>
          <div class="fig-legs walk">
            <div class="fig-leg lb"></div><div class="fig-leg la"></div>
          </div>
        </div>
        <!-- Floating stationery -->
        <div class="stat pen-a"></div>
        <div class="stat pen-b"></div>
        <div class="stat nb-a"></div>
        <div class="stat nb-b"></div>
        <div class="stat ruler"></div>
      </div>

      <!-- Grass strip -->
      <div class="grass"></div>

      <!-- Bottom brand bar -->
      <div class="footer-bar">
        <div class="footer-brand">
          <span class="fb-shule">Shule</span><span class="fb-pay">Pay</span>
        </div>
        <div class="footer-msg">Inapakia data ya shule...</div>
        <div class="footer-bar-track"><div class="footer-bar-fill"></div></div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* === Palette ===
   Navy:   #0a2240
   Green:  #007f3e  /  dark #0d3d26
   Gold:   #fcd116
   Sky:    #d1f0ff → #eaf9f0
*/

.loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: #06150e;
  display: flex;
  align-items: center;
  justify-content: center;
}

.scene-card {
  width: 360px;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 40px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.06);
  display: flex;
  flex-direction: column;
}

/* ── Sky ─── */
.sky {
  height: 84px;
  background: linear-gradient(180deg, #c9edff 0%, #e8fbf0 100%);
  position: relative;
  overflow: hidden;
}
.sun {
  position: absolute;
  top: 12px; right: 26px;
  width: 34px; height: 34px;
  border-radius: 50%;
  background: radial-gradient(circle, #fef08a 30%, #fde68a 65%, transparent 100%);
  box-shadow: 0 0 20px 8px rgba(252,209,22,0.35);
  animation: sun-glow 3s ease-in-out infinite;
}
@keyframes sun-glow {
  0%,100%{ box-shadow: 0 0 20px 8px rgba(252,209,22,0.35); }
  50%    { box-shadow: 0 0 34px 14px rgba(252,209,22,0.2); }
}
.cloud {
  position: absolute;
  background: #fff;
  border-radius: 40px;
  opacity: 0.85;
}
.cloud::before,.cloud::after {
  content:''; position:absolute;
  background:#fff; border-radius:50%;
}
.c1 { width:64px;height:20px;top:20px;left:22px; animation:drift 14s linear infinite; }
.c1::before{width:26px;height:26px;top:-13px;left:8px;}
.c1::after {width:18px;height:18px;top:-9px;left:28px;}
.c2 { width:46px;height:14px;top:36px;left:140px; animation:drift 20s linear infinite; opacity:.65; }
.c2::before{width:18px;height:18px;top:-9px;left:7px;}
.c2::after {width:14px;height:14px;top:-7px;left:22px;}
@keyframes drift { to{transform:translateX(30px)} }

/* ── Building ─── */
.building-wrap {
  display:flex; flex-direction:column; align-items:center;
  background: linear-gradient(180deg, #e8fbf0 0%, #ccf0d8 100%);
  padding-bottom: 0;
  position: relative;
  z-index: 2;
}
.roof {
  width:0; height:0;
  border-left:72px solid transparent;
  border-right:72px solid transparent;
  border-bottom:46px solid #0a2240;
}
.facade {
  width:144px;
  background: linear-gradient(180deg,#f0faf4,#e2f4ea);
  border:2px solid #b6e4c8;
  border-top:none;
  border-radius:0 0 6px 6px;
  display:flex; flex-direction:column; align-items:center;
  padding:4px 8px 8px;
  position:relative; gap:3px;
}
.flag-pole {
  position:absolute; top:-52px; left:50%; transform:translateX(-50%);
  width:2px; height:30px; background:#8fb3a0;
}
.flag {
  position:absolute; top:0; left:2px;
  width:20px; height:12px;
  background: linear-gradient(90deg,#007f3e,#00a34f);
  clip-path:polygon(0 0,100% 40%,0 80%);
  animation:flag-flap 1.6s ease-in-out infinite; transform-origin:left center;
}
@keyframes flag-flap {
  0%,100%{transform:scaleX(1) skewY(0);}
  50%{transform:scaleX(.82) skewY(6deg);}
}
.sign-board {
  background:#fcd116; border-radius:3px; padding:1px 10px;
  font-size:7px; font-weight:800; letter-spacing:2.5px; color:#0a2240;
}
.windows-row { display:flex; gap:8px; }
.win {
  width:28px; height:20px;
  background:linear-gradient(135deg,#a8d8f0,#7ec8e3);
  border:1.5px solid #0a2240; border-radius:3px 3px 0 0;
  position:relative;
}
.win::after {
  content:''; position:absolute; top:2px; left:2px;
  width:9px; height:6px;
  background:rgba(255,255,255,0.55); border-radius:2px;
}
.door {
  width:26px; height:30px;
  background:linear-gradient(180deg,#0a2240,#0d2f57);
  border-radius:4px 4px 0 0;
  border:1.5px solid #071a30;
}

/* ── Ground ─── */
.ground {
  height:120px;
  background:#e8fdf0;
  position:relative; overflow:hidden;
}
.path {
  position:absolute; bottom:0; left:50%; transform:translateX(-50%);
  width:48px; height:100%;
  background:linear-gradient(180deg,rgba(210,185,140,0.3),rgba(210,185,140,0.7));
  border-radius:0;
}
.grass {
  height:18px;
  background:linear-gradient(180deg,#007f3e,#0d3d26);
}

/* ── Trees ─── */
.tree { position:absolute; bottom:14px; display:flex; flex-direction:column; align-items:center; }
.tl { left:10px; }
.tr { right:10px; }
.leaves {
  width:34px; height:46px;
  background:linear-gradient(180deg,#00a34f,#007f3e);
  clip-path:polygon(50% 0%,0% 100%,100% 100%);
  filter:drop-shadow(0 2px 3px rgba(0,0,0,0.15));
}
.trunk { width:9px; height:15px; background:#5c3d11; border-radius:2px; }

/* ── Figures ─── */
.figure { position:absolute; bottom:14px; display:flex; flex-direction:column; align-items:center; }
.teacher{ left:52px; }
.stu-a  { left:130px; animation:bob 1.8s ease-in-out infinite; }
.stu-b  { left:196px; animation:bob 1.8s ease-in-out infinite .3s; }
@keyframes bob { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-3px)} }

.fig-head {
  width:18px; height:18px; border-radius:50%;
  background:#f5c97a; border:2px solid #d4952a;
  margin-bottom:1px;
}
.fig-body {
  width:20px; height:26px; border-radius:4px;
  display:flex; align-items:center; justify-content:center;
  position:relative;
}
.t-shirt  { background:#0a2240; }   /* Teacher — navy */
.s-shirt  { background:#007f3e; }   /* Student — green uniform */
.sb2      { background:#005c2d; }   /* Slightly darker green */

.t-book {
  position:absolute; left:-9px; top:5px;
  width:10px; height:13px;
  background:#fcd116; border-radius:1px;
  border-left:2px solid #b89c00;
}
.s-bag {
  position:absolute; right:-9px; top:0;
  width:10px; height:13px;
  background:#fcd116; border-radius:2px;
  border:1.5px solid #b89c00;
  margin-top:19px;
}

.fig-legs { display:flex; gap:4px; }
.fig-leg {
  width:7px; height:15px;
  background:#0a2240; border-radius:0 0 3px 3px;
}
.la { animation:leg-swing .5s ease-in-out infinite alternate; }
.lb { animation:leg-swing .5s ease-in-out infinite alternate-reverse; }
@keyframes leg-swing {
  from{transform:rotate(-11deg)} to{transform:rotate(11deg)}
}

/* ── Stationery floaters ─── */
.stat { position:absolute; }
/* Pen A */
.pen-a {
  width:4px; height:22px; bottom:58px; left:88px;
  background:linear-gradient(180deg,#fcd116,#b89c00);
  border-radius:2px;
  animation:float1 2.4s ease-in-out infinite;
}
.pen-a::after {
  content:''; position:absolute; bottom:-4px; left:-1px;
  width:6px; height:5px; background:#c0392b;
  clip-path:polygon(50% 100%,0 0,100% 0);
}
/* Pen B — pencil shape */
.pen-b {
  width:4px; height:18px; bottom:65px; right:72px;
  background:linear-gradient(180deg,#fcd116,#e6b800);
  border-radius:2px; transform:rotate(18deg);
  animation:float1 2.2s ease-in-out infinite .6s;
}
.pen-b::after {
  content:''; position:absolute; bottom:-4px; left:-1px;
  width:6px; height:5px; background:#888;
  clip-path:polygon(50% 100%,0 0,100% 0);
}
/* Notebook A */
.nb-a {
  width:20px; height:15px; bottom:72px; left:248px;
  background:#007f3e; border-radius:1px 3px 3px 1px;
  border-left:3px solid #005c2d;
  animation:float2 2.8s ease-in-out infinite .2s;
}
.nb-a::after {
  content:''; position:absolute;
  top:3px; left:4px; right:2px; height:1.5px;
  background:rgba(255,255,255,0.4); border-radius:1px;
  box-shadow:0 4px 0 rgba(255,255,255,0.3);
}
/* Notebook B */
.nb-b {
  width:18px; height:14px; bottom:55px; right:56px;
  background:#0a2240; border-radius:1px 3px 3px 1px;
  border-left:3px solid #061628;
  animation:float2 2.6s ease-in-out infinite .9s;
  transform:rotate(-10deg);
}
/* Ruler */
.ruler {
  width:34px; height:6px; bottom:78px; left:60px;
  background:linear-gradient(90deg,#fcd116 0%,#fcd116 48%,#0a2240 48%,#0a2240 52%,#fcd116 52%);
  border-radius:2px; border:1px solid #b89c00;
  animation:float1 3s ease-in-out infinite .4s;
  transform:rotate(-5deg);
}

@keyframes float1 {
  0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)}
}
@keyframes float2 {
  0%,100%{transform:translateY(0) rotate(-10deg)} 50%{transform:translateY(-7px) rotate(-5deg)}
}

/* ── Footer bar ─── */
.footer-bar {
  background:#0a2240;
  padding:12px 20px 16px;
  display:flex; flex-direction:column; align-items:center; gap:5px;
}
.footer-brand { font-size:1.4rem; font-weight:900; letter-spacing:1px; }
.fb-shule { color:#fcd116; }
.fb-pay   { color:#ffffff; }
.footer-msg {
  font-size:0.76rem; color:rgba(255,255,255,0.45); letter-spacing:.6px;
}
.footer-bar-track {
  width:150px; height:3px;
  background:rgba(255,255,255,0.1);
  border-radius:10px; overflow:hidden; margin-top:2px;
}
.footer-bar-fill {
  height:100%; width:40%;
  background:linear-gradient(90deg,#fcd116,#00a34f);
  border-radius:10px;
  animation:bar-slide 1.8s ease-in-out infinite;
}
@keyframes bar-slide {
  0%  {transform:translateX(-140%)}
  100%{transform:translateX(320%)}
}
</style>

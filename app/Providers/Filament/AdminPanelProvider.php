<?php

namespace App\Providers\Filament;

use App\Filament\Pages\LabaRugi;
use App\Filament\Pages\NeracaPage;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Pages\TreeAkunPage;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ============================================================
        // TEMA LOGIN:  'terang' (default)  atau  'gelap'
        // ============================================================
        $tema = 'terang';

        $terang = $tema === 'terang';

        // ============================================================
        // LOGO (akuntansi memakai logo Wijaya)
        // ============================================================
        $logo = file_exists(public_path('images/logo-wijaya.png'))
            ? 'images/logo-wijaya.png'
            : 'images/logo-wijaya.webp';
        $logoUrl = asset($logo);
        $logoRgb = $terang ? '15,23,42' : '255,255,255';

        // ------------------------------------------------------------
        // PALET WARNA PER TEMA
        // ------------------------------------------------------------
        $svgColors = $terang
            ? ['{{A}}' => '#f59e0b', '{{B}}' => '#3b82f6', '{{BAR}}' => '#3b82f6', '{{NB}}' => '#2563eb', '{{INK}}' => '#0f172a']
            : ['{{A}}' => '#fbbf24', '{{B}}' => '#60a5fa', '{{BAR}}' => '#93c5fd', '{{NB}}' => '#93c5fd', '{{INK}}' => '#ffffff'];

        $vars = $terang
            ? '--bg: linear-gradient(135deg, #f8fafc 0%, #eef2ff 55%, #fff6e3 100%);
               --ink: #0f172a;
               --card-bg: rgba(255,255,255,0.78);
               --card-border: rgba(15,23,42,0.08);
               --card-shadow: 0 30px 60px -20px rgba(15,23,42,0.28), inset 0 1px 0 rgba(255,255,255,0.9);
               --text: #0f172a;
               --muted: #64748b;
               --input-bg: #f8fafc;
               --input-ring: #cbd5e1;
               --link: #b45309;
               --link-hover: #92400e;
               --icon: #64748b;
               --check-bg: #ffffff;
               --check-border: #94a3b8;
               --error: #dc2626;
               --logo-shadow: drop-shadow(0 2px 6px rgba(15,23,42,0.15));'
            : '--bg: linear-gradient(135deg, #0a1020 0%, #101c36 55%, #0d1527 100%);
               --ink: #ffffff;
               --card-bg: rgba(12,20,40,0.52);
               --card-border: rgba(255,255,255,0.16);
               --card-shadow: 0 30px 60px -15px rgba(0,0,0,0.65), inset 0 1px 0 rgba(255,255,255,0.20);
               --text: #ffffff;
               --muted: rgba(255,255,255,0.75);
               --input-bg: rgba(255,255,255,0.10);
               --input-ring: rgba(255,255,255,0.22);
               --link: #fbbf24;
               --link-hover: #fde68a;
               --icon: rgba(255,255,255,0.8);
               --check-bg: rgba(255,255,255,0.15);
               --check-border: rgba(255,255,255,0.4);
               --error: #fca5a5;
               --logo-shadow: drop-shadow(0 4px 12px rgba(0,0,0,0.45));';

        // ------------------------------------------------------------
        // LATAR ABSTRAK (SVG) - untuk layar lebar
        // ------------------------------------------------------------
        $svg = <<<'HTML'
<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <radialGradient id="gAmber" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="{{A}}" stop-opacity=".38"/>
      <stop offset="100%" stop-color="{{A}}" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="gBlue" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#3b82f6" stop-opacity=".34"/>
      <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="gLine1" x1="0" x2="1" y1="0" y2="0">
      <stop offset="0%" stop-color="{{A}}" stop-opacity="0"/>
      <stop offset="45%" stop-color="{{A}}" stop-opacity=".75"/>
      <stop offset="100%" stop-color="{{A}}" stop-opacity=".1"/>
    </linearGradient>
    <linearGradient id="gLine2" x1="0" x2="1" y1="0" y2="0">
      <stop offset="0%" stop-color="{{B}}" stop-opacity="0"/>
      <stop offset="55%" stop-color="{{B}}" stop-opacity=".6"/>
      <stop offset="100%" stop-color="{{B}}" stop-opacity=".05"/>
    </linearGradient>
    <linearGradient id="gFill" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0%" stop-color="{{A}}" stop-opacity=".16"/>
      <stop offset="100%" stop-color="{{A}}" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="gBar" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0%" stop-color="{{BAR}}" stop-opacity=".30"/>
      <stop offset="100%" stop-color="{{BAR}}" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="gBarA" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0%" stop-color="{{A}}" stop-opacity=".34"/>
      <stop offset="100%" stop-color="{{A}}" stop-opacity="0"/>
    </linearGradient>
  </defs>

  <!-- cahaya lembut -->
  <circle cx="170" cy="120" r="420" fill="url(#gAmber)"/>
  <circle cx="1480" cy="820" r="480" fill="url(#gBlue)"/>
  <circle cx="1350" cy="90" r="260" fill="url(#gBlue)" opacity=".55"/>

  <!-- cincin abstrak -->
  <g fill="none" stroke="{{INK}}" stroke-opacity=".07">
    <circle cx="1380" cy="170" r="150"/>
    <circle cx="1380" cy="170" r="230" stroke-dasharray="3 12"/>
    <circle cx="1380" cy="170" r="320" stroke-opacity=".04"/>
    <circle cx="220" cy="760" r="120"/>
    <circle cx="220" cy="760" r="200" stroke-dasharray="3 12"/>
  </g>

  <!-- batang transparan -->
  <g>
    <rect x="70"  y="640" width="38" height="260" rx="8" fill="url(#gBar)"/>
    <rect x="124" y="580" width="38" height="320" rx="8" fill="url(#gBarA)"/>
    <rect x="178" y="690" width="38" height="210" rx="8" fill="url(#gBar)"/>
    <rect x="232" y="620" width="38" height="280" rx="8" fill="url(#gBar)"/>
    <rect x="1230" y="560" width="38" height="340" rx="8" fill="url(#gBar)"/>
    <rect x="1284" y="480" width="38" height="420" rx="8" fill="url(#gBarA)"/>
    <rect x="1338" y="610" width="38" height="290" rx="8" fill="url(#gBar)"/>
    <rect x="1392" y="530" width="38" height="370" rx="8" fill="url(#gBar)"/>
    <rect x="1446" y="440" width="38" height="460" rx="8" fill="url(#gBarA)"/>
  </g>

  <!-- area di bawah gelombang -->
  <path d="M-100 650 C 200 520, 400 760, 700 600 S 1200 380, 1700 480 L1700 900 L-100 900 Z" fill="url(#gFill)"/>

  <!-- gelombang mengalir -->
  <g fill="none" stroke-linecap="round">
    <path class="flow f1" d="M-100 650 C 200 520, 400 760, 700 600 S 1200 380, 1700 480" stroke="url(#gLine1)" stroke-width="3"/>
    <path class="flow f2" d="M-100 720 C 250 640, 500 820, 850 690 S 1300 520, 1700 590" stroke="url(#gLine2)" stroke-width="2.2"/>
    <path class="flow f3" d="M-100 560 C 300 430, 560 640, 900 500 S 1350 300, 1700 380" stroke="url(#gLine2)" stroke-width="1.6" stroke-opacity=".7"/>
    <path class="flow f4" d="M-100 790 C 300 730, 620 860, 980 770 S 1400 640, 1700 700" stroke="url(#gLine1)" stroke-width="1.4" stroke-opacity=".6"/>
  </g>

  <!-- jaringan titik -->
  <g stroke="{{INK}}" stroke-opacity=".10" stroke-width="1" fill="none">
    <polyline points="260,250 420,170 600,260 780,150 960,230 1130,140"/>
    <polyline points="420,170 470,340 600,260"/>
    <polyline points="780,150 840,320 960,230"/>
    <polyline points="1130,140 1210,300 1330,250"/>
  </g>
  <g fill="{{A}}">
    <circle cx="260" cy="250" r="4" opacity=".55"/><circle cx="420" cy="170" r="5" opacity=".7"/>
    <circle cx="600" cy="260" r="4" opacity=".5"/><circle cx="780" cy="150" r="5" opacity=".75"/>
    <circle cx="960" cy="230" r="4" opacity=".5"/><circle cx="1130" cy="140" r="5" opacity=".7"/>
  </g>
  <g fill="{{NB}}">
    <circle cx="470" cy="340" r="3.5" opacity=".5"/><circle cx="840" cy="320" r="3.5" opacity=".5"/>
    <circle cx="1210" cy="300" r="3.5" opacity=".55"/><circle cx="1330" cy="250" r="4" opacity=".55"/>
  </g>

  <!-- ===== ELEMEN AKUNTANSI + RUMUS EXCEL SAMAR (disembunyikan di HP) ===== -->
  <g class="acc" font-family="ui-monospace, 'SFMono-Regular', Consolas, 'Courier New', monospace" fill="{{INK}}">

    <!-- rumus excel -->
    <text class="fl a" x="70"   y="70"  font-size="30" fill-opacity=".14">=SUM(D2:D40)</text>
    <text class="fl c" x="1090" y="215" font-size="24" fill-opacity=".12">=VLOOKUP(A2;Akun;3;0)</text>
    <text class="fl b" x="1075" y="335" font-size="22" fill-opacity=".12">=SUMIF(B:B;&quot;Kas&quot;;D:D)</text>
    <text class="fl a" x="1040" y="640" font-size="22" fill-opacity=".12">=ROUND(F12*11%;0)</text>
    <text class="fl c" x="300"  y="730" font-size="22" fill-opacity=".12">=IF(D5&gt;E5;1;0)</text>
    <text class="fl b" x="300"  y="410" font-size="24" fill-opacity=".12">=D10-E10</text>

    <!-- rumus akuntansi -->
    <text class="fl b" x="980"  y="62"  font-size="26" fill-opacity=".12">Laba = Pendapatan − Beban</text>
    <text class="fl c" x="560"  y="868" font-size="28" fill-opacity=".11">Debit = Kredit</text>

    <!-- simbol besar -->
    <text class="fl b" x="95"   y="330" font-size="150" fill-opacity=".045" font-weight="700">Σ</text>
    <text class="fl a" x="1440" y="360" font-size="110" fill-opacity=".05" font-weight="700">%</text>
    <text class="fl c" x="760"  y="120" font-size="90"  fill-opacity=".04" font-weight="700">=</text>

    <!-- angka rupiah -->
    <text class="fl a" x="64"   y="470" font-size="26" fill-opacity=".12">1.250.000</text>
    <text class="fl c" x="1160" y="430" font-size="26" fill-opacity=".12">48.750.000</text>
    <text class="fl b" x="1010" y="860" font-size="24" fill-opacity=".11">Rp 12.500.000</text>
    <text class="fl a" x="300"  y="560" font-size="22" fill-opacity=".10">+12,4%</text>
    <text class="fl c" x="1330" y="500" font-size="22" fill-opacity=".14" fill="{{A}}">0,00</text>

    <!-- mini buku besar: kolom Debit / Kredit -->
    <g font-size="17">
      <text class="fl b" x="64"  y="540" fill="{{A}}"   fill-opacity=".22" letter-spacing="3">DEBIT</text>
      <text class="fl b" x="170" y="540" fill="{{NB}}"  fill-opacity=".22" letter-spacing="3">KREDIT</text>
    </g>
    <g stroke="{{INK}}" stroke-opacity=".10" stroke-width="1">
      <line x1="64" y1="552" x2="270" y2="552"/>
      <line x1="150" y1="530" x2="150" y2="640"/>
      <line x1="64" y1="580" x2="270" y2="580" stroke-opacity=".06"/>
      <line x1="64" y1="608" x2="270" y2="608" stroke-opacity=".06"/>
      <line x1="64" y1="636" x2="270" y2="636" stroke-opacity=".06"/>
    </g>
    <g font-size="16" fill-opacity=".10">
      <text x="72"  y="573">500.000</text>
      <text x="160" y="601">500.000</text>
      <text x="72"  y="629">1.250.000</text>
    </g>

    <!-- koin Rp -->
    <g class="fl a">
      <circle cx="700" cy="60" r="30" fill="none" stroke="{{A}}" stroke-opacity=".30" stroke-width="2"/>
      <circle cx="700" cy="60" r="23" fill="none" stroke="{{A}}" stroke-opacity=".16" stroke-width="1"/>
      <text x="700" y="67" font-size="19" text-anchor="middle" fill="{{A}}" fill-opacity=".38" font-weight="700">Rp</text>
    </g>
    <g class="fl c">
      <circle cx="1520" cy="760" r="34" fill="none" stroke="{{A}}" stroke-opacity=".28" stroke-width="2"/>
      <circle cx="1520" cy="760" r="26" fill="none" stroke="{{A}}" stroke-opacity=".16" stroke-width="1"/>
      <text x="1520" y="768" font-size="21" text-anchor="middle" fill="{{A}}" fill-opacity=".36" font-weight="700">Rp</text>
    </g>
    <g class="fl b">
      <circle cx="420" cy="860" r="26" fill="none" stroke="{{NB}}" stroke-opacity=".26" stroke-width="2"/>
      <text x="420" y="867" font-size="17" text-anchor="middle" fill="{{NB}}" fill-opacity=".34" font-weight="700">Rp</text>
    </g>
  </g>
</svg>
HTML;
        $svg = strtr($svg, $svgColors);

        // ------------------------------------------------------------
        // LAPISAN TEKS UNTUK HP (ukuran mengikuti lebar layar)
        // ------------------------------------------------------------
        $mobile = <<<'HTML'
<div class="abs-mob" aria-hidden="true">
  <span class="m t1">=SUM(D2:D40)</span>
  <span class="m t2">1.250.000</span>
  <span class="m sig">Σ</span>
  <span class="m coin">Rp</span>
  <span class="m t3">Laba = Pendapatan − Beban</span>
  <span class="m t4">Debit = Kredit</span>
  <span class="m t5">=ROUND(F12*11%;0)</span>
  <span class="m t6">=IF(D5&gt;E5;1;0)</span>
  <span class="m t7">=VLOOKUP(A2;Akun;3;0)</span>
</div>
HTML;

        $abstractBg = '<div class="abs-bg" aria-hidden="true">' . $svg . '</div>' . $mobile;

        // ------------------------------------------------------------
        // CSS
        // ------------------------------------------------------------
        $css = '.fi-simple-layout { ' . $vars . ' }' . <<<'CSS'

/* ===== LATAR ===== */
.fi-simple-layout {
    background: var(--bg) !important;
    min-height: 100vh;
    min-height: 100dvh;
}
.abs-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
}
.abs-bg svg { width: 100%; height: 100%; display: block; }
.fi-simple-main-ctn { position: relative; z-index: 1; }

/* animasi pelan */
.abs-bg .flow { stroke-dasharray: 14 10; animation: flowmove 40s linear infinite; }
.abs-bg .f2 { animation-duration: 55s; animation-direction: reverse; }
.abs-bg .f3 { animation-duration: 70s; }
.abs-bg .f4 { animation-duration: 48s; animation-direction: reverse; }
@keyframes flowmove { to { stroke-dashoffset: -600; } }
.abs-bg .fl { animation: floaty 16s ease-in-out infinite alternate; }
.abs-bg .fl.b { animation-duration: 21s; animation-delay: -6s; }
.abs-bg .fl.c { animation-duration: 26s; animation-delay: -11s; }
@keyframes floaty { from { transform: translateY(0); } to { transform: translateY(-12px); } }
@media (prefers-reduced-motion: reduce) {
    .abs-bg .flow { animation: none; stroke-dasharray: none; }
    .abs-bg .fl { animation: none; }
}

/* ===== LAPISAN HP (disembunyikan di layar lebar) ===== */
.abs-mob { display: none; }

/* ===== CARD ===== */
.fi-simple-main {
    background: var(--card-bg) !important;
    -webkit-backdrop-filter: blur(22px) saturate(160%);
    backdrop-filter: blur(22px) saturate(160%);
    border: 1px solid var(--card-border) !important;
    border-radius: 1.75rem !important;
    box-shadow: var(--card-shadow) !important;
    --tw-ring-shadow: 0 0 #0000 !important;
    --tw-ring-color: transparent !important;
    padding-top: 2.5rem !important;
    padding-bottom: 2.5rem !important;
}

/* ===== LOGO ===== */
.login-brand-logo { display: flex; justify-content: center; margin: 0 0 1rem 0; }
.login-brand-logo img {
    display: block;
    height: 6rem;
    width: auto;
    max-width: 85%;
    object-fit: contain;
    filter: var(--logo-shadow);
}
.fi-simple-main .fi-logo { display: none !important; }

/* Header */
.fi-simple-main .fi-simple-header {
    margin-top: 0 !important;
    padding-top: 0 !important;
    margin-bottom: 1rem !important;
}
.fi-simple-main .fi-simple-header-heading { margin-top: 0 !important; }

/* ===== TEKS ===== */
.fi-simple-main .fi-simple-header-heading,
.fi-simple-main h1,
.fi-simple-main label,
.fi-simple-main label span,
.fi-simple-main .fi-fo-field-wrp-label,
.fi-simple-main .fi-fo-field-wrp-label span {
    color: var(--text) !important;
}
.fi-simple-main .fi-simple-header-subheading,
.fi-simple-main .fi-simple-header-subheading span {
    color: var(--muted) !important;
}
.fi-simple-main a { color: var(--link) !important; }
.fi-simple-main a:hover { color: var(--link-hover) !important; }

/* ===== INPUT ===== */
.fi-simple-main .fi-input-wrp {
    background-color: var(--input-bg) !important;
    border-radius: 0.75rem !important;
    box-shadow: inset 0 0 0 1px var(--input-ring) !important;
    transition: all 0.2s ease;
}
.fi-simple-main .fi-input-wrp:focus-within {
    box-shadow: inset 0 0 0 2px #f59e0b, 0 0 0 4px rgba(251, 191, 36, 0.25) !important;
}
.fi-simple-main input.fi-input {
    color: var(--text) !important;
    -webkit-text-fill-color: var(--text) !important;
    background: transparent !important;
}
.fi-simple-main input.fi-input::placeholder { color: var(--muted) !important; }
.fi-simple-main input:-webkit-autofill {
    -webkit-text-fill-color: var(--text) !important;
    transition: background-color 9999s ease-in-out 0s;
}
.fi-simple-main .fi-input-wrp button,
.fi-simple-main .fi-input-wrp svg { color: var(--icon) !important; }

/* ===== CHECKBOX ===== */
.fi-simple-main input[type="checkbox"] {
    background-color: var(--check-bg) !important;
    border-color: var(--check-border) !important;
}

/* ===== TOMBOL ===== */
.fi-simple-main .fi-btn,
.fi-simple-main button[type="submit"] {
    background-color: #fbbf24 !important;
    color: #1f2937 !important;
    border-radius: 0.75rem !important;
    font-weight: 700;
    box-shadow: 0 10px 25px -8px rgba(245, 158, 11, 0.6);
    transition: transform .15s ease, box-shadow .15s ease, background-color .2s ease;
}
.fi-simple-main .fi-btn:hover,
.fi-simple-main button[type="submit"]:hover {
    background-color: #fcd34d !important;
    transform: translateY(-2px);
    box-shadow: 0 14px 30px -8px rgba(245, 158, 11, 0.8);
}

/* ===== ERROR ===== */
.fi-simple-main .fi-fo-field-wrp-error-message { color: var(--error) !important; }

/* ============================================================
   RESPONSIVE (HP / layar sempit)
   ============================================================ */
@media (max-width: 768px) {
    .abs-bg .acc { display: none; }
    .abs-mob { display: block; position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; color: var(--ink); font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Courier New', monospace; }
    .abs-mob .m { position: absolute; white-space: nowrap; }
    .abs-mob .t1 { top: 3.5%;  left: 6%;  font-size: clamp(12px, 3.6vw, 17px); opacity: .15; }
    .abs-mob .t2 { top: 8.5%;  right: 7%; font-size: clamp(11px, 3.1vw, 15px); opacity: .12; }
    .abs-mob .sig { top: 13%; left: 7%; font-size: 24vw; font-weight: 700; opacity: .05; line-height: 1; }
    .abs-mob .coin {
        top: 12%; right: 9%; width: 16vw; height: 16vw; max-width: 90px; max-height: 90px;
        border: 2px solid #f59e0b; border-radius: 50%; opacity: .32;
        display: flex; align-items: center; justify-content: center;
        font-size: 5vw; font-weight: 700; color: #f59e0b;
    }
    .abs-mob .t3 { bottom: 13%; right: 6%; font-size: clamp(11px, 3.1vw, 15px); opacity: .13; }
    .abs-mob .t4 { bottom: 7.5%; left: 6%; font-size: clamp(12px, 3.6vw, 17px); opacity: .13; }
    .abs-mob .t5 { bottom: 3%;  right: 6%; font-size: clamp(11px, 3vw, 14px); opacity: .13; }
    .abs-mob .t6 { bottom: 3%;  left: 6%;  font-size: clamp(11px, 3vw, 14px); opacity: .13; color: #f59e0b; }
    .abs-mob .t7 { top: 19.5%; left: 6%; font-size: clamp(10px, 2.9vw, 13px); opacity: .11; }

    .fi-simple-main-ctn { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
    .fi-simple-main {
        padding: 1.75rem 1.25rem !important;
        border-radius: 1.4rem !important;
    }
    .login-brand-logo img { height: 4.75rem; }
    .fi-simple-main .fi-simple-header-heading { font-size: 1.35rem !important; }
}
@media (max-width: 380px) {
    .fi-simple-main { padding: 1.5rem 1rem !important; }
    .login-brand-logo img { height: 4.25rem; }
}
CSS;

        // ------------------------------------------------------------
        // JS: logo ke atas "Sign in", latar putih dihapus, warna sesuai tema, auto-crop
        // ------------------------------------------------------------
        $js = <<<'JS'
(function () {
    function processLogo(img) {
        if (!img || img.dataset.done) return;
        img.dataset.done = "1";
        var rgb = (img.dataset.rgb || "255,255,255").split(",").map(Number);
        function run() {
            try {
                var scale = Math.min(1, 800 / img.naturalWidth);
                var w = Math.round(img.naturalWidth * scale);
                var h = Math.round(img.naturalHeight * scale);
                var c = document.createElement("canvas");
                c.width = w; c.height = h;
                var ctx = c.getContext("2d");
                ctx.drawImage(img, 0, 0, w, h);
                var data = ctx.getImageData(0, 0, w, h);
                var p = data.data;
                var minX = w, minY = h, maxX = 0, maxY = 0;
                for (var y = 0; y < h; y++) {
                    for (var x = 0; x < w; x++) {
                        var i = (y * w + x) * 4;
                        var lum = p[i] * 0.299 + p[i + 1] * 0.587 + p[i + 2] * 0.114;
                        var a = Math.max(0, Math.min(255, (255 - lum) * 1.6));
                        a = a * (p[i + 3] / 255);
                        p[i] = rgb[0]; p[i + 1] = rgb[1]; p[i + 2] = rgb[2]; p[i + 3] = a;
                        if (a > 40) {
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                        }
                    }
                }
                ctx.putImageData(data, 0, 0);
                if (maxX > minX && maxY > minY) {
                    var pad = 6;
                    minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
                    maxX = Math.min(w - 1, maxX + pad); maxY = Math.min(h - 1, maxY + pad);
                    var cw = maxX - minX + 1, ch = maxY - minY + 1;
                    var c2 = document.createElement("canvas");
                    c2.width = cw; c2.height = ch;
                    c2.getContext("2d").drawImage(c, minX, minY, cw, ch, 0, 0, cw, ch);
                    img.src = c2.toDataURL("image/png");
                } else {
                    img.src = c.toDataURL("image/png");
                }
            } catch (e) {
                console.warn("Logo tidak bisa diproses", e);
            }
        }
        if (img.complete && img.naturalWidth) run();
        else img.addEventListener("load", run, { once: true });
    }

    function init() {
        var logo = document.querySelector(".login-brand-logo");
        var heading = document.querySelector(".fi-simple-main h1, .fi-simple-header-heading");
        if (logo && heading) {
            var target = heading.closest(".fi-simple-header") || heading.parentElement;
            if (target && target.parentNode && logo.nextElementSibling !== target) {
                target.parentNode.insertBefore(logo, target);
            }
            processLogo(logo.querySelector("img"));
        }
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
    else init();
    document.addEventListener("livewire:navigated", init);
})();
JS;

        $logoHtml = '<div class="login-brand-logo"><img data-rgb="' . $logoRgb . '" src="' . $logoUrl . '" alt="Logo Wijaya"></div>';

        return $panel
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->default()
            ->id('admin')
            ->path('admin')
            ->theme('resources/css/filament/admin/theme.css')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName('Akuntansi')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                NeracaPage::class,
                TreeAkunPage::class,
                LabaRugi::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])

            // Logo di dalam form login
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => $logoHtml
            )

            // Latar abstrak (hanya untuk tamu / halaman login)
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => auth()->check() ? '' : $abstractBg
            )

            // Script logo
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => '<script>' . $js . '</script>'
            )

            // Style login
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<style>' . $css . '</style>'
            )

            ->navigationGroups([

                NavigationGroup::make('Master')
                    ->icon('heroicon-o-circle-stack')
                    ->collapsed(true),

                NavigationGroup::make('Jurnal')
                    ->icon('heroicon-o-book-open')
                    ->collapsed(true),
            ])
            ->sidebarCollapsibleOnDesktop()
        ;
    }
}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $lang === 'en' ? 'TELF · Fire Monitoring' : 'TELF · Pemantauan Kebakaran' }}</title>

        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔥</text></svg>">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous" defer></script>

        <style>
            html, body { height: 100%; }
            body { -webkit-font-smoothing: antialiased; }
            #map { height: 100%; min-height: 360px; background: #F3F4F6; }
            .leaflet-container { background: #F3F4F6; font-family: 'JetBrains Mono', monospace; outline: none; }
            .leaflet-bar a { background: #F8FAFC; color: #111827; border-color: rgba(15,23,42,.12); }
            .leaflet-bar a:hover { background: #E5E7EB; color: #0F172A; }

            .leaflet-tooltip.telf-tip {
                font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: .02em;
                background: #F8FAFC; color: #111827; border: 1px solid rgba(15,23,42,.12);
                border-radius: 4px; box-shadow: 0 6px 20px rgba(15,23,42,.08); padding: 7px 10px; white-space: nowrap;
            }
            .leaflet-tooltip.telf-tip::before { display: none; }

            .bnd { cursor: pointer; transition: fill-opacity .14s ease, color .14s ease; }

            .rail { scrollbar-width: thin; scrollbar-color: rgba(148,163,184,.4) transparent; }
            .rail::-webkit-scrollbar { width: 7px; height: 7px; }
            .rail::-webkit-scrollbar-track { background: transparent; }
            .rail::-webkit-scrollbar-thumb { background: rgba(148,163,184,.4); border-radius: 4px; }

            /* Ember readout: the fire count text is clean and subtle. */
            .ember { text-shadow: none; }

            /* Scan-line used during the year play animation. */
            #scan { background: rgba(148,163,184,.35); box-shadow: none; }

            .tl-tick { transition: color .15s ease; }
            .tl-dot { transition: background .15s ease, box-shadow .15s ease; }
            .tl-dot.dot-active { background: #D8501E; box-shadow: 0 0 0 3px rgba(216,80,30,.22); }

            :focus-visible { outline: 2px solid #93C5FD; outline-offset: 2px; border-radius: 3px; }
            .leaflet-container :focus-visible { outline: none; }

            .leaflet-popup.telf-luc-popup .leaflet-popup-content-wrapper {
                background: #F8FAFC; color: #111827; border: 1px solid rgba(15,23,42,.12);
                border-radius: 6px; box-shadow: 0 10px 28px rgba(15,23,42,.14);
            }
            .leaflet-popup.telf-luc-popup .leaflet-popup-content {
                margin: 10px 12px; font-family: 'JetBrains Mono', monospace; font-size: 10px; line-height: 1.55; min-width: 210px;
            }
            .leaflet-popup.telf-luc-popup .leaflet-popup-tip { background: #F8FAFC; }
            .leaflet-popup.telf-luc-popup a.leaflet-popup-close-button { color: #64748B; }
            .telf-luc-title { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: #475569; margin-bottom: 6px; padding-right: 14px; }
            .telf-luc-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 1px 0; }
            .telf-luc-label { color: #64748B; }
            .telf-luc-value { color: #0F172A; text-align: right; }
            .telf-luc-note { margin-top: 7px; border-top: 1px solid rgba(15,23,42,.10); padding-top: 7px; color: #64748B; font-size: 9px; line-height: 1.5; }

            @media (prefers-reduced-motion: reduce) {
                * { transition: none !important; animation: none !important; }
            }
        </style>
    </head>
    <body class="bg-[#F7F7F5] text-slate-900 font-inter antialiased">
        <div class="flex flex-col lg:h-screen min-h-screen">

            {{-- Top bar — instrument strip --}}
            <header class="shrink-0 border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between gap-4 px-5 py-2.5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-slate-100 ring-1 ring-slate-200 flex items-center justify-center shrink-0 overflow-hidden">
                            <img src="{{ asset('img/LOGOFIRE.png') }}" alt="TELF Logo" class="w-full h-full object-cover">
                        </div>
                        <div class="leading-tight min-w-0">
                            <div class="font-fraunces text-[15px] font-medium text-slate-900 truncate">TELF</div>
                            <div class="font-jetbrains-mono text-[9px] uppercase tracking-[0.2em] text-slate-500 -mt-0.5 truncate">{{ $lang === 'en' ? 'Forest Fire · Sumatra' : 'Kebakaran Hutan · Sumatera' }}</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <div class="grid grid-cols-2 gap-0.5 border border-slate-200 rounded-full p-0.5 bg-slate-50">
                            <a href="{{ url()->current() }}?lang=id" class="font-jetbrains-mono text-[10px] uppercase tracking-[0.14em] rounded-full px-2.5 py-1 transition-colors {{ $lang === 'id' ? 'text-slate-900 bg-slate-200' : 'text-slate-500 hover:text-slate-900' }}">ID</a>
                            <a href="{{ url()->current() }}?lang=en" class="font-jetbrains-mono text-[10px] uppercase tracking-[0.14em] rounded-full px-2.5 py-1 transition-colors {{ $lang === 'en' ? 'text-slate-900 bg-slate-200' : 'text-slate-500 hover:text-slate-900' }}">EN</a>
                        </div>
                        <a href="{{ route('home') }}" class="font-jetbrains-mono text-[10px] uppercase tracking-[0.18em] text-slate-500 hover:text-slate-900 transition-colors border border-slate-200 hover:border-slate-300 rounded-full px-3 py-1.5 shrink-0">{{ $lang === 'en' ? 'Home' : 'Beranda' }}</a>
                    </div>
                </div>
            </header>

            {{-- Filter strip: breadcrumb · search · count · reset --}}
            <div class="shrink-0 border-b border-slate-200 bg-white px-5 py-2 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex min-w-0 items-center gap-3 flex-1 flex-wrap">
                    <div id="breadcrumb" class="flex items-center gap-2 font-jetbrains-mono text-[11px] overflow-x-auto whitespace-nowrap rail min-w-0 text-slate-500"></div>
                    <div class="relative min-w-[220px] max-w-[340px] w-full lg:w-auto">
                        <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 shadow-sm focus-within:border-slate-300">
                            <svg viewBox="0 0 20 20" class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="8.5" cy="8.5" r="5.5"></circle>
                                <path d="m13.5 13.5 4 4"></path>
                            </svg>
                            <input id="place-search" type="search" placeholder="{{ $lang === 'en' ? 'Search region' : 'Cari wilayah' }}" class="w-full bg-transparent text-[11px] placeholder:text-slate-400 outline-none text-slate-700" autocomplete="off">
                        </div>
                        <div id="search-results" class="hidden absolute left-0 right-0 z-50 mt-1 max-h-64 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-lg"></div>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0 flex-wrap">
                    <span class="w-px h-5 bg-slate-200"></span>
                    <span id="footer-count" class="font-jetbrains-mono text-[11px] text-slate-500 tabular-nums">0 {{ $lang === 'en' ? 'points' : 'titik' }}</span>
                    <button id="f-reset" type="button" class="font-jetbrains-mono text-[10px] uppercase tracking-[0.16em] text-slate-500 hover:text-slate-900 border border-slate-200 hover:border-slate-300 rounded-full px-3 py-1 transition-colors">{{ $lang === 'en' ? 'Reset' : 'Atur ulang' }}</button>
                </div>
            </div>

            {{-- Main: light map plate + clean readout rail --}}
            <main class="flex-1 grid grid-cols-1 lg:grid-cols-[1fr_320px] lg:grid-rows-[minmax(0,1fr)] min-h-0">

                {{-- Map plate — light field --}}
                <section class="relative bg-[#F8F9FB] min-h-[60vh] lg:min-h-0">
                    <div id="map" class="absolute inset-0"></div>

                    {{-- Instrument rulers (quiet) --}}
                    <div class="absolute top-0 bottom-7 left-0 w-2.5 pointer-events-none z-[450]" style="background:repeating-linear-gradient(to bottom, rgba(15,23,42,.08) 0 1px, transparent 1px 9px)"></div>
                    <div class="absolute left-0 right-0 bottom-7 h-2.5 pointer-events-none z-[450]" style="background:repeating-linear-gradient(to right, rgba(15,23,42,.08) 0 1px, transparent 1px 9px)"></div>

                    {{-- Scan-line for the year play animation --}}
                    <div id="scan" class="absolute top-0 bottom-7 w-px hidden pointer-events-none z-[465]" style="left:0px"></div>

                    {{-- DN spectrum legend (clickable tier toggle) --}}
                    <div class="absolute top-3 right-3 z-[455] flex flex-col items-end gap-2 select-none w-80 max-w-[calc(100vw-1.5rem)]">
                        <div id="map-legend" class="w-48 max-w-full">
                            <div class="rounded-lg border border-slate-200 bg-white/90 backdrop-blur-sm px-3 py-2">
                                <div class="font-jetbrains-mono text-[9px] uppercase tracking-[0.22em] text-slate-500 mb-1.5">{{ $lang === 'en' ? 'Intensity' : 'Intensitas' }}</div>
                                <div class="flex flex-col gap-1">
                                    <button type="button" id="map-leg-3" aria-pressed="true" data-tier="3" title="{{ $lang === 'en' ? 'Toggle high' : 'Tampilkan/sembunyikan tinggi' }}" class="flex items-center gap-2 text-left w-full rounded px-1 py-0.5 hover:bg-slate-100 transition-colors">
                                        <span class="w-3 h-3 rounded-[2px] shrink-0" style="background:#C84A26"></span>
                                        <span class="font-jetbrains-mono text-[9px] text-slate-600">{{ $lang === 'en' ? 'High' : 'Tinggi' }}</span>
                                        <span class="font-jetbrains-mono text-[8px] text-slate-400 ml-auto">DN ≥25</span>
                                    </button>
                                    <button type="button" id="map-leg-2" aria-pressed="true" data-tier="2" title="{{ $lang === 'en' ? 'Toggle moderate' : 'Tampilkan/sembunyikan sedang' }}" class="flex items-center gap-2 text-left w-full rounded px-1 py-0.5 hover:bg-slate-100 transition-colors">
                                        <span class="w-3 h-3 rounded-[2px] shrink-0" style="background:#E8A93A"></span>
                                        <span class="font-jetbrains-mono text-[9px] text-slate-600">{{ $lang === 'en' ? 'Moderate' : 'Sedang' }}</span>
                                        <span class="font-jetbrains-mono text-[8px] text-slate-400 ml-auto">DN 13–21</span>
                                    </button>
                                    <button type="button" id="map-leg-1" aria-pressed="true" data-tier="1" title="{{ $lang === 'en' ? 'Toggle low' : 'Tampilkan/sembunyikan rendah' }}" class="flex items-center gap-2 text-left w-full rounded px-1 py-0.5 hover:bg-slate-100 transition-colors">
                                        <span class="w-3 h-3 rounded-[2px] shrink-0" style="background:#E8C547"></span>
                                        <span class="font-jetbrains-mono text-[9px] text-slate-600">{{ $lang === 'en' ? 'Low' : 'Rendah' }}</span>
                                        <span class="font-jetbrains-mono text-[8px] text-slate-400 ml-auto">DN ≤9</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="luc-filter-widget" class="w-48 max-w-full">
                            <button id="luc-filter-trigger" type="button" aria-expanded="false" aria-controls="luc-filter-panel" class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white/90 px-3 py-2 text-left text-slate-600 shadow-sm backdrop-blur-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1C3A14]/40">
                                <span class="font-jetbrains-mono text-[9px] uppercase tracking-[0.22em]">{{ $lang === 'en' ? 'Land Cover' : 'Tutupan Lahan' }}</span>
                                <svg id="luc-filter-chevron" viewBox="0 0 16 16" class="h-3 w-3 shrink-0 text-slate-400 transition-transform" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 6 4 4 4-4"></path></svg>
                            </button>
                            <div id="luc-filter-panel" class="hidden mt-1 max-h-[55vh] overflow-y-auto rounded-lg border border-slate-200 bg-white/95 p-2 shadow-md backdrop-blur-sm">
                                <div id="luc-section" data-luc-loaded="false" data-luc-points-loaded="false">
                                    <div class="mb-1 flex items-center justify-between gap-2">
                                        <span class="font-jetbrains-mono text-[9px] uppercase tracking-[0.2em] text-slate-500">{{ $lang === 'en' ? 'Observation year' : 'Tahun pengamatan' }}</span>
                                        <span id="luc-year-chip" class="font-jetbrains-mono text-[9px] text-slate-500"></span>
                                    </div>
                                    <div class="mb-1 grid grid-cols-5 gap-1" role="tablist" aria-label="{{ $lang === 'en' ? 'Land cover year' : 'Tahun tutupan lahan' }}">
                                        <button id="luc-year-2020" type="button" data-luc-year="2020" role="tab" aria-selected="true" class="rounded-md bg-[#1C3A14] px-1 py-1 font-jetbrains-mono text-[9px] text-white transition-colors">2020</button>
                                        <button id="luc-year-2021" type="button" data-luc-year="2021" role="tab" aria-selected="false" class="rounded-md px-1 py-1 font-jetbrains-mono text-[9px] text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">2021</button>
                                        <button id="luc-year-2022" type="button" data-luc-year="2022" role="tab" aria-selected="false" class="rounded-md px-1 py-1 font-jetbrains-mono text-[9px] text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">2022</button>
                                        <button id="luc-year-2023" type="button" data-luc-year="2023" role="tab" aria-selected="false" class="rounded-md px-1 py-1 font-jetbrains-mono text-[9px] text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">2023</button>
                                        <button id="luc-year-2024" type="button" data-luc-year="2024" role="tab" aria-selected="false" class="rounded-md px-1 py-1 font-jetbrains-mono text-[9px] text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">2024</button>
                                    </div>
                                    <label for="luc-points-toggle" class="mb-1 flex items-center gap-2 rounded-md border border-slate-200 px-2 py-1.5 text-[10px] text-slate-600 transition-colors hover:bg-slate-50">
                                        <input id="luc-points-toggle" type="checkbox" autocomplete="off" class="h-3 w-3 shrink-0 accent-[#D8501E]">
                                        <span id="luc-points-label">{{ $lang === 'en' ? 'Show rings at fire points' : 'Tampilkan cincin pada titik kebakaran' }}</span>
                                    </label>
                                    <div class="mb-1">
                                        <button id="luc-all-categories" type="button" aria-pressed="true" class="w-full rounded border border-[#1C3A14] bg-[#F1F5F1] px-1.5 py-1 text-[9px] font-medium text-[#1C3A14] transition-colors hover:bg-slate-50">{{ $lang === 'en' ? 'All categories' : 'Semua Kategori' }}</button>
                                    </div>
                                    <div id="luc-legend" class="hidden grid grid-cols-1 gap-1.5"></div>
                                </div>
                            </div>
                        </div>
                        <div id="map-style-dd" class="relative w-48 max-w-full">
                            <button type="button" id="map-style-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="{{ $lang === 'en' ? 'Map theme' : 'Tema peta' }}" class="flex items-center gap-2 justify-between w-full rounded-full bg-white border border-slate-200 text-slate-700 shadow-sm px-3 py-2 hover:bg-slate-50 transition-colors">
                                <span id="map-style-icon" class="leading-none" aria-hidden="true">☀</span>
                                <span id="map-style-label" class="font-jetbrains-mono text-[10px] uppercase tracking-[0.14em]">{{ $lang === 'en' ? 'Light' : 'Terang' }}</span>
                                <svg viewBox="0 0 16 16" class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 6 4 4 4-4"></path></svg>
                            </button>
                            <div id="map-style-menu" role="listbox" aria-label="{{ $lang === 'en' ? 'Map theme' : 'Tema peta' }}" class="hidden absolute left-0 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg py-1">
                                <button type="button" role="option" aria-selected="true" data-map-style="light" class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors"><span class="leading-none" aria-hidden="true">☀</span><span>{{ $lang === 'en' ? 'Light' : 'Terang' }}</span></button>
                                <button type="button" role="option" aria-selected="false" data-map-style="dark" class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors"><span class="leading-none" aria-hidden="true">🌑</span><span>{{ $lang === 'en' ? 'Dark' : 'Gelap' }}</span></button>
                                <button type="button" role="option" aria-selected="false" data-map-style="sat" class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors"><span class="leading-none" aria-hidden="true">🛰</span><span>{{ $lang === 'en' ? 'Satellite' : 'Satelit' }}</span></button>
                            </div>
                        </div>
                    </div>

                    <div id="loading" class="absolute inset-0 z-[470] flex items-center justify-center pointer-events-none">
                        <span class="font-jetbrains-mono text-[10px] uppercase tracking-[0.22em] text-slate-700 bg-white/90 px-3 py-1.5 rounded-full border border-slate-200">{{ $lang === 'en' ? 'loading data…' : 'memuat data…' }}</span>
                    </div>

                    {{-- Timeline — year slider --}}
                    <div id="timeline" class="absolute bottom-0 left-0 right-0 z-[460] h-7 bg-white/90 backdrop-blur-sm border-t border-slate-200 flex items-center gap-2 px-3">
                        <button id="tl-play" type="button" aria-label="{{ $lang === 'en' ? 'Play year animation' : 'Putar animasi tahun' }}" class="shrink-0 w-5 h-5 rounded-full border border-slate-300 text-slate-500 hover:text-slate-900 hover:border-slate-400 transition-colors flex items-center justify-center text-[9px] leading-none">▶</button>
                        <div id="tl-track" class="relative flex-1 h-5 cursor-pointer touch-none select-none">
                            <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-px bg-slate-300"></div>
                            <div id="tl-dots" class="absolute inset-0"></div>
                            <div id="tl-handle" class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full bg-slate-400 shadow-[0_0_0_3px_rgba(148,163,184,0.22)]" style="left:10px"></div>
                        </div>
                        <span id="tl-year" class="font-fraunces text-sm text-slate-900 shrink-0 w-12 text-right tabular-nums">2024</span>
                        <span class="font-jetbrains-mono text-[8px] text-slate-400 shrink-0 hidden md:block">© Google</span>
                    </div>
                </section>

                {{-- Readout rail — telemetry --}}
                <aside class="rail border-t lg:border-t-0 lg:border-l border-slate-200 bg-white overflow-y-auto min-h-0">
                    <div class="p-5 space-y-7">
                        <div id="dl-dd" class="relative">
                            <button type="button" id="dl-trigger" aria-haspopup="listbox" aria-expanded="false" class="flex items-center justify-between w-full gap-2 rounded-xl border border-slate-200 bg-white text-slate-700 px-3 py-2 hover:bg-slate-50 transition-colors text-[11px]">
                                <span class="flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 1.5v9"/><path d="m4.5 7 3.5 3.5L11.5 7"/><path d="M2.5 11.5v2h11v-2"/></svg>
                                    <span class="font-jetbrains-mono uppercase tracking-[0.14em]">{{ $lang === 'en' ? 'Download Data' : 'Unduh Data' }}</span>
                                </span>
                                <svg viewBox="0 0 16 16" class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 6 4 4 4-4"></path></svg>
                            </button>
                            <div id="dl-menu" role="listbox" aria-label="{{ $lang === 'en' ? 'Download data format' : 'Format unduh data' }}" class="hidden absolute left-0 right-0 mt-1 rounded-xl border border-slate-200 bg-white shadow-lg py-1 z-10">
                                <button type="button" role="option" data-dl="pie" class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="8" cy="8" r="6" stroke="currentColor"/><path d="M8 8 14 5" stroke="currentColor" stroke-linecap="round"/></svg>
                                    <span>{{ $lang === 'en' ? 'Pie Chart' : 'Diagram Lingkaran' }}</span>
                                </button>
                                <button type="button" role="option" data-dl="bar" class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M3 13V8M8 13V4M13 13v-3" stroke-linecap="round"/></svg>
                                    <span>{{ $lang === 'en' ? 'Bar Chart' : 'Diagram Batang' }}</span>
                                </button>
                                <button type="button" role="option" data-dl="line" class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="m2 12 4-4 3 2 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span>{{ $lang === 'en' ? 'Line Chart' : 'Diagram Garis' }}</span>
                                </button>
                                <button type="button" role="option" data-dl="csv" class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M3 2.5h7l3 3v8h-10z" stroke-linejoin="round"/><path d="M10 2.5v3h3" stroke-linejoin="round"/></svg>
                                    <span>CSV</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="font-jetbrains-mono text-[10px] uppercase tracking-[0.2em] text-slate-500 mb-2">{{ $lang === 'en' ? 'Summary' : 'Ringkasan' }}</p>
                            <div class="flex items-baseline gap-2">
                                <span id="kpi-alerts" class="font-fraunces text-[44px] leading-none font-medium text-slate-900">0</span>
                                <span class="font-jetbrains-mono text-[11px] uppercase tracking-[0.18em] text-slate-500">{{ $lang === 'en' ? 'points' : 'titik' }}</span>
                            </div>
                            <p class="font-jetbrains-mono text-[10px] uppercase tracking-[0.14em] text-slate-500 mt-2">
                                <span id="kpi-area" class="normal-case tracking-normal text-slate-500">Sumatera</span>
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <p class="font-jetbrains-mono text-[10px] uppercase tracking-[0.2em] text-slate-500">{{ $lang === 'en' ? 'Yearly Trend' : 'Tren Tahunan' }}</p>
                                <span id="trend-peak" class="font-jetbrains-mono text-[10px] text-slate-500"></span>
                            </div>
                            <div id="trend-chart"></div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <p class="font-jetbrains-mono text-[10px] uppercase tracking-[0.2em] text-slate-500">{{ $lang === 'en' ? 'Intensity Levels' : 'Tingkat Intensitas' }}</p>
                                <span id="dn-total" class="font-jetbrains-mono text-[10px] text-slate-500"></span>
                            </div>
                            <div id="dn-summary"></div>
                        </div>


                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <p id="by-title" class="font-jetbrains-mono text-[10px] uppercase tracking-[0.2em] text-slate-500">{{ $lang === 'en' ? 'By Province' : 'Berdasarkan Provinsi' }}</p>
                                <span id="by-count" class="font-jetbrains-mono text-[10px] text-slate-500"></span>
                            </div>
                            <div id="prov-bars" class="space-y-2"></div>
                        </div>
                    </div>
                </aside>
            </main>
        </div>

        <script>
            (function () {
                const LANG = @json($lang);
                const I18N = {
                    loadFail: LANG === 'en' ? 'failed to load data' : 'gagal memuat data',
                    subd: LANG === 'en' ? 'Subd.' : 'Kec.',
                    points: LANG === 'en' ? 'points' : 'titik',
                    regions: LANG === 'en' ? 'regions' : 'wilayah',
                    region: LANG === 'en' ? 'Region' : 'Wilayah',
                    peak: LANG === 'en' ? 'peak ' : 'puncak ',
                    byPrefix: LANG === 'en' ? 'By ' : 'Berdasarkan ',
                    byProvince: LANG === 'en' ? 'Province' : 'Provinsi',
                    byRegency: LANG === 'en' ? 'Regency' : 'Kabupaten',
                    bySubdistrict: LANG === 'en' ? 'Subdistrict' : 'Kecamatan',
                    noFire: LANG === 'en' ? 'No fire points in this area.' : 'Tidak ada titik kebakaran pada wilayah ini.',
                    play: LANG === 'en' ? 'Play year animation' : 'Putar animasi tahun',
                    pause: LANG === 'en' ? 'Pause animation' : 'Jeda animasi',
                };
                const DN = {
                    3: { color: '#C84A26', label: LANG === 'en' ? 'High' : 'Tinggi', range: 'DN ≥25' },
                    2: { color: '#E8A93A', label: LANG === 'en' ? 'Moderate' : 'Sedang', range: 'DN 13–21' },
                    1: { color: '#E8C547', label: LANG === 'en' ? 'Low' : 'Rendah', range: 'DN ≤9' },
                };
                const fmt = (n) => Number(n || 0).toLocaleString('id-ID');
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                // Fire intensity → 3 tiers. Raw DN values: 3,5,9 / 13,21 / 25,35,40,76.
                const tierOf = (dn) => dn <= 9 ? 1 : (dn <= 21 ? 2 : 3);

                const cellSize = () => { const z = map.getZoom(); return Math.max(2, Math.min(12, 3 + (z - 6))); };

                // ---- Spatial grid for hover-tooltipping the nearest fire point ----
                const GRID = 40; let pointGrid = new Map(), gridPts = null;
                function buildGrid(pts) {
                    pointGrid = new Map();
                    pts.forEach((pt, i) => {
                        const key = Math.floor(pt[0] * GRID) + ',' + Math.floor(pt[1] * GRID);
                        let arr = pointGrid.get(key);
                        if (!arr) { arr = []; pointGrid.set(key, arr); }
                        arr.push(i);
                    });
                    gridPts = pts;
                }
                function nearestPoint(latlng) {
                    if (!gridPts || !gridPts.length) return null;
                    const blat = Math.floor(latlng.lat * GRID), blng = Math.floor(latlng.lng * GRID);
                    let best = null, bestD = Infinity;
                    for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
                        const arr = pointGrid.get((blat + dy) + ',' + (blng + dx));
                        if (!arr) continue;
                        for (const i of arr) {
                            const pt = gridPts[i];
                            const dlat = pt[0] - latlng.lat, dlng = pt[1] - latlng.lng;
                            const d = dlat * dlat + dlng * dlng;
                            if (d < bestD) { bestD = d; best = pt; }
                        }
                    }
                    return best;
                }

                // ---- Custom canvas layer: crisp square cells per alert, anchored to map ----
                let CellLayerClass = null;
                function buildCellLayer() {
                    return L.Layer.extend({
                        options: { baseCell: 3, minCell: 2, maxCell: 12, alpha: 0.82 },
                        initialize(latlngs, opts) { this._latlngs = latlngs || []; L.setOptions(this, opts); },
                        setLatLngs(latlngs) { this._latlngs = latlngs; return this.redraw(); },
                        redraw() {
                            if (this._canvas && this._map && !this._frame) {
                                this._frame = L.Util.requestAnimFrame(this._redraw, this);
                            }
                            return this;
                        },
                        onAdd(map) {
                            this._map = map;
                            if (!this._canvas) this._initCanvas();
                            map.getPanes().overlayPane.appendChild(this._canvas);
                            map.on('moveend', this._reset, this);
                            map.on('zoomend', this._reset, this);
                            if (map.options.zoomAnimation && L.Browser.any3d) {
                                map.on('zoomanim', this._animateZoom, this);
                            }
                            this._reset();
                        },
                        onRemove(map) {
                            map.getPanes().overlayPane.removeChild(this._canvas);
                            map.off('moveend', this._reset, this);
                            map.off('zoomend', this._reset, this);
                            map.off('zoomanim', this._animateZoom, this);
                        },
                        _initCanvas() {
                            const canvas = this._canvas = L.DomUtil.create('canvas', 'leaflet-cell-layer leaflet-layer');
                            const originProp = L.DomUtil.testProp(['transformOrigin', 'WebkitTransformOrigin', 'msTransformOrigin']);
                            canvas.style[originProp] = '0 0';
                            const size = this._map.getSize();
                            canvas.width = size.x; canvas.height = size.y;
                            const animated = this._map.options.zoomAnimation && L.Browser.any3d;
                            L.DomUtil.addClass(canvas, 'leaflet-zoom-' + (animated ? 'animated' : 'hide'));
                        },
                        _reset() {
                            const canvas = this._canvas, map = this._map;
                            L.DomUtil.setPosition(canvas, map.containerPointToLayerPoint([0, 0]));
                            const size = map.getSize();
                            if (canvas.width !== size.x) canvas.width = size.x;
                            if (canvas.height !== size.y) canvas.height = size.y;
                            this._redraw();
                        },
                        _animateZoom(e) {
                            const map = this._map;
                            const scale = map.getZoomScale(e.zoom);
                            const topLeft = map._latLngToNewLayerPoint(map.getBounds().getNorthWest(), e.zoom, e.center);
                            L.DomUtil.setTransform(this._canvas, topLeft, scale);
                        },
                        _redraw() {
                            const ctx = this._ctx || (this._ctx = this._canvas.getContext('2d'));
                            ctx.clearRect(0, 0, this._canvas.width, this._canvas.height);
                            const map = this._map;
                            const z = map.getZoom();
                            const cell = Math.max(this.options.minCell, Math.min(this.options.maxCell, this.options.baseCell + (z - 6)));
                            const r = cell / 2;
                            const pts = this._latlngs;
                            for (let i = 0; i < pts.length; i++) {
                                const p = pts[i];
                                const pt = map.latLngToContainerPoint([p[0], p[1]]);
                                ctx.globalAlpha = p[3] != null ? p[3] : this.options.alpha;
                                ctx.fillStyle = p[2];
                                ctx.fillRect(pt.x - r, pt.y - r, cell, cell);
                            }
                            ctx.globalAlpha = 1;
                            this._frame = null;
                        },
                    });
                }

                // ---- Custom canvas layer: matched land-cover rings at fire points ----
                let LucPointLayerClass = null;
                const lucRingSprites = new Map();
                function lucPointRadius(z) {
                    return Math.max(3, Math.min(6, 3 + (z - 6) * 0.45));
                }
                function lucRingRadius(z) {
                    return Math.max(5, Math.min(8, lucPointRadius(z) + 2));
                }
                function lucRingSprite(color, radius) {
                    const key = color + '|' + radius;
                    let sprite = lucRingSprites.get(key);
                    if (sprite) return sprite;
                    const pad = 2;
                    sprite = document.createElement('canvas');
                    sprite.width = radius * 2 + pad * 2;
                    sprite.height = radius * 2 + pad * 2;
                    const ctx = sprite.getContext('2d');
                    const cx = sprite.width / 2, cy = sprite.height / 2;
                    ctx.beginPath();
                    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                    ctx.strokeStyle = '#FFFFFF';
                    ctx.lineWidth = 5;
                    ctx.stroke();
                    ctx.beginPath();
                    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                    ctx.strokeStyle = color;
                    ctx.lineWidth = 3.25;
                    ctx.stroke();
                    lucRingSprites.set(key, sprite);
                    return sprite;
                }
                function buildLucPointLayer() {
                    return L.Layer.extend({
                        options: { pane: 'landcoverPointsPane' },
                        initialize(data, opts) {
                            this._data = data;
                            this._grid = opts.grid || null;
                            this._year = opts.year;
                            this._drawnPoints = [];
                            L.setOptions(this, opts);
                        },
                        setData(data, opts) {
                            this._data = data;
                            if (opts) {
                                if (opts.grid !== undefined) this._grid = opts.grid;
                                if (opts.year !== undefined) this._year = opts.year;
                            }
                            return this.redraw();
                        },
                        redraw() {
                            if (this._canvas && this._map && !this._frame) {
                                this._frame = L.Util.requestAnimFrame(this._redraw, this);
                            }
                            return this;
                        },
                        onAdd(map) {
                            this._map = map;
                            if (!this._canvas) this._initCanvas();
                            const pane = map.getPane(this.options.pane) || map.getPanes().overlayPane;
                            pane.appendChild(this._canvas);
                            map.on('moveend', this._reset, this);
                            map.on('zoomend', this._reset, this);
                            if (map.options.zoomAnimation && L.Browser.any3d) {
                                map.on('zoomanim', this._animateZoom, this);
                            }
                            this._reset();
                        },
                        onRemove(map) {
                            if (this._canvas && this._canvas.parentNode) this._canvas.parentNode.removeChild(this._canvas);
                            this._drawnPoints = [];
                            map.off('moveend', this._reset, this);
                            map.off('zoomend', this._reset, this);
                            map.off('zoomanim', this._animateZoom, this);
                        },
                        _initCanvas() {
                            const canvas = this._canvas = L.DomUtil.create('canvas', 'leaflet-luc-points-layer leaflet-layer');
                            const originProp = L.DomUtil.testProp(['transformOrigin', 'WebkitTransformOrigin', 'msTransformOrigin']);
                            canvas.style[originProp] = '0 0';
                            const size = this._map.getSize();
                            canvas.width = size.x; canvas.height = size.y;
                            const animated = this._map.options.zoomAnimation && L.Browser.any3d;
                            L.DomUtil.addClass(canvas, 'leaflet-zoom-' + (animated ? 'animated' : 'hide'));
                        },
                        _reset() {
                            const canvas = this._canvas, map = this._map;
                            if (!canvas || !map) return;
                            L.DomUtil.setPosition(canvas, map.containerPointToLayerPoint([0, 0]));
                            const size = map.getSize();
                            if (canvas.width !== size.x) canvas.width = size.x;
                            if (canvas.height !== size.y) canvas.height = size.y;
                            this._redraw();
                        },
                        _animateZoom(e) {
                            const map = this._map;
                            const scale = map.getZoomScale(e.zoom);
                            const topLeft = map._latLngToNewLayerPoint(map.getBounds().getNorthWest(), e.zoom, e.center);
                            L.DomUtil.setTransform(this._canvas, topLeft, scale);
                        },
                        _redraw() {
                            const ctx = this._ctx || (this._ctx = this._canvas.getContext('2d'));
                            ctx.clearRect(0, 0, this._canvas.width, this._canvas.height);
                            const data = this._data;
                            this._drawnPoints = [];
                            if (!data) { this._frame = null; return; }
                            const map = this._map;
                            const z = map.getZoom();
                            const radius = lucRingRadius(z);
                            const bounds = map.getBounds().pad(0.08);
                            const west = bounds.getWest(), east = bounds.getEast();
                            const south = bounds.getSouth(), north = bounds.getNorth();
                            const cell = LUC_POINT_GRID;
                            const x0 = Math.floor(west / cell), x1 = Math.floor(east / cell);
                            const y0 = Math.floor(south / cell), y1 = Math.floor(north / cell);
                            const grid = this._grid;
                            const w = this._canvas.width, h = this._canvas.height;
                            ctx.globalAlpha = z < 8 ? 0.72 : 0.92;
                            if (grid) {
                                for (let gy = y0; gy <= y1; gy++) {
                                    for (let gx = x0; gx <= x1; gx++) {
                                        const arr = grid.get(gy + ',' + gx);
                                        if (!arr) continue;
                                        for (let k = 0; k < arr.length; k++) {
                                            const i = arr[k];
                                            const lat = data.lat[i], lng = data.lng[i];
                                            if (lat < south || lat > north || lng < west || lng > east) continue;
                                            const p = map.latLngToContainerPoint([lat, lng]);
                                            if (p.x < -10 || p.y < -10 || p.x > w + 10 || p.y > h + 10) continue;
                                            this._drawPoint(ctx, data, i, p.x, p.y, radius);
                                        }
                                    }
                                }
                            }
                            ctx.globalAlpha = 1;
                            this._frame = null;
                        },
                        _drawPoint(ctx, data, index, x, y, radius) {
                            const year = data.fireYear ? data.fireYear[index] : this._year;
                            const color = lucMarkerColor(data.dn[index], year);
                            const sprite = lucRingSprite(color, radius);
                            ctx.drawImage(sprite, x - sprite.width / 2, y - sprite.height / 2);
                            this._drawnPoints.push({ index, x, y });
                        },
                        findPointAt(latlng) {
                            const click = this._map.latLngToContainerPoint(latlng);
                            const tolerance = lucRingRadius(this._map.getZoom()) + 6;
                            let bestIndex = null;
                            let bestDistance = tolerance * tolerance;

                            this._drawnPoints.forEach((point) => {
                                const distance = (point.x - click.x) * (point.x - click.x) + (point.y - click.y) * (point.y - click.y);
                                if (distance <= bestDistance) {
                                    bestDistance = distance;
                                    bestIndex = point.index;
                                }
                            });

                            return bestIndex;
                        },
                    });
                }

                // ---- State ----
                let map, tileLayer, cellLayer, bndLayer = null;
                let FIRES = [], FIRE_LANDCOVER = [], PROVINCES = [], REGS = [], KECS = [];
                const firePointIndex = new WeakMap();
                const kecLookup = new Map(); // kecCode -> { kecamatan, kabupaten, kabCode, province, provCode }
                const desaCache = {};
                let provBoundsAll = null;
                const sel = { province: null, kabupaten: null, kecCode: null, kabCode: null, kecamatan: null };
                const state = { level: 'prov', dn: new Set([1, 2, 3]), lucClass: null, year: null, mapStyle: 'light' };
                let lastStats = null;
                let lastFiltered = [];
                let tipMarker = null;
                let SEARCH_INDEX = [];

                // ---- Land cover 2020–2024 samples and matched points ----
                // Unmapped source DNs are grouped provisionally by their spatial
                // neighborhood patterns across the 2020–2024 web rasters. Source DNs
                // remain unchanged in matched-point data and popup details.
                const LUC_YEARS = [2020, 2021, 2022, 2023, 2024];
                const LUC_POINT_GRID = 0.05;
                const LUC_CLASS_ORDER = [3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, 76];
                const LUC_DN_TO_CLASS = {
                    3: 3, 5: 5, 9: 9, 11: 21, 12: 3, 13: 13, 21: 21,
                    24: 24, 25: 25, 30: 30, 31: 31, 33: 33, 35: 35,
                    40: 40, 46: 3, 48: 21, 49: 21, 76: 76, 80: 21, 92: 3,
                };
                const LUC_PROVISIONAL_DNS = new Set([11, 12, 46, 48, 49, 80, 92]);
                const LUC_CLASS_NAMES = {
                    3: { id: 'Formasi Hutan', en: 'Forest Formation' },
                    5: { id: 'Mangrove', en: 'Mangrove' },
                    9: { id: 'Kebun Kayu', en: 'Pulpwood Plantation' },
                    13: { id: 'Tumbuhan Non-Hutan Lainnya', en: 'Other Natural Vegetation' },
                    21: { id: 'Pertanian Lainnya', en: 'Other Agriculture' },
                    24: { id: 'Permukiman', en: 'Urban Area' },
                    25: { id: 'Non-Vegetasi Lainnya', en: 'Other Non-Vegetation' },
                    30: { id: 'Lubang Tambang', en: 'Mining Pit' },
                    31: { id: 'Tambak', en: 'Aquaculture' },
                    33: { id: 'Sungai, Danau, Laut', en: 'River, Lake, Ocean' },
                    35: { id: 'Sawit', en: 'Oil Palm' },
                    40: { id: 'Sawah', en: 'Rice Paddy' },
                    76: { id: 'Hutan Rawa Gambut', en: 'Peat Swamp Forest' },
                };
                // One marker color per displayed category; fire colors remain separate.
                const LUC_MARKER_PALETTE = {
                    3: '#18953A', 5: '#0AA38A', 9: '#5B3A1A', 13: '#187C95',
                    21: '#FDD617', 24: '#175CFD', 25: '#671895', 30: '#CA7CFE',
                    31: '#4991FD', 33: '#17FDFD', 35: '#FD17B0', 40: '#958018', 76: '#EC8DCD',
                };
                const LUC_RASTER_MANIFEST = {
                    2020: '/data/landcover/index.json',
                    2021: '/data/landcover/raster_2021.json',
                    2022: '/data/landcover/raster_2022.json',
                    2023: '/data/landcover/raster_2023.json',
                    2024: '/data/landcover/raster_2024.json',
                };
                let lucPointsPane = null, lucPointsPopup = null;
                let lucMatchedLayer = null, lucMatchedData = null, lucMatchedCount = 0;
                const lucYearStates = new Map();

                function selectedLucYear() {
                    const year = Number(state.year);
                    return LUC_YEARS.includes(year) ? year : LUC_YEARS[0];
                }

                function lucState(year = selectedLucYear()) {
                    const y = Number(year);
                    let s = lucYearStates.get(y);
                    if (!s) {
                        s = { year: y, meta: null, metaPromise: null };
                        lucYearStates.set(y, s);
                    }
                    return s;
                }

                const lucColor = (dn, year = selectedLucYear()) => {
                    const s = lucState(year);
                    return (s.meta && s.meta.palette && s.meta.palette[dn]) || '#94A3B8';
                };
                const lucPointClass = (dn) => LUC_DN_TO_CLASS[dn] || dn;
                const lucMarkerColor = (dn, year = selectedLucYear()) => LUC_MARKER_PALETTE[lucPointClass(dn)] || lucColor(dn, year);
                function lucClassLabel(dn) {
                    const className = LUC_CLASS_NAMES[lucPointClass(dn)];
                    return className ? (LANG === 'en' ? className.en : className.id) : 'DN ' + dn;
                }

                function lucCategoryCounts(rawCounts) {
                    const counts = Object.fromEntries(LUC_CLASS_ORDER.map((dn) => [dn, 0]));
                    Object.entries(rawCounts).forEach(([dn, count]) => {
                        const classDn = LUC_DN_TO_CLASS[dn];
                        if (classDn !== undefined) counts[classDn] += count;
                    });
                    return counts;
                }

                const MAP_STYLE_LAYERS = {
                    light: {
                        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        options: { subdomains: ['a', 'b', 'c'], maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' },
                    },
                    dark: {
                        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
                        options: { subdomains: ['a', 'b', 'c'], maxZoom: 19, attribution: '&copy; CARTO' },
                    },
                    sat: {
                        url: 'https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
                        options: { subdomains: ['mt0', 'mt1', 'mt2', 'mt3'], maxZoom: 20, attribution: '&copy; Google' },
                    },
                };
                const MAP_STYLE_META = {
                    light: { icon: '☀', label: LANG === 'en' ? 'Light' : 'Terang' },
                    dark: { icon: '🌑', label: LANG === 'en' ? 'Dark' : 'Gelap' },
                    sat: { icon: '🛰', label: LANG === 'en' ? 'Satellite' : 'Satelit' },
                };

                function closeMapStyleMenu() {
                    const menu = document.getElementById('map-style-menu');
                    const trigger = document.getElementById('map-style-trigger');
                    if (menu) menu.classList.add('hidden');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                }
                function openMapStyleMenu() {
                    const menu = document.getElementById('map-style-menu');
                    const trigger = document.getElementById('map-style-trigger');
                    if (menu) menu.classList.remove('hidden');
                    if (trigger) trigger.setAttribute('aria-expanded', 'true');
                }

                function setMapStyle(style) {
                    const provider = MAP_STYLE_LAYERS[style] || MAP_STYLE_LAYERS.light;
                    if (!map) { state.mapStyle = style; return; }
                    if (tileLayer) { map.removeLayer(tileLayer); }
                    tileLayer = L.tileLayer(provider.url, provider.options).addTo(map);
                    state.mapStyle = style;
                    const meta = MAP_STYLE_META[style] || MAP_STYLE_META.light;
                    const icon = document.getElementById('map-style-icon');
                    const label = document.getElementById('map-style-label');
                    if (icon) icon.textContent = meta.icon;
                    if (label) label.textContent = meta.label;
                    document.querySelectorAll('#map-style-menu [data-map-style]').forEach((button) => {
                        const active = button.dataset.mapStyle === style;
                        button.classList.toggle('bg-slate-100', active);
                        button.classList.toggle('font-medium', active);
                        button.classList.toggle('text-slate-900', active);
                        button.classList.toggle('text-slate-700', !active);
                        button.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    closeMapStyleMenu();
                }

                const STYLES = {
                    prov: { color: '#6B7B4E', weight: 1.5, opacity: 0.9, fillOpacity: 0.04 },
                    kab:  { color: '#6B7B4E', weight: 1.2, opacity: 0.8, fillOpacity: 0.05 },
                    kec:  { color: '#6B7B4E', weight: 1.0, opacity: 0.7, fillOpacity: 0.06 },
                    desa: { color: '#F2EDE3', weight: 0.6, opacity: 0.5, fillOpacity: 0.05 },
                };

                function boot() {
                    if (typeof L === 'undefined') { return setTimeout(boot, 50); }
                    map = L.map('map', { zoomControl: false, attributionControl: false, scrollWheelZoom: true })
                        .setView([-0.5, 101.5], 6);
                    setMapStyle(state.mapStyle);
                    const mapStyleTrigger = document.getElementById('map-style-trigger');
                    const mapStyleMenu = document.getElementById('map-style-menu');
                    const mapStyleDD = document.getElementById('map-style-dd');
                    if (mapStyleTrigger && mapStyleMenu) {
                        mapStyleTrigger.addEventListener('click', (event) => {
                            event.stopPropagation();
                            if (mapStyleMenu.classList.contains('hidden')) openMapStyleMenu();
                            else closeMapStyleMenu();
                        });
                        mapStyleMenu.addEventListener('click', (event) => {
                            const btn = event.target.closest('[data-map-style]');
                            if (btn) setMapStyle(btn.dataset.mapStyle);
                        });
                    }
                    document.addEventListener('click', (event) => {
                        if (mapStyleDD && !mapStyleDD.contains(event.target)) closeMapStyleMenu();
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') closeMapStyleMenu();
                    });

                    const searchInput = document.getElementById('place-search');
                    const searchResults = document.getElementById('search-results');
                    if (searchInput) {
                        searchInput.addEventListener('input', () => searchPlace(searchInput.value));
                        searchInput.addEventListener('focus', () => searchPlace(searchInput.value));
                        searchInput.addEventListener('keydown', (event) => {
                            if (event.key === 'Escape') {
                                searchResults.classList.add('hidden');
                                searchResults.innerHTML = '';
                                searchInput.blur();
                                return;
                            }
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                // Enter memilih hasil pertama (zoom ke wilayahnya);
                                // klik manual pada dropdown tetap tersedia.
                                const matches = searchPlace(searchInput.value);
                                if (matches && matches.length) {
                                    selectSearchItem(matches[0]);
                                }
                            }
                        });
                    }
                    document.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!target.closest || !searchResults || !searchInput) return;
                        if (!target.closest('#place-search') && !target.closest('#search-results')) {
                            searchResults.classList.add('hidden');
                        }
                    });

                    tipMarker = L.circleMarker([0, 0], { radius: 0, opacity: 0, fillOpacity: 0, stroke: false, interactive: false })
                        .bindTooltip('', { className: 'telf-tip', direction: 'top', offset: [0, -6] })
                        .addTo(map);
                    let lastTipKey = null;
                    const closeTip = () => { if (lastTipKey) { tipMarker.closeTooltip(); lastTipKey = null; } };
                    map.on('movestart', closeTip);
                    map.on('zoomstart', closeTip);
                    map.getContainer().addEventListener('mousemove', (e) => {
                        const rect = map.getContainer().getBoundingClientRect();
                        const cp = [e.clientX - rect.left, e.clientY - rect.top];
                        const ll = map.containerPointToLatLng(cp);
                        const pt = nearestPoint(ll);
                        if (!pt) { closeTip(); return; }
                        const pp = map.latLngToContainerPoint([pt[0], pt[1]]);
                        const thr = cellSize() / 2 + 6;
                        if ((pp.x - cp[0]) * (pp.x - cp[0]) + (pp.y - cp[1]) * (pp.y - cp[1]) > thr * thr) { closeTip(); return; }
                        const key = pt[0] + ',' + pt[1] + ',' + pt[2] + ',' + pt[3];
                        if (key !== lastTipKey) {
                            const a = kecLookup.get(pt[4]);
                            tipMarker.setLatLng([pt[0], pt[1]]);
                            const fireIndex = firePointIndex.get(pt);
                            const landCoverSample = FIRE_LANDCOVER[fireIndex];
                            const beforeLabel = landCoverSample && landCoverSample[0] !== null
                                ? (pt[3] - 1) + ' DN ' + landCoverSample[0]
                                : null;
                            const afterLabel = landCoverSample && landCoverSample[1] !== null
                                ? (pt[3] + 1) + ' DN ' + landCoverSample[1]
                                : null;
                            const sampleLabel = beforeLabel && afterLabel
                                ? ' · MapBiomas ' + beforeLabel + ' → ' + afterLabel
                                : (beforeLabel || afterLabel
                                    ? ' · MapBiomas ' + (beforeLabel || afterLabel) + ' · ' + (LANG === 'en' ? 'other comparison year unavailable' : 'tahun pembanding lain tidak tersedia')
                                    : '');
                            tipMarker.setTooltipContent('DN ' + pt[2] + ' · ' + pt[3] + ' · ' + I18N.subd + ' ' + (a ? a.kecamatan : '—') + sampleLabel);
                            tipMarker.openTooltip();
                            lastTipKey = key;
                        }
                    });
                    map.getContainer().addEventListener('mouseout', closeTip);

                    Promise.all([
                        fetch('/data/sumatera_provinces.geojson').then((r) => r.json()),
                        fetch('/data/sumatera_regencies.geojson').then((r) => r.json()),
                        fetch('/data/sumatera_kecamatan.geojson').then((r) => r.json()),
                        fetch('/data/fires.json').then((r) => r.json()),
                        fetch('/data/fire_landcover_samples.json').then((r) => {
                            if (!r.ok) throw new Error('fire land-cover samples HTTP ' + r.status);
                            return r.json();
                        }),
                    ]).then(([provs, regs, kecs, fires, fireLandcover]) => {
                        PROVINCES = provs.features;
                        REGS = regs.features;
                        KECS = kecs.features;
                        KECS.forEach((f) => {
                            const p = f.properties;
                            kecLookup.set(p.code, {
                                kecamatan: p.kecamatan, kabupaten: p.kabupaten,
                                kabCode: p.kabCode, province: p.province, provCode: p.provCode,
                            });
                        });
                        FIRES = fires.pts; // [lat, lng, dn, year, kecCode]
                        if (fireLandcover.type !== 'fire-landcover-samples' || fireLandcover.fireCount !== FIRES.length || fireLandcover.records.length !== FIRES.length) {
                            throw new Error('fire land-cover sample order/count mismatch');
                        }
                        FIRE_LANDCOVER = fireLandcover.records;
                        FIRES.forEach((point, index) => firePointIndex.set(point, index));
                        provBoundsAll = L.geoJSON(PROVINCES).getBounds();
                        document.getElementById('loading').style.display = 'none';
                        buildSearchIndex();
                        loadDesaSearchIndex();
                        buildTimeline();
                        renderBoundary();
                        updateBreadcrumb();
                        if (provBoundsAll.isValid()) map.fitBounds(provBoundsAll, { padding: [12, 12] });
                        render();
                        updateLucYearTabs();
                        updateLucLabels();

                        // Land-cover point clicks open their own popup. Other
                        // map clicks keep the existing boundary/fire behavior.
                        map.on('click', (e) => {
                            const oe = e.originalEvent;
                            if (oe && oe.target && (oe.target.tagName === 'path' || (oe.target.closest && oe.target.closest('.leaflet-control')))) return;
                            const lucIndex = lucPointAt(e.latlng);
                            const firePoint = nearestPoint(e.latlng);
                            if (firePoint) {
                                const clickPoint = map.latLngToContainerPoint(e.latlng);
                                const firePixel = map.latLngToContainerPoint([firePoint[0], firePoint[1]]);
                                const fireTolerance = cellSize() / 2 + 6;
                                const fireDistance = Math.hypot(firePixel.x - clickPoint.x, firePixel.y - clickPoint.y);
                                if (fireDistance <= fireTolerance) {
                                    if (lucIndex !== null && fireDistance > cellSize() / 2) {
                                        showLucPointPopup(lucIndex);
                                    } else {
                                        showFireLandCoverPopup(firePoint);
                                    }
                                    return;
                                }
                            }
                            if (lucIndex !== null) {
                                showLucPointPopup(lucIndex);
                            }
                        });
                    }).catch((e) => {
                        document.getElementById('loading').textContent = I18N.loadFail;
                        console.error(e);
                    });
                }

                // ---- Boundaries (clickable drill) ----
                function renderBoundary() {
                    if (bndLayer) { map.removeLayer(bndLayer); bndLayer = null; }
                    let fc, style, onClick, tipKey;
                    if (state.level === 'prov') {
                        fc = PROVINCES; style = STYLES.prov; onClick = drillProv; tipKey = 'province';
                    } else if (state.level === 'kab') {
                        fc = REGS.filter((r) => r.properties.province === sel.province);
                        style = STYLES.kab; onClick = drillKab; tipKey = 'regency';
                    } else if (state.level === 'kec') {
                        fc = KECS.filter((k) => k.properties.kabupaten === sel.kabupaten);
                        style = STYLES.kec; onClick = drillKec; tipKey = 'kecamatan';
                    } else if (state.level === 'desa') {
                        const g = desaCache[sel.kabCode];
                        if (!g) return;
                        fc = g.features.filter((d) => d.properties.kecCode === sel.kecCode);
                        style = STYLES.desa; onClick = null; tipKey = 'desa';
                    }
                    if (!fc || (Array.isArray(fc) ? fc.length === 0 : !fc.length)) return;
                    bndLayer = L.geoJSON(fc, {
                        style: () => Object.assign({}, style),
                        interactive: onClick !== null,
                        onEachFeature: (f, lyr) => {
                            const tip = f.properties[tipKey];
                            if (tip) lyr.bindTooltip(tip, { className: 'telf-tip', direction: 'top', sticky: true });
                            if (onClick) lyr.on('click', () => onClick(f));
                            lyr.on('mouseover', () => lyr.setStyle({ color: '#F2EDE3', weight: (style.weight || 1) + 1, opacity: 1, fillOpacity: 0.14 }));
                            lyr.on('mouseout', () => lyr.setStyle(Object.assign({}, style)));
                        },
                    }).addTo(map);
                }

                function drillProv(f) {
                    sel.province = f.properties.province; sel.kabupaten = null; sel.kecCode = null; sel.kabCode = null; sel.kecamatan = null;
                    state.level = 'kab'; renderBoundary(); updateBreadcrumb(); fitTo(bndLayer); render();
                }
                function drillKab(f) {
                    sel.kabupaten = f.properties.regency; sel.kecCode = null; sel.kabCode = null; sel.kecamatan = null;
                    state.level = 'kec'; renderBoundary(); updateBreadcrumb(); fitTo(bndLayer); render();
                }
                function drillKec(f) {
                    sel.kecCode = f.properties.code; sel.kabCode = f.properties.kabCode; sel.kecamatan = f.properties.kecamatan;
                    state.level = 'desa';
                    render(); updateBreadcrumb();
                    loadDesa(sel.kabCode, () => { renderBoundary(); fitBoundaryOr(f); });
                }

                function goToLevel(level) {
                    if (level === 'prov') { sel.province = sel.kabupaten = sel.kecCode = sel.kabCode = sel.kecamatan = null; }
                    else if (level === 'kab') { sel.kabupaten = sel.kecCode = sel.kabCode = sel.kecamatan = null; }
                    else if (level === 'kec') { sel.kecCode = sel.kabCode = sel.kecamatan = null; }
                    state.level = level;
                    if (level === 'desa' && sel.kabCode) {
                        loadDesa(sel.kabCode, () => { renderBoundary(); fitTo(bndLayer); });
                    } else {
                        renderBoundary();
                        fitTo(level === 'prov' ? null : bndLayer);
                    }
                    updateBreadcrumb();
                    render();
                }

                function loadDesa(kabCode, cb) {
                    if (desaCache[kabCode]) { cb(); return; }
                    fetch('/data/desa/' + kabCode + '.geojson').then((r) => r.json()).then((g) => {
                        desaCache[kabCode] = g;
                    }).catch((e) => console.error('desa load fail', kabCode, e))
                    // Selalu lanjut: tanpa data desa pun peta tetap zoom ke
                    // poligon kecamatan (fallback di pemanggil).
                    .finally(cb);
                }

                function fitTo(layer) {
                    if (layer && layer.getBounds) {
                        const b = layer.getBounds();
                        if (b.isValid()) { map.fitBounds(b, { padding: [14, 14] }); return; }
                    }
                    if (provBoundsAll && provBoundsAll.isValid()) map.fitBounds(provBoundsAll, { padding: [12, 12] });
                }

                // Zoom ke bndLayer hasil render; jika data desa gagal dimuat,
                // gambar poligon kecamatan sebagai pengganti agar peta tetap
                // zoom dan wilayahnya tetap terlihat.
                function fitBoundaryOr(feature) {
                    if (bndLayer) { fitTo(bndLayer); return; }
                    bndLayer = L.geoJSON(feature, {
                        style: () => Object.assign({}, STYLES.kec),
                        interactive: false,
                    }).addTo(map);
                    fitTo(bndLayer);
                }

                function updateBreadcrumb() {
                    const bc = document.getElementById('breadcrumb');
                    const crumbs = [{ l: 'prov', t: 'Sumatera' }];
                    if (sel.province) crumbs.push({ l: 'kab', t: sel.province });
                    if (sel.kabupaten) crumbs.push({ l: 'kec', t: sel.kabupaten });
                    if (sel.kecamatan) crumbs.push({ l: 'desa', t: sel.kecamatan });
                    bc.innerHTML = '';
                    const tag = document.createElement('span');
                    tag.textContent = I18N.region;
                    tag.className = 'font-jetbrains-mono text-[10px] uppercase tracking-[0.2em] text-[#5F7466] shrink-0';
                    bc.appendChild(tag);
                    crumbs.forEach((c, idx) => {
                        if (idx > 0) {
                            const s = document.createElement('span');
                            s.textContent = '›'; s.className = 'text-lime/40 shrink-0';
                            bc.appendChild(s);
                        }
                        const b = document.createElement('button');
                        b.type = 'button'; b.textContent = c.t;
                        b.className = 'hover:text-lime transition-colors truncate max-w-[220px] '
                            + (idx === crumbs.length - 1 ? ' text-cream font-medium' : 'text-[#8FA392]');
                        b.addEventListener('click', () => goToLevel(c.l));
                        bc.appendChild(b);
                    });
                }

                function buildSearchIndex() {
                    SEARCH_INDEX = [];
                    SEARCH_INDEX.push({
                        level: 'all',
                        name: LANG === 'en' ? 'Sumatra' : 'Sumatera',
                    });
                    PROVINCES.forEach((feature) => {
                        SEARCH_INDEX.push({
                            level: 'prov',
                            name: feature.properties.province,
                            code: feature.properties.provCode,
                            feature,
                        });
                    });
                    REGS.forEach((feature) => {
                        SEARCH_INDEX.push({
                            level: 'kab',
                            name: feature.properties.regency,
                            province: feature.properties.province,
                            code: feature.properties.kabCode,
                            feature,
                        });
                    });
                    KECS.forEach((feature) => {
                        SEARCH_INDEX.push({
                            level: 'kec',
                            name: feature.properties.kecamatan,
                            kabupaten: feature.properties.kabupaten,
                            province: feature.properties.province,
                            code: feature.properties.code,
                            kabCode: feature.properties.kabCode,
                            feature,
                        });
                    });
                }

                // Muat index nama desa (tanpa geometri) di latar belakang agar
                // pencarian bisa menemukan desa; dipanggil sekali saat boot.
                function loadDesaSearchIndex() {
                    fetch('/data/sumatera_desa_index.json').then((r) => r.json()).then((data) => {
                        const list = (data && data.d) ? data.d : [];
                        list.forEach((row) => {
                            const k = kecLookup.get(row[3]);
                            SEARCH_INDEX.push({
                                level: 'desa',
                                name: row[1],
                                code: row[0],
                                kecamatan: row[2],
                                kecCode: row[3],
                                kabCode: row[4],
                                kabupaten: k ? k.kabupaten : '',
                                province: k ? k.province : '',
                            });
                        });
                    }).catch((e) => console.error('desa index load fail', e));
                }

                function formatSearchLabel(item) {
                    if (item.level === 'all') {
                        return LANG === 'en' ? 'Whole island' : 'Seluruh pulau';
                    }
                    if (item.level === 'prov') {
                        return item.name + ' · ' + (LANG === 'en' ? 'Province' : 'Provinsi');
                    }
                    if (item.level === 'kab') {
                        return item.name + ', ' + item.province;
                    }
                    if (item.level === 'desa') {
                        return item.name + ', ' + item.kecamatan + ', ' + item.kabupaten;
                    }
                    return item.name + ', ' + item.kabupaten;
                }

                function selectSearchItem(item) {
                    const searchInput = document.getElementById('place-search');
                    const results = document.getElementById('search-results');
                    if (item.level === 'all') {
                        sel.province = null;
                        sel.kabupaten = null;
                        sel.kecCode = null;
                        sel.kabCode = null;
                        sel.kecamatan = null;
                        state.level = 'prov';
                        renderBoundary();
                        updateBreadcrumb();
                        fitTo(null);
                        render();
                    } else if (item.level === 'prov') {
                        sel.province = item.name;
                        sel.kabupaten = null;
                        sel.kecCode = null;
                        sel.kabCode = null;
                        sel.kecamatan = null;
                        state.level = 'kab';
                        renderBoundary();
                        updateBreadcrumb();
                        fitTo(bndLayer);
                        render();
                    } else if (item.level === 'kab') {
                        sel.province = item.province;
                        sel.kabupaten = item.name;
                        sel.kecCode = null;
                        sel.kabCode = item.code;
                        sel.kecamatan = null;
                        state.level = 'kec';
                        renderBoundary();
                        updateBreadcrumb();
                        fitTo(bndLayer);
                        render();
                    } else if (item.level === 'kec') {
                        sel.province = item.province;
                        sel.kabupaten = item.kabupaten;
                        sel.kecCode = item.code;
                        sel.kabCode = item.kabCode;
                        sel.kecamatan = item.name;
                        state.level = 'desa';
                        updateBreadcrumb();
                        loadDesa(sel.kabCode, () => {
                            renderBoundary();
                            fitBoundaryOr(item.feature);
                            render();
                        });
                    } else if (item.level === 'desa') {
                        sel.province = item.province;
                        sel.kabupaten = item.kabupaten;
                        sel.kecCode = item.kecCode;
                        sel.kabCode = item.kabCode;
                        sel.kecamatan = item.kecamatan;
                        state.level = 'desa';
                        updateBreadcrumb();
                        loadDesa(sel.kabCode, () => {
                            renderBoundary();
                            const target = item.code
                                ? desaCache[sel.kabCode].features.find((d) => d.properties.code === item.code)
                                : null;
                            if (target) {
                                const dl = L.geoJSON(target).getBounds();
                                if (dl.isValid()) map.fitBounds(dl, { padding: [20, 20] });
                                else fitTo(bndLayer);
                            } else {
                                fitTo(bndLayer);
                            }
                            render();
                        });
                    }
                    if (searchInput) {
                        searchInput.value = item.name;
                        searchInput.focus();
                    }
                    if (results) {
                        results.classList.add('hidden');
                        results.innerHTML = '';
                    }
                }

                function searchPlace(query) {
                    const results = document.getElementById('search-results');
                    if (!results) return [];
                    const term = String(query || '').trim().toLowerCase();
                    if (term.length < 2) {
                        results.classList.add('hidden');
                        results.innerHTML = '';
                        return [];
                    }
                    const matches = SEARCH_INDEX.filter((item) => {
                        return item.name.toLowerCase().includes(term)
                            || (item.province && item.province.toLowerCase().includes(term))
                            || (item.kabupaten && item.kabupaten.toLowerCase().includes(term))
                            || (item.kecamatan && item.kecamatan.toLowerCase().includes(term));
                    }).slice(0, 8);
                    results.innerHTML = '';
                    if (!matches.length) {
                        const empty = document.createElement('div');
                        empty.className = 'px-3 py-2 text-[11px] text-slate-500';
                        empty.textContent = LANG === 'en' ? 'No matching regions' : 'Tidak ada wilayah yang cocok';
                        results.appendChild(empty);
                        results.classList.remove('hidden');
                        return [];
                    }
                    matches.forEach((item) => {
                        const row = document.createElement('button');
                        row.type = 'button';
                        row.className = 'w-full px-3 py-2 text-left text-[11px] text-slate-700 hover:bg-slate-50 transition-colors';
                        row.innerHTML = '<div class="font-medium">' + item.name + '</div>'
                            + '<div class="text-slate-500 text-[10px]">' + formatSearchLabel(item) + '</div>';
                        row.addEventListener('click', () => selectSearchItem(item));
                        results.appendChild(row);
                    });
                    results.classList.remove('hidden');
                    return matches;
                }

                // ---- Fire points (cells) ----
                function filtered(applyYear) {
                    if (applyYear === undefined) applyYear = true;
                    return FIRES.filter((pt) => {
                        if (applyYear && state.year !== null && pt[3] !== state.year) return false;
                        const code = pt[4];
                        if (state.level === 'desa') {
                            if (code !== sel.kecCode) return false;
                        } else {
                            const a = kecLookup.get(code);
                            if (!a) return false;
                            if (state.level === 'kab' && a.province !== sel.province) return false;
                            if (state.level === 'kec' && a.kabupaten !== sel.kabupaten) return false;
                        }
                        if (!state.dn.has(tierOf(pt[2]))) return false;
                        if (state.lucClass !== null) {
                            const fireIndex = firePointIndex.get(pt);
                            const sample = fireIndex === undefined ? null : FIRE_LANDCOVER[fireIndex];
                            if (!sample || sample[2] === null || sample[2] === undefined) return false;
                            if (lucPointClass(sample[2]) !== state.lucClass) return false;
                        }
                        return true;
                    });
                }

                function render() {
                    const baseData = filtered(false);
                    const data = (state.year !== null) ? baseData.filter((pt) => pt[3] === state.year) : baseData;
                    lastFiltered = data;
                    buildGrid(data);

                    const pts = data.map((pt) => {
                        const t = tierOf(pt[2]);
                        const alpha = t === 3 ? 0.92 : (t === 2 ? 0.8 : 0.62);
                        return [pt[0], pt[1], (DN[t] || DN[1]).color, alpha];
                    });
                    if (!cellLayer) {
                        const Cls = CellLayerClass || (CellLayerClass = buildCellLayer());
                        cellLayer = new Cls(pts, { baseCell: 3 }).addTo(map);
                    } else {
                        if (!map.hasLayer(cellLayer)) cellLayer.addTo(map);
                        cellLayer.setLatLngs(pts);
                    }
                    renderStats(data, baseData);
                    updateLucPointsLayer();
                }

                function renderStats(data, baseData) {
                    document.getElementById('kpi-alerts').textContent = fmt(data.length);
                    document.getElementById('kpi-alerts').classList.toggle('ember', data.length > 0);
                    const areaName = sel.kecamatan || sel.kabupaten || sel.province || 'Sumatera';
                    document.getElementById('kpi-area').textContent = areaName;
                    document.getElementById('footer-count').textContent = fmt(data.length) + ' ' + I18N.points;

                    // Intensity breakdown — donut chart
                    const sum = { 3: 0, 2: 0, 1: 0 };
                    data.forEach((pt) => { sum[tierOf(pt[2])]++; });
                    const total = data.length;
                    document.getElementById('dn-total').textContent = 'Σ ' + fmt(total);
                    const cs = document.getElementById('dn-summary');
                    cs.innerHTML = '';

                    const svgNs = 'http://www.w3.org/2000/svg';
                    const pie = document.createElementNS(svgNs, 'svg');
                    pie.setAttribute('viewBox', '0 0 120 120');
                    pie.setAttribute('class', 'w-32 h-32 mx-auto');
                    pie.setAttribute('role', 'img');
                    pie.setAttribute('aria-label', LANG === 'en' ? 'Intensity' : 'Intensitas');
                    const pieGroup = document.createElementNS(svgNs, 'g');
                    pieGroup.setAttribute('transform', 'rotate(-90 60 60)');
                    const R = 40, SW = 17;
                    const C = 2 * Math.PI * R;
                    if (total === 0) {
                        const ring = document.createElementNS(svgNs, 'circle');
                        ring.setAttribute('cx', 60); ring.setAttribute('cy', 60);
                        ring.setAttribute('r', R); ring.setAttribute('stroke', '#E2E8F0');
                        ring.setAttribute('stroke-width', SW); ring.setAttribute('fill', 'none');
                        pieGroup.appendChild(ring);
                    } else {
                        let acc = 0;
                        [3, 2, 1].forEach((k) => {
                            const frac = sum[k] / total;
                            if (frac === 0) return;
                            const segLen = frac * C;
                            const draw = Math.max(0, segLen - 2);
                            const seg = document.createElementNS(svgNs, 'circle');
                            seg.setAttribute('cx', 60); seg.setAttribute('cy', 60);
                            seg.setAttribute('r', R); seg.setAttribute('stroke', DN[k].color);
                            seg.setAttribute('stroke-width', SW); seg.setAttribute('fill', 'none');
                            seg.setAttribute('stroke-dasharray', draw + ' ' + (C - draw));
                            seg.setAttribute('stroke-dashoffset', -acc);
                            pieGroup.appendChild(seg);
                            acc += segLen;
                        });
                    }
                    pie.appendChild(pieGroup);
                    const centerValue = document.createElementNS(svgNs, 'text');
                    centerValue.setAttribute('x', 60); centerValue.setAttribute('y', 63);
                    centerValue.setAttribute('text-anchor', 'middle');
                    centerValue.setAttribute('class', 'font-fraunces');
                    centerValue.setAttribute('font-size', '16');
                    centerValue.setAttribute('font-weight', '500');
                    centerValue.setAttribute('fill', '#0F172A');
                    centerValue.textContent = fmt(total);
                    pie.appendChild(centerValue);
                    const centerUnit = document.createElementNS(svgNs, 'text');
                    centerUnit.setAttribute('x', 60); centerUnit.setAttribute('y', 78);
                    centerUnit.setAttribute('text-anchor', 'middle');
                    centerUnit.setAttribute('class', 'font-jetbrains-mono');
                    centerUnit.setAttribute('font-size', '6');
                    centerUnit.setAttribute('fill', '#94A3B8');
                    centerUnit.textContent = I18N.points;
                    pie.appendChild(centerUnit);
                    cs.appendChild(pie);

                    const dleg = document.createElement('div');
                    dleg.className = 'flex gap-2 mt-3';
                    [3, 2, 1].forEach((k) => {
                        const on = state.dn.has(k);
                        const c = document.createElement('button');
                        c.type = 'button';
                        c.setAttribute('aria-pressed', on ? 'true' : 'false');
                        c.title = LANG === 'en' ? 'Toggle the ' + DN[k].label + ' tier' : 'Tampilkan/sembunyikan tingkat ' + DN[k].label;
                        c.className = 'flex-1 flex flex-col items-center gap-1 rounded-lg py-1.5 px-1 transition-opacity hover:bg-slate-100'
                            + (on ? '' : ' opacity-40');
                        const pct = total === 0 ? 0 : Math.round(sum[k] / total * 100);
                        c.innerHTML =
                            '<span class="w-2.5 h-2.5 rounded-[2px]" style="background:' + DN[k].color + '"></span>' +
                            '<span class="text-[10px] text-slate-500' + (on ? '' : ' line-through') + '">' + DN[k].label + '</span>' +
                            '<span class="font-jetbrains-mono text-[11px] text-slate-900 font-medium">' + fmt(sum[k]) + '</span>' +
                            '<span class="font-jetbrains-mono text-[9px] text-slate-400">' + pct + '% · ' + DN[k].range + '</span>';
                        c.addEventListener('click', () => setDnTier(k, !state.dn.has(k)));
                        dleg.appendChild(c);
                    });
                    cs.appendChild(dleg);

                    // Region distribution — horizontal bar chart
                    const groupKey = state.level === 'prov' ? 'province' : (state.level === 'kab' ? 'kabupaten' : 'kecamatan');
                    document.getElementById('by-title').textContent =
                        I18N.byPrefix + (state.level === 'prov' ? I18N.byProvince : state.level === 'kab' ? I18N.byRegency : I18N.bySubdistrict);
                    const groups = {};
                    data.forEach((pt) => {
                        const a = kecLookup.get(pt[4]);
                        const k = (a && a[groupKey]) || '—';
                        groups[k] = (groups[k] || 0) + 1;
                    });
                    const entries = Object.entries(groups).sort((a, b) => b[1] - a[1]).slice(0, 10);
                    const max = Math.max(1, ...entries.map((e) => e[1]));
                    const bars = document.getElementById('prov-bars');
                    bars.innerHTML = '';
                    document.getElementById('by-count').textContent = entries.length ? (entries.length + ' ' + I18N.regions) : '';
                    if (entries.length === 0) {
                        const e = document.createElement('p');
                        e.className = 'text-[12px] text-[#7A8B7F]';
                        e.textContent = state.lucClass === null
                            ? I18N.noFire
                            : (LANG === 'en'
                                ? 'No fire points match ' + lucClassLabel(state.lucClass) + ' in ' + state.year + ' with the current filters.'
                                : 'Tidak ada titik kebakaran dengan kategori ' + lucClassLabel(state.lucClass) + ' pada tahun ' + state.year + ' dengan filter saat ini.');
                        bars.appendChild(e);
                    } else {
                        const axis = document.createElement('div');
                        axis.className = 'flex items-center gap-2 mb-1.5';
                        axis.innerHTML =
                            '<span class="w-24 shrink-0"></span>' +
                            '<div class="flex-1 flex justify-between font-jetbrains-mono text-[9px] text-[#5F7466]">' +
                            '<span>0</span><span>' + fmt(Math.round(max / 2)) + '</span><span>' + fmt(max) + '</span>' +
                            '</div>' +
                            '<span class="w-9 shrink-0"></span>';
                        bars.appendChild(axis);
                        entries.forEach(([p, v]) => {
                            const row = document.createElement('div');
                            row.className = 'flex items-center gap-2';
                            row.innerHTML =
                                '<span class="text-[11px] text-[#B7C1BA] truncate w-24 shrink-0" title="' + p + '">' + p + '</span>' +
                                '<div class="relative flex-1 h-3.5 rounded-[1px] bg-white/6 overflow-hidden">' +
                                    '<span class="absolute top-0 bottom-0 w-px bg-white/10" style="left:25%"></span>' +
                                    '<span class="absolute top-0 bottom-0 w-px bg-white/10" style="left:50%"></span>' +
                                    '<span class="absolute top-0 bottom-0 w-px bg-white/10" style="left:75%"></span>' +
                                    '<div class="relative h-full rounded-[1px] transition-[width] duration-300" style="width:' + (v / max * 100) + '%;min-width:2px;background:#C84A26"></div>' +
                                '</div>' +
                                '<span class="font-jetbrains-mono text-[10px] text-[#8FA392] w-9 text-right shrink-0">' + fmt(v) + '</span>';
                            bars.appendChild(row);
                        });
                    }

                    // ---- Tren tahunan (region + DN, semua tahun) ----
                    const years = YEARS.length ? YEARS : Array.from(new Set(FIRES.map((p) => p[3]))).sort((a, b) => a - b);
                    const yc = {}; years.forEach((y) => { yc[y] = 0; });
                    baseData.forEach((pt) => { yc[pt[3]] = (yc[pt[3]] || 0) + 1; });
                    const maxY = Math.max(1, ...years.map((y) => yc[y]));
                    const peak = years.reduce((a, b) => (yc[a] >= yc[b] ? a : b), years[0]);
                    document.getElementById('trend-peak').textContent = I18N.peak + peak;
                    const tc = document.getElementById('trend-chart');
                    tc.innerHTML = '';
                    const W = 280, H = 96, PADX = 10, PADT = 16, PADB = 14;
                    const svg = document.createElementNS(svgNs, 'svg');
                    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
                    svg.setAttribute('class', 'w-full h-auto');
                    svg.setAttribute('role', 'img');
                    svg.setAttribute('aria-label', I18N.peak + peak);
                    const n = years.length;
                    const xOf = (i) => n === 1 ? W / 2 : PADX + (i / (n - 1)) * (W - PADX * 2);
                    const yOf = (v) => H - PADB - (v / maxY) * (H - PADT - PADB);
                    [0.25, 0.5, 0.75].forEach((f) => {
                        const gy = H - PADB - f * (H - PADT - PADB);
                        const g = document.createElementNS(svgNs, 'line');
                        g.setAttribute('x1', PADX); g.setAttribute('x2', W - PADX);
                        g.setAttribute('y1', gy); g.setAttribute('y2', gy);
                        g.setAttribute('stroke', '#E2E8F0'); g.setAttribute('stroke-width', '1');
                        svg.appendChild(g);
                    });
                    const pts = years.map((y, i) => [xOf(i), yOf(yc[y] || 0)]);
                    if (n > 0) {
                        const area = document.createElementNS(svgNs, 'path');
                        const d = 'M ' + pts[0][0] + ' ' + (H - PADB)
                            + pts.map((p) => ' L ' + p[0] + ' ' + p[1]).join('')
                            + ' L ' + pts[pts.length - 1][0] + ' ' + (H - PADB) + ' Z';
                        area.setAttribute('d', d);
                        area.setAttribute('fill', 'rgba(216,80,30,0.06)');
                        svg.appendChild(area);
                    }
                    if (n > 1) {
                        const line = document.createElementNS(svgNs, 'polyline');
                        line.setAttribute('points', pts.map((p) => p[0] + ',' + p[1]).join(' '));
                        line.setAttribute('fill', 'none');
                        line.setAttribute('stroke', '#D8501E');
                        line.setAttribute('stroke-width', '1.5');
                        line.setAttribute('stroke-linejoin', 'round');
                        line.setAttribute('stroke-linecap', 'round');
                        svg.appendChild(line);
                    }
                    years.forEach((y, i) => {
                        const [x, yy] = pts[i];
                        const sel = (y === state.year);
                        const cnt = document.createElementNS(svgNs, 'text');
                        cnt.setAttribute('x', x); cnt.setAttribute('y', yy - 6);
                        cnt.setAttribute('text-anchor', 'middle');
                        cnt.setAttribute('class', 'font-jetbrains-mono');
                        cnt.setAttribute('font-size', '8');
                        cnt.setAttribute('fill', sel ? '#D8501E' : '#94A3B8');
                        cnt.textContent = fmt(yc[y] || 0);
                        svg.appendChild(cnt);
                        const dot = document.createElementNS(svgNs, 'circle');
                        dot.setAttribute('cx', x); dot.setAttribute('cy', yy);
                        dot.setAttribute('r', sel ? '3' : '2.5');
                        dot.setAttribute('fill', '#fff');
                        dot.setAttribute('stroke', '#D8501E');
                        dot.setAttribute('stroke-width', sel ? '2' : '1.5');
                        svg.appendChild(dot);
                        const hit = document.createElementNS(svgNs, 'circle');
                        hit.setAttribute('cx', x); hit.setAttribute('cy', yy);
                        hit.setAttribute('r', '9');
                        hit.setAttribute('fill', 'transparent');
                        hit.style.cursor = 'pointer';
                        hit.addEventListener('click', () => setYear(y));
                        svg.appendChild(hit);
                        const lab = document.createElementNS(svgNs, 'text');
                        lab.setAttribute('x', x); lab.setAttribute('y', H - 4);
                        lab.setAttribute('text-anchor', 'middle');
                        lab.setAttribute('class', 'font-jetbrains-mono');
                        lab.setAttribute('font-size', '8');
                        lab.setAttribute('fill', sel ? '#D8501E' : '#7A8B7F');
                        lab.textContent = String(y);
                        svg.appendChild(lab);
                    });
                    tc.appendChild(svg);
                    lastStats = { years: years.slice(), yc: Object.assign({}, yc), maxY: maxY, sum: sum, total: total, area: areaName };

                    // Yearly totals — vertical bar chart
                    const bc = document.createElement('div');
                    bc.className = 'mt-3 flex items-end gap-1 h-20';
                    years.forEach((y) => {
                        const col = document.createElement('div');
                        col.className = 'flex-1 flex flex-col items-center justify-end gap-0.5 h-full cursor-pointer group';
                        const val = document.createElement('span');
                        val.className = 'font-jetbrains-mono text-[8px] ' + (y === state.year ? 'text-lime' : 'text-slate-400');
                        val.textContent = fmt(yc[y] || 0);
                        const bar = document.createElement('div');
                        bar.className = 'w-full max-w-8 rounded-t-[2px] transition-colors duration-150';
                        bar.style.height = (maxY === 0 ? 0 : (yc[y] || 0) / maxY * 100) + '%';
                        bar.style.minHeight = '2px';
                        bar.style.background = y === state.year ? '#D8501E' : 'rgba(216,80,30,0.28)';
                        const lab = document.createElement('span');
                        lab.className = 'font-jetbrains-mono text-[8px] ' + (y === state.year ? 'text-lime' : 'text-slate-400');
                        lab.textContent = String(y);
                        col.appendChild(val);
                        col.appendChild(bar);
                        col.appendChild(lab);
                        col.addEventListener('click', () => setYear(y));
                        col.addEventListener('mouseenter', () => { bar.style.background = '#D8501E'; });
                        col.addEventListener('mouseleave', () => { bar.style.background = y === state.year ? '#D8501E' : 'rgba(216,80,30,0.28)'; });
                        bc.appendChild(col);
                    });
                    tc.appendChild(bc);
                }

                // ---- Land cover 2020–2024: metadata, legend, and matched points ----
                function lucPointsToggleChecked() {
                    const el = document.getElementById('luc-points-toggle');
                    return !!(el && el.checked);
                }

                function ensureLucPointsPane() {
                    if (!map || lucPointsPane) return;
                    lucPointsPane = map.createPane('landcoverPointsPane');
                    lucPointsPane.style.zIndex = '420'; // draw valid rings above the fire-square overlayPane (400)
                    lucPointsPane.style.pointerEvents = 'none';
                }

                function updateLucYearTabs() {
                    document.querySelectorAll('[data-luc-year]').forEach((btn) => {
                        const active = Number(btn.dataset.lucYear) === selectedLucYear();
                        btn.setAttribute('aria-selected', active ? 'true' : 'false');
                        btn.classList.toggle('bg-[#1C3A14]', active);
                        btn.classList.toggle('text-white', active);
                        btn.classList.toggle('text-slate-600', !active);
                        btn.classList.toggle('hover:text-slate-900', !active);
                    });
                }

                function updateLucLabels() {
                    const fireYear = selectedLucYear();
                    const pointsLabel = document.getElementById('luc-points-label');
                    const pointsToggle = document.getElementById('luc-points-toggle');
                    const hasPoints = LUC_YEARS.includes(fireYear);
                    if (pointsToggle) {
                        pointsToggle.disabled = !hasPoints;
                        if (!hasPoints) pointsToggle.checked = false;
                    }
                    if (pointsLabel) {
                        pointsLabel.textContent = hasPoints
                            ? (LANG === 'en'
                                ? 'Show land-cover rings at fire points (' + fireYear + ')'
                                : 'Tampilkan cincin tutupan lahan pada titik kebakaran (' + fireYear + ')')
                            : (LANG === 'en' ? 'Land cover available for 2020–2024 only' : 'Tutupan lahan hanya tersedia untuk 2020–2024');
                    }
                    const pointsNote = document.getElementById('luc-points-note');
                    if (pointsNote) {
                        pointsNote.textContent = (LANG === 'en'
                            ? 'Rings appear only where a fire point has a valid same-year MapBiomas DN. '
                                + (lucMatchedCount ? fmt(lucMatchedCount) + ' matched fire points for ' + fireYear + '. ' : '')
                                + 'This is not the full Sumatra land-cover point layer; the fire square stays centred inside the ring.'
                            : 'Cincin hanya muncul pada titik kebakaran yang memiliki DN MapBiomas valid pada tahun yang sama. '
                                + (lucMatchedCount ? fmt(lucMatchedCount) + ' titik kebakaran cocok untuk ' + fireYear + '. ' : '')
                                + 'Ini bukan layer seluruh titik tutupan lahan Sumatera; kotak kebakaran tetap di tengah cincin.');
                    }
                    const note = document.getElementById('luc-note');
                    if (note) {
                        note.textContent = (LANG === 'en'
                            ? 'MapBiomas land-cover raster for all Sumatra, ' + fireYear + ', resampled to a WGS84 web grid (~275 m). Seven unmapped DNs use provisional spatial groupings into 13 displayed categories; source DN values are preserved. The TIFF source collection is not verified. Not a fire-severity layer.'
                            : 'Raster tutupan lahan MapBiomas seluruh Sumatera tahun ' + fireYear + ', diubah ke grid web WGS84 (±275 m). Tujuh DN tanpa nama dikelompokkan sementara berdasarkan pola spasial ke dalam 13 kategori; nilai DN sumber tetap dipertahankan. Koleksi sumber TIFF belum terverifikasi. Bukan layer tingkat keparahan kebakaran.');
                    }
                }

                function syncLucChip() {
                    const chip = document.getElementById('luc-year-chip');
                    if (!chip) return;
                    const onPoints = lucPointsToggleChecked();
                    if (!onPoints) { chip.textContent = ''; return; }
                    chip.textContent = (LANG === 'en' ? 'fire ' : 'kebakaran ')
                        + selectedLucYear()
                        + ' · ' + fmt(lucMatchedCount) + ' ' + I18N.points;
                }

                function setLucYear(year) {
                    const next = Number(year);
                    if (!LUC_YEARS.includes(next) || next === selectedLucYear()) return;
                    setYear(next);
                }

                function fetchLandCoverIndex(year) {
                    const s = lucState(year);
                    if (s.metaPromise) return s.metaPromise;
                    const url = LUC_RASTER_MANIFEST[year];
                    if (!url) return Promise.resolve(null);
                    s.metaPromise = fetch(url).then((r) => {
                        if (!r.ok) throw new Error('landcover index HTTP ' + r.status);
                        return r.json();
                    }).then((d) => {
                        if (d.type !== 'landcover-raster' || Number(d.year) !== Number(year) || !d.image || !Array.isArray(d.bbox) || !d.step) {
                            throw new Error('unexpected landcover manifest');
                        }
                        s.meta = d;
                        if (Number(year) === selectedLucYear()) {
                            const section = document.getElementById('luc-section');
                            if (section) section.dataset.lucLoaded = 'true';
                            buildLucLegend();
                            syncLucChip();
                        }
                        if (Number(year) === Number(state.year)) updateLucPointsLayer();
                        return d;
                    }).catch((e) => {
                        console.error('landcover index load fail', e);
                        s.metaPromise = null;
                        if (Number(year) === selectedLucYear()) {
                            const chip = document.getElementById('luc-year-chip');
                            if (chip) chip.textContent = LANG === 'en' ? 'data unavailable' : 'data tak tersedia';
                        }
                        return null;
                    });
                    return s.metaPromise;
                }

                function buildLucPointGrid(data) {
                    const grid = new Map();
                    const cell = LUC_POINT_GRID;
                    for (let i = 0; i < data.count; i++) {
                        const key = Math.floor(data.lat[i] / cell) + ',' + Math.floor(data.lng[i] / cell);
                        let arr = grid.get(key);
                        if (!arr) { arr = []; grid.set(key, arr); }
                        arr.push(i);
                    }
                    return grid;
                }

                function buildMatchedLucData() {
                    const rows = [];
                    for (const point of lastFiltered) {
                        const index = firePointIndex.get(point);
                        if (index === undefined) continue;
                        const sample = FIRE_LANDCOVER[index];
                        const dn = sample ? sample[2] : null;
                        if (dn === null || dn === undefined) continue;
                        rows.push({ point, index, dn });
                    }
                    const count = rows.length;
                    const data = {
                        count,
                        lat: new Float64Array(count),
                        lng: new Float64Array(count),
                        dn: new Uint8Array(count),
                        fireIndex: new Uint32Array(count),
                        fireYear: new Uint16Array(count),
                        matched: true,
                    };
                    const counts = {};
                    for (let i = 0; i < count; i++) {
                        const row = rows[i];
                        data.lat[i] = row.point[0];
                        data.lng[i] = row.point[1];
                        data.dn[i] = row.dn;
                        data.fireIndex[i] = row.index;
                        data.fireYear[i] = row.point[3];
                        counts[row.dn] = (counts[row.dn] || 0) + 1;
                    }
                    data.grid = buildLucPointGrid(data);
                    data.counts = counts;
                    return data;
                }

                function updateLucPointsLayer() {
                    if (!map) return;
                    const fireYear = Number(state.year);
                    if (!lucPointsToggleChecked() || !LUC_YEARS.includes(fireYear)) {
                        if (lucMatchedLayer && map.hasLayer(lucMatchedLayer)) map.removeLayer(lucMatchedLayer);
                        if (lucPointsPopup) { map.closePopup(lucPointsPopup); lucPointsPopup = null; }
                        lucMatchedData = null;
                        lucMatchedCount = 0;
                        const section = document.getElementById('luc-section');
                        if (section) {
                            section.dataset.lucPointsLoaded = 'false';
                            section.dataset.lucMatchedCount = '0';
                        }
                        buildLucLegend();
                        updateLucLabels();
                        syncLucChip();
                        return;
                    }
                    const s = lucState(fireYear);
                    if (!s.meta) {
                        fetchLandCoverIndex(fireYear);
                        syncLucChip();
                        return;
                    }
                    const data = buildMatchedLucData();
                    lucMatchedData = data;
                    lucMatchedCount = data.count;
                    ensureLucPointsPane();
                    if (!LucPointLayerClass) LucPointLayerClass = buildLucPointLayer();
                    if (!lucMatchedLayer) {
                        lucMatchedLayer = new LucPointLayerClass(data, {
                            pane: 'landcoverPointsPane',
                            grid: data.grid,
                            year: fireYear,
                        });
                        lucMatchedLayer.addTo(map);
                    } else {
                        lucMatchedLayer.setData(data, {
                            grid: data.grid,
                            year: fireYear,
                        });
                        if (!map.hasLayer(lucMatchedLayer)) lucMatchedLayer.addTo(map);
                    }
                    const section = document.getElementById('luc-section');
                    if (section) {
                        section.dataset.lucPointsLoaded = 'true';
                        section.dataset.lucMatchedCount = String(data.count);
                    }
                    buildLucLegend();
                    updateLucLabels();
                    syncLucChip();
                }

                function buildLucLegend() {
                    const box = document.getElementById('luc-legend');
                    if (!box) return;
                    const s = lucState();
                    const source = s.meta;
                    if (!source) {
                        box.classList.add('hidden');
                        box.innerHTML = '';
                        return;
                    }
                    const rawCounts = {};
                    lastFiltered.forEach((point) => {
                        const fireIndex = firePointIndex.get(point);
                        const sample = fireIndex === undefined ? null : FIRE_LANDCOVER[fireIndex];
                        const dn = sample ? sample[2] : null;
                        if (dn !== null && dn !== undefined) rawCounts[dn] = (rawCounts[dn] || 0) + 1;
                    });
                    const counts = lucCategoryCounts(rawCounts);
                    box.classList.remove('hidden');
                    box.innerHTML = '';
                    const allButton = document.getElementById('luc-all-categories');
                    if (allButton) {
                        const allSelected = state.lucClass === null;
                        allButton.setAttribute('aria-pressed', allSelected ? 'true' : 'false');
                        allButton.className = 'rounded border px-1.5 py-1 text-[9px] transition-colors hover:bg-slate-50'
                            + (allSelected ? ' border-[#1C3A14] bg-[#F1F5F1] font-medium text-[#1C3A14] ring-1 ring-[#1C3A14]/20' : ' border-slate-200 text-slate-600');
                    }
                    LUC_CLASS_ORDER.forEach((dn) => {
                        const count = counts[dn] || 0;
                        const on = state.lucClass === dn;
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.setAttribute('aria-pressed', on ? 'true' : 'false');
                        item.title = LANG === 'en' ? 'Filter by ' + lucClassLabel(dn) : 'Filter berdasarkan ' + lucClassLabel(dn);
                        item.className = 'flex w-full min-w-0 items-center gap-2 rounded border px-2 py-1.5 text-left text-[10px] leading-snug transition-colors hover:border-slate-400 hover:bg-slate-50'
                            + (on ? ' border-[#1C3A14] bg-[#F1F5F1] font-medium text-[#1C3A14] ring-1 ring-[#1C3A14]/20' : ' border-slate-200 text-slate-600');
                        const swatch = document.createElement('span');
                        swatch.className = 'h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-white';
                        swatch.style.background = lucMarkerColor(dn, s.year);
                        const label = document.createElement('span');
                        label.className = 'min-w-0 flex-1 whitespace-normal wrap-anywhere leading-snug';
                        label.textContent = lucClassLabel(dn);
                        item.append(swatch, label);
                        const countLabel = document.createElement('span');
                        countLabel.className = 'ml-auto shrink-0 whitespace-nowrap text-right tabular-nums text-slate-400';
                        countLabel.textContent = fmt(count);
                        item.appendChild(countLabel);
                        item.addEventListener('click', () => setLucClass(on ? null : dn));
                        box.appendChild(item);
                    });
                }

                function setLucClass(dn) {
                    state.lucClass = dn;
                    render();
                }

                function lucDnChipHtml(dn, year = selectedLucYear()) {
                    const provisional = LUC_PROVISIONAL_DNS.has(Number(dn));
                    const note = provisional ? (LANG === 'en' ? ' · provisional grouping' : ' · pengelompokan sementara') : '';
                    return '<span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-[2px]" style="background:' + lucMarkerColor(dn, year) + '"></span><span class="font-medium text-slate-800">' + lucClassLabel(dn) + ' · DN ' + dn + note + '</span></span>';
                }

                function lucPointAt(latlng) {
                    if (!lucPointsToggleChecked() || !lucMatchedLayer || !map.hasLayer(lucMatchedLayer)) return null;
                    return lucMatchedLayer.findPointAt(latlng);
                }

                function showLucPointPopup(index) {
                    const data = lucMatchedData;
                    if (!data || index < 0 || index >= data.count) return;
                    const fireIndex = data.fireIndex[index];
                    const fire = FIRES[fireIndex];
                    const lat = data.lat[index], lng = data.lng[index], dn = data.dn[index];
                    const year = fire ? fire[3] : data.fireYear[index];
                    const fireDn = fire ? fire[2] : null;
                    const unavailable = LANG === 'en' ? 'Not available' : 'Tidak tersedia';
                    const title = LANG === 'en' ? 'Land cover at fire point' : 'Tutupan lahan pada titik kebakaran';
                    const note = LANG === 'en'
                        ? 'The ring DN is sampled from the same-year MapBiomas raster cell containing this fire coordinate. The ring is drawn around the fire square and the coordinate is unchanged. This is not a causal-impact or change-area analysis.'
                        : 'DN cincin diambil dari sel raster MapBiomas tahun yang sama yang memuat koordinat kebakaran ini. Cincin digambar mengelilingi kotak kebakaran dan koordinatnya tidak diubah. Ini bukan analisis dampak sebab-akibat atau luas perubahan.';
                    const html =
                        '<div class="telf-luc-title">' + title + '</div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'Fire year' : 'Tahun kebakaran') + '</span><span class="telf-luc-value">' + year + '</span></div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'Land-cover class' : 'Kelas tutupan lahan') + '</span><span class="telf-luc-value">' + lucDnChipHtml(dn, year) + '</span></div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'Fire intensity DN' : 'DN intensitas kebakaran') + '</span><span class="telf-luc-value">' + (fireDn === null || fireDn === undefined ? unavailable : 'DN ' + fireDn) + '</span></div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'Coords' : 'Koordinat') + '</span><span class="telf-luc-value">' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</span></div>'
                        + '<div class="telf-luc-note">' + note + '</div>';
                    const popup = L.popup({
                        className: 'telf-luc-popup',
                        maxWidth: 340,
                        autoPan: true,
                    }).setLatLng([lat, lng]).setContent(html);
                    popup.on('remove', () => {
                        if (lucPointsPopup === popup) lucPointsPopup = null;
                    });
                    lucPointsPopup = popup.openOn(map);
                }

                function showFireLandCoverPopup(point) {
                    const fireIndex = firePointIndex.get(point);
                    const sample = FIRE_LANDCOVER[fireIndex];
                    if (!sample) return;
                    const unavailable = LANG === 'en' ? 'Not available' : 'Tidak tersedia';
                    const before = sample[0] === null ? unavailable : 'DN ' + sample[0];
                    const after = sample[1] === null ? unavailable : 'DN ' + sample[1];
                    const fireDn = sample[2] === null || sample[2] === undefined ? unavailable : 'DN ' + sample[2];
                    const title = LANG === 'en' ? 'Fire point · MapBiomas sample' : 'Titik kebakaran · sampel MapBiomas';
                    const note = LANG === 'en'
                        ? 'Raw DN sampled from the nearest ~275 m annual raster cell at this aggregated fire point. The fire data has a year but no exact event date. The same-year DN is also used for the land-cover ring around this fire point. This comparison does not prove that fire caused a land-cover change.'
                        : 'DN mentah diambil dari sel raster tahunan terdekat (±275 m) pada titik kebakaran agregat ini. Data kebakaran hanya memiliki tahun, tanpa tanggal kejadian persis. DN tahun yang sama juga dipakai untuk cincin tutupan lahan di sekitar titik ini. Perbandingan ini tidak membuktikan kebakaran menyebabkan perubahan tutupan lahan.';
                    const html =
                        '<div class="telf-luc-title">' + title + '</div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'Fire year' : 'Tahun kebakaran') + '</span><span class="telf-luc-value">' + point[3] + ' · ' + fireDn + '</span></div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'MapBiomas before' : 'MapBiomas sebelum') + '</span><span class="telf-luc-value">' + (point[3] - 1) + ' · ' + before + '</span></div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'MapBiomas after' : 'MapBiomas sesudah') + '</span><span class="telf-luc-value">' + (point[3] + 1) + ' · ' + after + '</span></div>'
                        + '<div class="telf-luc-row"><span class="telf-luc-label">' + (LANG === 'en' ? 'Coordinates' : 'Koordinat') + '</span><span class="telf-luc-value">' + point[0].toFixed(5) + ', ' + point[1].toFixed(5) + '</span></div>'
                        + '<div class="telf-luc-note">' + note + '</div>';
                    L.popup({ className: 'telf-luc-popup', maxWidth: 340, autoPan: true })
                        .setLatLng([point[0], point[1]])
                        .setContent(html)
                        .openOn(map);
                }

                // ---- Year timeline (MapBiomas-style slider) ----
                let YEARS = [];
                let tlTimer = null, tlDragging = false, scanTimer = null;
                const tlTrack = () => document.getElementById('tl-track');
                const PAD = 12;

                // Scan-line sweeps the map when the year animation plays.
                function sweepScan() {
                    const s = document.getElementById('scan');
                    const el = document.getElementById('map');
                    if (!s || !el || reduceMotion) return;
                    const w = el.clientWidth;
                    s.classList.remove('hidden');
                    s.style.transition = 'none';
                    s.style.left = '0px';
                    void s.offsetWidth;
                    s.style.transition = 'left 1.1s cubic-bezier(.6,.05,.3,1)';
                    s.style.left = w + 'px';
                    clearTimeout(scanTimer);
                    scanTimer = setTimeout(() => s.classList.add('hidden'), 1250);
                }

                function yearToX(y) {
                    const w = tlTrack().clientWidth;
                    const i = YEARS.indexOf(y);
                    if (i < 0 || YEARS.length === 1) return PAD;
                    return PAD + (i / (YEARS.length - 1)) * (w - PAD * 2);
                }
                function xToYear(x) {
                    const w = tlTrack().clientWidth;
                    let frac = (x - PAD) / (w - PAD * 2);
                    frac = Math.max(0, Math.min(1, frac || 0));
                    return YEARS[Math.round(frac * (YEARS.length - 1))];
                }
                function moveHandle() {
                    document.getElementById('tl-handle').style.left = yearToX(state.year) + 'px';
                }
                function highlightTick() {
                    document.querySelectorAll('.tl-dot').forEach((t) => {
                        t.classList.toggle('dot-active', Number(t.dataset.year) === state.year);
                    });
                }
                function setYear(y, doRender) {
                    const next = Number(y);
                    const yearChanged = next !== Number(state.year);
                    if (yearChanged) {
                        if (map && lucPointsPopup) {
                            map.closePopup(lucPointsPopup);
                            lucPointsPopup = null;
                        }
                    }
                    state.year = next;
                    moveHandle();
                    highlightTick();
                    document.getElementById('tl-year').textContent = next;
                    updateLucYearTabs();
                    updateLucLabels();
                    if (yearChanged && doRender !== false) render();
                }
                function positionTimeline() {
                    document.querySelectorAll('.tl-dot').forEach((t) => {
                        t.style.left = yearToX(Number(t.dataset.year)) + 'px';
                    });
                    moveHandle();
                }
                function stopPlay() {
                    if (tlTimer) { clearInterval(tlTimer); tlTimer = null; }
                    clearTimeout(scanTimer);
                    const s = document.getElementById('scan');
                    if (s) s.classList.add('hidden');
                    const btn = document.getElementById('tl-play');
                        btn.textContent = '▶'; btn.classList.remove('text-lime'); btn.setAttribute('aria-label', I18N.play);
                }
                function buildTimeline() {
                    YEARS = Array.from(new Set(FIRES.map((p) => p[3]))).sort((a, b) => a - b);
                    const dotsEl = document.getElementById('tl-dots');
                    dotsEl.innerHTML = '';
                    YEARS.forEach((y) => {
                        const d = document.createElement('span');
                        d.className = 'tl-dot absolute -translate-x-1/2 -translate-y-1/2 w-1 h-1 rounded-full bg-slate-400 pointer-events-none';
                        d.dataset.year = y;
                        d.style.left = yearToX(y) + 'px';
                        d.style.top = '50%';
                        dotsEl.appendChild(d);
                    });
                    if (state.year === null || !YEARS.includes(state.year)) state.year = YEARS[YEARS.length - 1];
                    positionTimeline();
                    setYear(state.year, false);

                    const track = tlTrack();
                    track.addEventListener('pointerdown', (e) => {
                        tlDragging = true; stopPlay(); track.setPointerCapture(e.pointerId);
                        const rect = track.getBoundingClientRect();
                        setYear(xToYear(e.clientX - rect.left));
                    });
                    track.addEventListener('pointermove', (e) => {
                        if (!tlDragging) return;
                        const rect = track.getBoundingClientRect();
                        setYear(xToYear(e.clientX - rect.left));
                    });
                    track.addEventListener('pointerup', () => { tlDragging = false; });
                    track.addEventListener('pointercancel', () => { tlDragging = false; });

                    document.getElementById('tl-play').addEventListener('click', () => {
                        if (tlTimer) { stopPlay(); return; }
                        const btn = document.getElementById('tl-play');
                        btn.textContent = '⏸'; btn.classList.add('text-lime'); btn.setAttribute('aria-label', I18N.pause);
                        tlTimer = setInterval(() => {
                            let i = YEARS.indexOf(state.year);
                            i = (i + 1) % YEARS.length;
                            sweepScan();
                            setYear(YEARS[i]);
                        }, 1400);
                    });

                    window.addEventListener('resize', positionTimeline);
                }

                // ---- Controls ----
                // Satu pintu untuk toggle tier DN: pill di peta dan legend di
                // panel readout sama-sama lewat sini agar selalu sinkron.
                function syncMapLegend() {
                    document.querySelectorAll('#map-legend button[data-tier]').forEach((btn) => {
                        const on = state.dn.has(Number(btn.dataset.tier));
                        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                        btn.classList.toggle('opacity-40', !on);
                        btn.classList.toggle('line-through', !on);
                    });
                }
                function setDnTier(tier, on) {
                    if (on) state.dn.add(tier); else state.dn.delete(tier);
                    syncMapLegend();
                    render();
                }
                document.querySelectorAll('#map-legend button[data-tier]').forEach((btn) => {
                    btn.addEventListener('click', () => setDnTier(Number(btn.dataset.tier), !state.dn.has(Number(btn.dataset.tier))));
                });
                document.getElementById('f-reset').addEventListener('click', () => {
                    state.dn = new Set([1, 2, 3]);
                    state.lucClass = null;
                    stopPlay();
                    syncMapLegend();
                    if (YEARS.length) setYear(YEARS[YEARS.length - 1], false);
                    goToLevel('prov');
                });

                // ---- Land cover control wiring ----
                document.querySelectorAll('[data-luc-year]').forEach((btn) => {
                    btn.addEventListener('click', () => setLucYear(Number(btn.dataset.lucYear)));
                });
                const lucFilterWidget = document.getElementById('luc-filter-widget');
                const lucFilterTrigger = document.getElementById('luc-filter-trigger');
                const lucFilterPanel = document.getElementById('luc-filter-panel');
                const lucFilterChevron = document.getElementById('luc-filter-chevron');
                let isLucFilterOpen = false;
                function setLucFilterOpen(open) {
                    if (!lucFilterTrigger || !lucFilterPanel) return;
                    isLucFilterOpen = Boolean(open);
                    lucFilterTrigger.setAttribute('aria-expanded', isLucFilterOpen ? 'true' : 'false');
                    lucFilterPanel.classList.toggle('hidden', !isLucFilterOpen);
                    if (lucFilterChevron) lucFilterChevron.classList.toggle('rotate-180', isLucFilterOpen);
                }
                if (lucFilterTrigger) {
                    lucFilterTrigger.addEventListener('click', () => {
                        setLucFilterOpen(!isLucFilterOpen);
                    });
                }
                if (lucFilterWidget) {
                    lucFilterWidget.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            setLucFilterOpen(false);
                            lucFilterTrigger?.focus();
                        }
                    });
                }
                document.getElementById('luc-all-categories')?.addEventListener('click', () => setLucClass(null));
                const lucPointsToggleEl = document.getElementById('luc-points-toggle');
                if (lucPointsToggleEl) {
                    lucPointsToggleEl.checked = false;
                    lucPointsToggleEl.addEventListener('change', updateLucPointsLayer);
                }
                // ---- Download data ----
                function downloadBlob(blob, name) {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = name;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
                }
                function downloadSvgAsPng(svgStr, name) {
                    const url = URL.createObjectURL(new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' }));
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        const scale = 2;
                        canvas.width = img.width * scale;
                        canvas.height = img.height * scale;
                        const ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#fff';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        URL.revokeObjectURL(url);
                        canvas.toBlob((b) => downloadBlob(b, name), 'image/png');
                    };
                    img.src = url;
                }
                function pieChartSvg() {
                    const st = lastStats || { sum: { 3: 0, 2: 0, 1: 0 }, total: 0 };
                    const S = 200, R = 70, SW = 28, C = 2 * Math.PI * R;
                    let segs = '';
                    if (st.total > 0) {
                        let acc = 0;
                        [3, 2, 1].forEach((k) => {
                            const frac = st.sum[k] / st.total;
                            if (frac === 0) return;
                            const segLen = frac * C;
                            const draw = Math.max(0, segLen - 3);
                            segs += '<circle cx="100" cy="100" r="' + R + '" fill="none" stroke="' + DN[k].color + '" stroke-width="' + SW + '" stroke-dasharray="' + draw + ' ' + (C - draw) + '" stroke-dashoffset="' + -acc + '"/>';
                            acc += segLen;
                        });
                    } else {
                        segs += '<circle cx="100" cy="100" r="' + R + '" fill="none" stroke="#E2E8F0" stroke-width="' + SW + '"/>';
                    }
                    let legend = '';
                    [3, 2, 1].forEach((k) => {
                        const pct = st.total === 0 ? 0 : Math.round(st.sum[k] / st.total * 100);
                        legend += '<rect x="220" y="' + (60 + (k - 1) * 34) + '" width="12" height="12" rx="2" fill="' + DN[k].color + '"/>'
                            + '<text x="240" y="' + (70 + (k - 1) * 34) + '" font-family="monospace" font-size="11" fill="#334155">' + DN[k].label + ' — ' + fmt(st.sum[k]) + ' (' + pct + '%)</text>';
                    });
                    return '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="200" viewBox="0 0 360 200">'
                        + '<rect width="360" height="200" fill="#fff"/>'
                        + '<g transform="rotate(-90 100 100)">' + segs + '</g>'
                        + '<text x="100" y="106" text-anchor="middle" font-family="serif" font-size="28" font-weight="600" fill="#0F172A">' + fmt(st.total) + '</text>'
                        + '<text x="100" y="122" text-anchor="middle" font-family="monospace" font-size="9" fill="#94A3B8">' + I18N.points + '</text>'
                        + legend + '</svg>';
                }
                function barChartSvg() {
                    const st = lastStats || { years: [], yc: {}, maxY: 1 };
                    const years = st.years.length ? st.years : [0];
                    const W = 360, H = 200, PADX = 24, PADT = 20, PADB = 24;
                    const n = years.length;
                    const xOf = (i) => n === 1 ? W / 2 : PADX + (i / (n - 1)) * (W - PADX * 2);
                    const base = H - PADB;
                    const bw = Math.min(34, (W - PADX * 2) / n * 0.6);
                    let bars = '';
                    years.forEach((y, i) => {
                        const v = st.yc[y] || 0;
                        const h = (v / st.maxY) * (H - PADT - PADB);
                        const x = xOf(i);
                        bars += '<rect x="' + (x - bw / 2) + '" y="' + (base - h) + '" width="' + bw + '" height="' + Math.max(1, h) + '" rx="2" fill="#D8501E"/>'
                            + '<text x="' + x + '" y="' + (base - h - 5) + '" text-anchor="middle" font-family="monospace" font-size="9" fill="#64748B">' + fmt(v) + '</text>'
                            + '<text x="' + x + '" y="' + (H - 8) + '" text-anchor="middle" font-family="monospace" font-size="9" fill="#94A3B8">' + y + '</text>';
                    });
                    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '">'
                        + '<rect width="' + W + '" height="' + H + '" fill="#fff"/>' + bars + '</svg>';
                }
                function lineChartSvg() {
                    const st = lastStats || { years: [], yc: {}, maxY: 1 };
                    const years = st.years.length ? st.years : [0];
                    const W = 360, H = 200, PADX = 24, PADT = 20, PADB = 24;
                    const n = years.length;
                    const xOf = (i) => n === 1 ? W / 2 : PADX + (i / (n - 1)) * (W - PADX * 2);
                    const yOf = (v) => H - PADB - (v / st.maxY) * (H - PADT - PADB);
                    let grid = '';
                    [0.25, 0.5, 0.75].forEach((f) => {
                        const gy = H - PADB - f * (H - PADT - PADB);
                        grid += '<line x1="' + PADX + '" x2="' + (W - PADX) + '" y1="' + gy + '" y2="' + gy + '" stroke="#E2E8F0" stroke-width="1"/>';
                    });
                    const pts = years.map((y, i) => [xOf(i), yOf(st.yc[y] || 0)]);
                    let marks = '';
                    pts.forEach((p, i) => {
                        marks += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="4" fill="#fff" stroke="#D8501E" stroke-width="1.5"/>'
                            + '<text x="' + p[0] + '" y="' + (p[1] - 7) + '" text-anchor="middle" font-family="monospace" font-size="9" fill="#64748B">' + fmt(st.yc[years[i]] || 0) + '</text>'
                            + '<text x="' + p[0] + '" y="' + (H - 8) + '" text-anchor="middle" font-family="monospace" font-size="9" fill="#94A3B8">' + years[i] + '</text>';
                    });
                    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '">'
                        + '<rect width="' + W + '" height="' + H + '" fill="#fff"/>' + grid
                        + '<polyline points="' + pts.map((p) => p[0] + ',' + p[1]).join(' ') + '" fill="none" stroke="#D8501E" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>'
                        + marks + '</svg>';
                }
                function exportCsv() {
                    const st = lastStats || { sum: { 3: 0, 2: 0, 1: 0 }, total: 0, years: [], yc: {} };
                    const lines = ['area,total,' + fmt(st.total)];
                    [3, 2, 1].forEach((k) => lines.push('intensity_' + DN[k].label + ',' + st.sum[k]));
                    (st.years.length ? st.years : [0]).forEach((y) => lines.push('year_' + y + ',' + (st.yc[y] || 0)));
                    downloadBlob(new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' }), 'telf-stats.csv');
                }
                const dlTrigger = document.getElementById('dl-trigger');
                const dlMenu = document.getElementById('dl-menu');
                const dlDD = document.getElementById('dl-dd');
                if (dlTrigger && dlMenu) {
                    dlTrigger.addEventListener('click', (event) => {
                        event.stopPropagation();
                        dlMenu.classList.toggle('hidden');
                        dlTrigger.setAttribute('aria-expanded', dlMenu.classList.contains('hidden') ? 'false' : 'true');
                    });
                    dlMenu.addEventListener('click', (event) => {
                        const opt = event.target.closest('[data-dl]');
                        if (!opt) return;
                        const type = opt.dataset.dl;
                        const name = 'telf-' + type + '-' + Date.now() + '.png';
                        if (type === 'pie') downloadSvgAsPng(pieChartSvg(), name);
                        else if (type === 'bar') downloadSvgAsPng(barChartSvg(), name);
                        else if (type === 'line') downloadSvgAsPng(lineChartSvg(), name);
                        else if (type === 'csv') exportCsv();
                        dlMenu.classList.add('hidden');
                        dlTrigger.setAttribute('aria-expanded', 'false');
                    });
                }
                document.addEventListener('click', (event) => {
                    if (dlDD && !dlDD.contains(event.target)) {
                        if (dlMenu) dlMenu.classList.add('hidden');
                        if (dlTrigger) dlTrigger.setAttribute('aria-expanded', 'false');
                    }
                });

                boot();
            })();
        </script>
    </body>
</html>

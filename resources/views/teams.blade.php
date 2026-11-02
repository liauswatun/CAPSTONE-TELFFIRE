@extends('layouts.landing')

@section('title', ($lang === 'en' ? 'Teams' : 'Tim') . ' · TELF')

@php
    $members = \App\Models\TeamMember::active()->ordered()->get();
    $img = fn ($p) => $p ? (str_starts_with($p, 'http') ? $p : asset('storage/'.$p)) : null;

    // Tumpukan polaroid ala arsip pos lapangan: maksimal 5 kartu (batas dari
    // komponen aslinya — logika anti-tumpang-tindih disetel untuk 5). Anggota
    // selebihnya tampil sebagai daftar personel bernomor di bawah tumpukan.
    $stackMembers = $members->take(5)->values();
    $rosterMembers = $members->slice(5)->values();
    $stackRotations = [0, -2, 4, -4, 6];
@endphp

@section('content')
    {{-- Hero --}}
    <section class="relative min-h-[70vh] flex flex-col items-center justify-center text-center px-6 pt-20" style="background-image: linear-gradient(to bottom, rgba(23,16,9,0.6) 0%, rgba(23,16,9,0.55) 55%, rgba(23,16,9,0.85) 100%), url('{{ asset('img/APILANDING.jpg') }}'); background-size: cover; background-position: center;">
        <h1 class="font-fraunces text-5xl md:text-7xl text-cream font-normal leading-tight max-w-4xl">
            {!! $lang === 'en'
                ? 'The people behind<br><em class="not-italic">TELF.</em>'
                : 'Orang-orang di balik<br><em class="not-italic">TELF.</em>' !!}
        </h1>
        <p class="mt-6 text-white/70 text-base md:text-lg max-w-xl leading-relaxed">
            {{ $lang === 'en'
                ? 'Geospatial experts, analysts, and researchers turning satellite data into a traceable forest-monitoring tool.'
                : 'Tim geospasial, analis, dan peneliti yang mengubah data satelit menjadi alat bantu pemantauan hutan yang dapat ditelusuri.' }}
        </p>
    </section>

    {{-- Photo stack --}}
    <section class="max-w-7xl mx-auto px-6 py-24">
        @if ($members->isEmpty())
            @include('partials.empty-state', [
                'eyebrow' => $lang === 'en' ? 'Team members' : 'Anggota Tim',
                'title' => $lang === 'en' ? 'No data yet.' : 'Belum ada data.',
                'hint' => $lang === 'en'
                    ? 'Team member profiles have not been added yet. The team introduction is coming soon.'
                    : 'Profil anggota tim belum ditambahkan. Nantikan pengenalan tim segera.',
            ])
        @else
            {{-- Section header --}}
            <div class="text-center max-w-2xl mx-auto">
                <p class="font-jetbrains-mono text-[11px] uppercase tracking-[0.22em] text-sage">TELF · {{ $lang === 'en' ? 'Personnel file' : 'Berkas personel' }}</p>
                <h2 class="font-fraunces text-4xl md:text-5xl text-forest mt-2">{{ $lang === 'en' ? 'Meet the field crew' : 'Kenali kru lapangan' }}</h2>
                <p class="mt-3 text-bark/70 leading-relaxed">
                    {{ $lang === 'en'
                        ? 'The people verifying what the satellites see — from canopy flights to village interviews.'
                        : 'Orang-orang yang memverifikasi apa yang dilihat satelit — dari pengamatan udara hingga wawancara desa.' }}
                </p>
            </div>

            {{-- Stack stage: cards fan out on hover, click spins one to the front --}}
            <div id="photo-stack-stage" class="relative mx-auto mt-12 h-[40rem] max-w-5xl md:h-[42rem]">
                <div class="absolute left-1/2 top-1/2 h-80 w-64 -translate-x-1/2 -translate-y-1/2" style="perspective: 1400px;">
                    @foreach ($stackMembers as $i => $member)
                        @php
                            $initialTransform = sprintf('translateY(%srem) scale(%s) rotate(%sdeg)', $i * 0.5, 1 - $i * 0.05, $stackRotations[$i] ?? 0);
                        @endphp
                        <div
                            class="stack-card absolute inset-0 cursor-pointer select-none rounded-xl border border-forest/10 bg-white p-3 shadow-[0_18px_50px_rgba(23,16,9,0.16)]"
                            style="transform: {{ $initialTransform }}; z-index: {{ $stackMembers->count() - $i }};"
                            role="button"
                            tabindex="0"
                            aria-label="{{ $lang === 'en' ? 'Bring '.$member->name.' to the front' : 'Bawa '.$member->name.' ke depan' }}"
                            data-name="{{ $member->name }}"
                            data-role="{{ $member->roleFor($lang) }}"
                            data-bio="{{ $member->bioFor($lang) }}"
                            data-github="{{ $member->github_url }}"
                            data-instagram="{{ $member->instagram_url }}"
                        >
                            <div class="flex h-full w-full flex-col">
                                <div class="h-60 w-full overflow-hidden rounded-md bg-forest/10">
                                    @if ($img($member->photo))
                                        <img src="{{ $img($member->photo) }}" alt="{{ $member->name }}" class="h-full w-full object-cover" draggable="false">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-forest to-forest-dark font-fraunces text-5xl text-cream/80">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-1 flex-col items-center justify-center gap-1 px-1 text-center">
                                    <p class="font-fraunces text-lg italic leading-tight text-forest">{{ $member->name }}</p>
                                    @if ($member->roleFor($lang))
                                        <p class="font-jetbrains-mono text-[10px] uppercase tracking-[0.16em] text-sage">{{ $member->roleFor($lang) }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Dossier readout: detail of whichever card sits on top --}}
            @php $topMember = $stackMembers->first(); @endphp
            <div id="stack-readout" class="mx-auto mt-10 max-w-md text-center" style="transition: opacity .25s ease;">
                <p id="readout-num" class="font-jetbrains-mono text-[11px] uppercase tracking-[0.22em] text-lime">№001</p>
                <h3 id="readout-name" class="font-fraunces text-2xl md:text-3xl text-forest mt-2">{{ $topMember->name }}</h3>
                <p id="readout-role" class="font-jetbrains-mono text-[10px] uppercase tracking-[0.18em] text-sage mt-2 {{ $topMember->roleFor($lang) ? '' : 'hidden' }}">{{ $topMember->roleFor($lang) }}</p>
                <div id="readout-bio" class="article-body text-bark/70 text-sm leading-relaxed mt-3 text-left {{ $topMember->bioFor($lang) ? '' : 'hidden' }}">{!! $topMember->bioFor($lang) !!}</div>
                <div id="readout-social" class="flex items-center justify-center gap-3 mt-5" style="{{ ($topMember->github_url || $topMember->instagram_url) ? '' : 'display:none' }}">
                    <a id="readout-github" href="{{ $topMember->github_url ?: '#' }}" target="_blank" rel="noopener noreferrer" aria-label="GitHub"
                       class="inline-flex w-10 h-10 items-center justify-center rounded-full border border-forest/15 text-forest/70 hover:text-lime hover:border-lime/50 transition-colors"
                       style="{{ $topMember->github_url ? '' : 'display:none' }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                    </a>
                    <a id="readout-instagram" href="{{ $topMember->instagram_url ?: '#' }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
                       class="inline-flex w-10 h-10 items-center justify-center rounded-full border border-forest/15 text-forest/70 hover:text-lime hover:border-lime/50 transition-colors"
                       style="{{ $topMember->instagram_url ? '' : 'display:none' }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    </a>
                </div>
            </div>

            <p class="font-jetbrains-mono text-[11px] uppercase tracking-[0.18em] text-sage/80 text-center mt-8">
                {{ $lang === 'en' ? 'Fan the deck open, then click a card to bring it forward' : 'Buka tumpukannya, lalu klik kartu untuk membawanya ke depan' }}
            </p>

            {{-- Roster: members beyond the 5-card deck --}}
            @if ($rosterMembers->isNotEmpty())
                <div class="max-w-3xl mx-auto mt-20">
                    <p class="font-jetbrains-mono text-[11px] uppercase tracking-[0.22em] text-sage mb-4">{{ $lang === 'en' ? 'Also on the roster' : 'Juga terdaftar' }}</p>
                    <div class="divide-y divide-forest/10 border-t border-b border-forest/10">
                        @foreach ($rosterMembers as $j => $member)
                            <div class="grid grid-cols-[64px_1fr_auto] items-baseline gap-x-4 py-4">
                                <span class="font-jetbrains-mono text-[13px] tabular-nums text-sage">№{{ str_pad((string) ($j + 6), 3, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="font-fraunces text-lg text-forest leading-snug">{{ $member->name }}</h3>
                                <div class="flex items-center justify-end gap-2">
                                    <span class="font-jetbrains-mono text-[10px] uppercase tracking-[0.16em] text-sage/80">{{ $member->roleFor($lang) }}</span>
                                    @if ($member->github_url)
                                        <a href="{{ $member->github_url }}" target="_blank" rel="noopener noreferrer" aria-label="GitHub"
                                           class="inline-flex w-7 h-7 items-center justify-center rounded-full border border-forest/10 text-sage hover:text-lime hover:border-lime/40 transition-colors">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                                        </a>
                                    @endif
                                    @if ($member->instagram_url)
                                        <a href="{{ $member->instagram_url }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
                                           class="inline-flex w-7 h-7 items-center justify-center rounded-full border border-forest/10 text-sage hover:text-lime hover:border-lime/40 transition-colors">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <style>
                .stack-card { transition: transform .5s cubic-bezier(.22,.8,.24,1), scale .25s ease; will-change: transform; }
                #photo-stack-stage.is-spread .stack-card:not(.is-clicked):hover { scale: 1.08; }
                .stack-card.is-clicked { animation: photo-spin-y .7s ease-in-out; }
                .stack-card:focus-visible { outline: 2px solid #D8501E; outline-offset: 4px; }
                @keyframes photo-spin-y {
                    0% { transform: translate(0px, 0px) rotateY(0deg) scale(1); }
                    50% { transform: translate(0px, -12px) rotateY(180deg) scale(1.1); }
                    100% { transform: translate(0px, 0px) rotateY(360deg) scale(1); }
                }
                @media (prefers-reduced-motion: reduce) {
                    .stack-card { transition: none !important; animation: none !important; }
                }
            </style>
        @endif
    </section>
@endsection

@section('extra-scripts')
    <script>
        (function () {
            var stage = document.getElementById('photo-stack-stage');
            if (!stage) { return; }
            var cards = Array.prototype.slice.call(stage.querySelectorAll('.stack-card'));
            var n = cards.length;
            if (!n) { return; }

            var CARD_W = 256;
            var CARD_H = 320;
            var STACK_ROT = [0, -2, 4, -4, 6];
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            var topIndex = 0;
            var spread = false;
            var clickedIndex = null;
            var spreadTransforms = [];
            var readoutIndex = 0;

            var readout = document.getElementById('stack-readout');
            var roNum = document.getElementById('readout-num');
            var roName = document.getElementById('readout-name');
            var roRole = document.getElementById('readout-role');
            var roBio = document.getElementById('readout-bio');
            var roSocial = document.getElementById('readout-social');
            var roGithub = document.getElementById('readout-github');
            var roInstagram = document.getElementById('readout-instagram');

            function rand(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

            function pad3(i) { return ('00' + (i + 1)).slice(-3); }

            function updateReadout() {
                if (!readout || topIndex === readoutIndex) { return; }
                readoutIndex = topIndex;
                readout.style.opacity = '0';
                window.setTimeout(function () {
                    var d = cards[topIndex];
                    roNum.textContent = '№' + pad3(topIndex);
                    roName.textContent = d.getAttribute('data-name') || '';
                    roRole.textContent = d.getAttribute('data-role') || '';
                    roRole.classList.toggle('hidden', !d.getAttribute('data-role'));
                    roBio.innerHTML = d.getAttribute('data-bio') || '';
                    roBio.classList.toggle('hidden', !d.getAttribute('data-bio'));
                    var gh = d.getAttribute('data-github') || '';
                    var ig = d.getAttribute('data-instagram') || '';
                    roGithub.setAttribute('href', gh || '#');
                    roGithub.style.display = gh ? 'inline-flex' : 'none';
                    roInstagram.setAttribute('href', ig || '#');
                    roInstagram.style.display = ig ? 'inline-flex' : 'none';
                    roSocial.style.display = (gh || ig) ? 'flex' : 'none';
                    readout.style.opacity = '1';
                }, reduced ? 0 : 150);
            }

            function stackPositionOf(i) {
                var p = i - topIndex;
                if (p < 0) { p += n; }
                return p;
            }

            function stackedTransform(i) {
                var p = stackPositionOf(i);
                return 'translateY(' + (p * 0.5) + 'rem) scale(' + (1 - p * 0.05) + ') rotate(' + STACK_ROT[p] + 'deg)';
            }

            // Spread positions are measured in px from the stage box so the fan
            // stays on-canvas at any viewport width (the React original used
            // vw/vh, which throws cards off-screen on mobile).
            function generateSpread() {
                var rect = stage.getBoundingClientRect();
                var maxX = Math.max(20, rect.width / 2 - CARD_W / 2 - 24);
                var maxY = Math.max(14, rect.height / 2 - CARD_H / 2 - 20);
                var positions = [];
                cards.forEach(function () {
                    var pos, collision, retries = 0;
                    do {
                        collision = false;
                        pos = { x: rand(-maxX, maxX), y: rand(-maxY, maxY), r: rand(-25, 25) };
                        for (var k = 0; k < positions.length; k++) {
                            if (Math.abs(pos.x - positions[k].x) < CARD_W * 0.85 && Math.abs(pos.y - positions[k].y) < CARD_H * 0.85) { collision = true; break; }
                        }
                        retries++;
                    } while (collision && retries < 100);
                    positions.push(pos);
                });
                return positions.map(function (p) { return 'translate(' + p.x + 'px, ' + p.y + 'px) rotate(' + p.r + 'deg)'; });
            }

            function render() {
                cards.forEach(function (card, i) {
                    var p = stackPositionOf(i);
                    card.style.transform = spread ? spreadTransforms[i] : stackedTransform(i);
                    card.style.zIndex = i === clickedIndex ? 200 : (spread ? 100 : (i === topIndex ? n : n - p));
                    card.classList.toggle('is-clicked', i === clickedIndex);
                });
                stage.classList.toggle('is-spread', spread);
            }

            stage.addEventListener('mouseenter', function () {
                spreadTransforms = generateSpread();
                spread = true;
                render();
            });

            stage.addEventListener('mouseleave', function () {
                if (clickedIndex === null) {
                    spread = false;
                    render();
                }
            });

            function select(i) {
                if (spread) {
                    clickedIndex = i;
                    render();
                    window.setTimeout(function () {
                        spread = false;
                        topIndex = i;
                        clickedIndex = null;
                        render();
                        updateReadout();
                    }, reduced ? 0 : 700);
                } else {
                    topIndex = i;
                    render();
                    updateReadout();
                }
            }

            cards.forEach(function (card, i) {
                card.addEventListener('click', function () { select(i); });
                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        select(i);
                    }
                });
            });

            window.addEventListener('resize', function () {
                if (spread) {
                    spreadTransforms = generateSpread();
                    render();
                }
            });
        })();
    </script>
@endsection
php
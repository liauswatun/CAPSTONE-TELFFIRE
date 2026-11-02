@extends('layouts.landing')

@section('title', ($lang === 'en' ? 'About' : 'Tentang') . ' · TELF')

@php
    $about = \App\Models\About::where('is_active', true)->latest('id')->first();
    $img = fn ($p) => $p ? (str_starts_with($p, 'http') ? $p : asset('storage/'.$p)) : null;
@endphp

@section('content')
    @if (! $about)
        {{-- No fire monitoring content published yet --}}
        <section class="max-w-3xl mx-auto px-6 pt-40 pb-32">
            @include('partials.empty-state', [
                'eyebrow' => $lang === 'en' ? 'Fire monitoring' : 'Pemantauan api',
                'title' => $lang === 'en' ? 'No data yet.' : 'Belum ada data.',
                'hint' => $lang === 'en'
                    ? 'The fire monitoring page has not been published yet. Please check back for the latest fire updates.'
                    : 'Halaman pemantauan kebakaran belum dipublikasikan. Silakan kembali untuk pembaruan kebakaran terbaru.',
            ])
        </section>
    @else
        {{-- Hero --}}
        @php
            $heroBg = "linear-gradient(to bottom, rgba(23,16,9,0.6) 0%, rgba(23,16,9,0.55) 55%, rgba(23,16,9,0.85) 100%), url('" . asset('img/APILANDING.jpg') . "')";
        @endphp
        <section class="relative min-h-screen flex flex-col items-center justify-center text-center px-6 pt-20" style="background-image: {{ $heroBg }}; background-size: cover; background-position: center;">
            <p class="text-lime text-sm font-medium tracking-widest uppercase mb-4">{{ $lang === 'en' ? 'Fire monitoring' : '' }}</p>
            <h1 class="font-fraunces text-5xl md:text-7xl text-cream font-normal leading-tight max-w-4xl">
                {{ $lang === 'en' ? 'Sumatra fire insights and hotspot tracking' : 'Kawasan kebakaran Sumatera dan pelacakan titik api' }}
            </h1>
            <p class="mt-6 text-white/70 text-base md:text-lg max-w-xl leading-relaxed">
                {{ $lang === 'en'
                    ? 'TELFFIRE provides near-real-time fire maps, burned area detection, and regional fire intelligence for better response and planning.'
                    : 'TELFFIRE menyediakan peta kebakaran waktu nyata, deteksi area terbakar, dan intelijen kebakaran regional untuk respons dan perencanaan yang lebih baik.' }}
            </p>
            <div class="mt-8 flex flex-col sm:flex-row items-center gap-4">
                <a href="{{ route('map') }}" class="inline-flex items-center justify-center px-8 py-3 bg-lime text-ink text-sm font-semibold rounded-full hover:bg-[#E6652A] transition-colors">{{ $lang === 'en' ? 'Open Platform' : 'Buka Platform' }}</a>
                <a href="{{ route('home') }}#contact" class="inline-flex items-center justify-center px-8 py-3 border border-white/40 text-white text-sm font-semibold rounded-full hover:bg-white/10 transition-colors">{{ $lang === 'en' ? 'Contact us' : 'Hubungi Kami' }}</a>
            </div>

            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2">
                <div class="w-px h-12 bg-white/30"></div>
                <p class="text-white/40 text-xs tracking-widest uppercase">{{ $lang === 'en' ? 'Scroll' : 'Gulir' }}</p>
            </div>
        </section>

        {{-- Our story --}}
        <section class="max-w-7xl mx-auto px-6 py-24 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
                <p class="text-lime text-sm font-medium tracking-widest uppercase mb-4">{{ $lang === 'en' ? 'Fire narrative' : '' }}</p>
                <h2 class="font-fraunces text-4xl md:text-5xl font-normal text-forest leading-tight">{{ $lang === 'en' ? 'How TELFFIRE maps fire across Sumatra' : 'Bagaimana TELFFIRE memetakan kebakaran di seluruh Sumatera' }}</h2>
                <div class="article-body mt-6 text-bark/70 leading-relaxed space-y-8">
                    @if ($lang === 'en')
                        <p>TELFFIRE tracks burned areas and fire hotspots by combining satellite observations with automated analysis to provide a clearer picture of fire events across Sumatra.</p>
                        <p>Our platform emphasizes fire intelligence, helping users understand fire evolution, burned area extent, and where risk is highest.</p>
                        <p>The data is curated for Sumatra and designed to support responders, researchers, and policymakers in making informed fire management decisions.</p>
                    @else
                        <p>TELFFIRE melacak area terbakar dan titik api dengan menggabungkan pengamatan satelit dan analisis otomatis untuk memberikan gambaran lebih jelas tentang kejadian kebakaran di Sumatera.</p>
                        <p>Platform kami menekankan intelijen kebakaran, membantu pengguna memahami evolusi api, luas area terbakar, dan lokasi risiko tertinggi.</p>
                        <p>Data ini dikurasi untuk Sumatera dan dirancang untuk mendukung penanggap, peneliti, dan pembuat kebijakan dalam mengambil keputusan pengelolaan kebakaran yang lebih tepat.</p>
                    @endif
                </div>
            </div>

            <div class="relative">
                <img src="{{ asset('img/P.jpg') }}" alt="{{ $lang === 'en' ? 'Fire story' : 'Cerita kebakaran' }}" class="w-full h-[420px] md:h-[520px] object-cover rounded-[2rem]">
            </div>
        </section>

        {{-- Mission & Vision --}}
        <section class="max-w-7xl mx-auto px-6 py-24">
            <div class="text-center max-w-2xl mx-auto">
                <p class="text-lime text-sm font-medium tracking-widest uppercase mb-4">{{ $lang === 'en' ? 'Fire purpose' : '' }}</p>
                <h2 class="font-fraunces text-4xl md:text-5xl font-normal text-forest leading-tight">{{ $lang === 'en' ? 'Our mission and vision for fire monitoring' : 'Misi dan visi kami untuk pemantauan kebakaran' }}</h2>
            </div>

            <div class="mt-14 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-[2rem] border border-forest/15 bg-white p-10 shadow-[0_35px_80px_rgba(23,16,9,0.08)]">
                    <span class="inline-block rounded-full bg-lime/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-lime">{{ $lang === 'en' ? 'Mission' : 'Misi' }}</span>
                    <div class="article-body mt-5 text-bark/80 leading-relaxed">
                        @if ($lang === 'en')
                            <p>To deliver accurate and timely fire information across Sumatra, empowering communities and authorities to respond faster.</p>
                        @else
                            <p>Menyediakan informasi kebakaran yang akurat dan tepat waktu di seluruh Sumatera, memberdayakan masyarakat dan otoritas untuk merespons lebih cepat.</p>
                        @endif
                    </div>
                </div>
                <div class="rounded-[2rem] border border-forest/15 bg-white p-10 shadow-[0_35px_80px_rgba(23,16,9,0.08)]">
                    <span class="inline-block rounded-full bg-lime/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-lime">{{ $lang === 'en' ? 'Vision' : 'Visi' }}</span>
                    <div class="article-body mt-5 text-bark/80 leading-relaxed">
                        @if ($lang === 'en')
                            <p>To be the trusted source of fire data in Sumatra, helping protect landscapes and communities from wildfire impacts.</p>
                        @else
                            <p>Menjadi sumber data kebakaran terpercaya di Sumatera, membantu melindungi lanskap dan masyarakat dari dampak kebakaran lahan.</p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection

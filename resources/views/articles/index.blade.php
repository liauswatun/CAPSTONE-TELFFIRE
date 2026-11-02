@extends('layouts.landing')

@section('title', ($lang === 'en' ? 'Articles' : 'Artikel') . ' · TELF')

@section('content')
@php
    $toggle = function (string $to) use ($lang) {
        return request()->fullUrlWithQuery(['lang' => $to]);
    };
    $total = $articles->count();
@endphp

{{-- Station header --}}
<section class="bg-forest-dark text-white">
    <div class="max-w-7xl mx-auto px-6 pt-32 pb-16">
        <div class="flex items-end justify-between gap-6 flex-wrap">
            <div>
                <p class="font-jetbrains-mono text-[11px] uppercase tracking-[0.22em] text-lime">
                    Pos Sumatera · {{ $lang === 'en' ? 'dispatch log' : 'buku laporan' }}
                </p>
                <h1 class="font-fraunces text-4xl md:text-6xl text-cream font-normal leading-tight mt-3">
                    {{ $lang === 'en' ? 'Stories from the field' : 'Kabar dari lapangan' }}
                </h1>
                <p class="mt-4 text-white/60 max-w-xl leading-relaxed">
                    {{ $lang === 'en'
                        ? 'Reports, notes, and context on forest and fire monitoring across Sumatera.'
                        : 'Laporan, catatan, dan konteks seputar pemantauan hutan dan kebakaran di Sumatera.' }}
                </p>
            </div>

            <div class="text-right">
                <span class="font-jetbrains-mono text-[11px] uppercase tracking-[0.18em] text-white/40 tabular-nums">
                    {{ $lang === 'en' ? $total.' dispatches on file' : $total.' laporan tercatat' }}
                </span>
            </div>
        </div>
    </div>
</section>

{{-- The log --}}
<section class="max-w-7xl mx-auto px-6 py-14">
    @if ($articles->isEmpty())
        @include('partials.empty-state', [
            'eyebrow' => $lang === 'en' ? 'Dispatch log' : 'Buku laporan',
            'title' => $lang === 'en' ? 'No data yet.' : 'Belum ada data.',
            'hint' => $lang === 'en'
                ? 'Field reports will appear here once published.'
                : 'Kabar dari lapangan akan tampil di sini setelah dipublikasikan.',
        ])
    @else
        <div class="divide-y divide-forest/10">
            @foreach ($articles as $i => $article)
                @php
                    $hasLink = filled($article->link);
                    $url = $hasLink ? $article->link : route('articles.show', $article);
                    $num = '№' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
                    $date = $article->published_at?->format('Y-m-d');
                    $author = $article->author?->name;
                @endphp
                <a href="{{ $url }}" @if($hasLink) target="_blank" rel="noopener noreferrer" @endif
                   class="group grid grid-cols-[56px_1fr_auto] md:grid-cols-[120px_1fr_auto] items-baseline gap-x-4 md:gap-x-8 py-8 transition-colors">
                    <div class="flex flex-col gap-1.5">
                        <span class="font-jetbrains-mono text-[13px] tabular-nums text-sage group-hover:text-lime transition-colors">{{ $num }}</span>
                        <span class="hidden md:block font-jetbrains-mono text-[10px] uppercase tracking-[0.18em] text-sage/80">{{ $date }}</span>
                    </div>

                    <div class="min-w-0">
                        <div class="md:hidden font-jetbrains-mono text-[10px] uppercase tracking-[0.18em] text-sage mb-1.5">
                            {{ $date }}{{ $author ? ' · '.$author : '' }}{{ $hasLink ? ' · ↗' : '' }}
                        </div>
                        <h3 class="font-fraunces text-lg md:text-xl text-forest leading-snug">{{ $article->titleFor($lang) }}</h3>
                        @if ($article->excerptFor($lang))
                            <p class="text-sm text-bark/70 leading-relaxed mt-2 line-clamp-2">{{ $article->excerptFor($lang) }}</p>
                        @endif
                        <div class="hidden md:block font-jetbrains-mono text-[10px] uppercase tracking-[0.18em] text-sage/80 mt-2">
                            {{ $author ? $author : '' }}{{ $hasLink ? ($author ? ' · ' : '') . ($lang === 'en' ? 'external link' : 'tautan eksternal') : '' }}
                        </div>
                    </div>

                    <span class="self-center text-lg text-forest/40 group-hover:text-lime transition-all group-hover:translate-x-1">{{ $hasLink ? '↗' : '→' }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection

@extends('layouts.landing')

@section('title', 'FAQ · TELF')

@php
    $items = \App\Models\FaqItem::active()->ordered()->get();
@endphp

@section('content')
    {{-- Hero --}}
    <section class="relative min-h-[70vh] flex flex-col items-center justify-center text-center px-6 pt-20" style="background-image: linear-gradient(to bottom, rgba(23,16,9,0.6) 0%, rgba(23,16,9,0.55) 55%, rgba(23,16,9,0.85) 100%), url('{{ asset('img/APILANDING.jpg') }}'); background-size: cover; background-position: center;">
        <h1 class="font-fraunces text-5xl md:text-7xl text-cream font-normal leading-tight max-w-4xl">
            {!! $lang === 'en'
                ? 'Answers for every<br><em class="not-italic">question.</em>'
                : 'Pertanyaan Yang<br><em class="not-italic">Sering ditanyakan.</em>' !!}
        </h1>
        <p class="mt-6 text-white/70 text-base md:text-lg max-w-xl leading-relaxed">
            {{ $lang === 'en'
                ? 'What you need to know about TELF — the data, the map, and how to use the forest-loss monitoring platform.'
                : 'Hal-hal yang perlu Anda ketahui tentang TELF — data, peta, dan cara memanfaatkan platform pemantauan titik kebakaran.' }}
        </p>
    </section>

    {{-- FAQ accordion --}}
    <section id="faq" class="max-w-3xl mx-auto px-6 py-24">
        @if ($items->isEmpty())
            @include('partials.empty-state', [
                'eyebrow' => 'FAQ',
                'title' => $lang === 'en' ? 'No data yet.' : 'Belum ada data.',
                'hint' => $lang === 'en'
                    ? 'Frequently asked questions are not available yet. The FAQ list is coming soon.'
                    : 'Pertanyaan yang sering diajukan belum tersedia. Nantikan daftar FAQ segera.',
            ])
        @else
            <div class="space-y-4">
                @foreach ($items as $item)
                    <div class="faq-item bg-white rounded-2xl border border-forest/15 overflow-hidden">
                        <button type="button" class="faq-toggle w-full flex items-center justify-between gap-4 text-left px-6 md:px-8 py-6 cursor-pointer">
                            <span class="font-fraunces text-lg md:text-xl text-forest">{{ $item->questionFor($lang) }}</span>
                            <span class="faq-icon shrink-0 w-8 h-8 rounded-full bg-lime/20 text-forest flex items-center justify-center text-lg transition-transform duration-300">+</span>
                        </button>
                        <div class="faq-body px-6 md:px-8 pb-0 max-h-0 overflow-hidden transition-all duration-300">
                            <div class="article-body text-bark/70 leading-relaxed pb-6">{!! $item->answerFor($lang) !!}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

@section('extra-scripts')
    <script>
        (function () {
            document.querySelectorAll('.faq-item').forEach((item) => {
                const toggle = item.querySelector('.faq-toggle');
                const body = item.querySelector('.faq-body');
                const icon = item.querySelector('.faq-icon');
                toggle.addEventListener('click', () => {
                    const isOpen = item.classList.contains('faq-open');
                    document.querySelectorAll('.faq-item.faq-open').forEach((openItem) => {
                        openItem.classList.remove('faq-open');
                        openItem.querySelector('.faq-body').style.maxHeight = null;
                        openItem.querySelector('.faq-icon').style.transform = '';
                    });
                    if (!isOpen) {
                        item.classList.add('faq-open');
                        body.style.maxHeight = body.scrollHeight + 'px';
                        icon.style.transform = 'rotate(45deg)';
                    }
                });
            });
        })();
    </script>
@endsection

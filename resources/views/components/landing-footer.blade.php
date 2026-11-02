<footer class="bg-forest-dark text-white">
    <div class="max-w-7xl mx-auto px-6 pt-16 pb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-white/10">
            {{-- Brand --}}
            <div class="md:col-span-1">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center overflow-hidden">
                        <img src="{{ asset('img/LOGOFIRE.png') }}" alt="TELF Logo" class="w-full h-full object-cover">
                    </div>
                    <div class="leading-tight">
                        <div class="font-fraunces text-sm font-semibold">TELF</div>
                        <div class="font-jetbrains-mono text-[10px] uppercase tracking-[0.18em] text-lime/80 -mt-0.5">{{ $lang === 'en' ? 'Forest watch' : 'Pantauan hutan' }}</div>
                    </div>
                </div>
                <p class="text-white/50 text-sm leading-relaxed">
                    {{ $lang === 'en'
                        ? 'Satellite-based forest-loss monitoring for Sumatra, from province to village.'
                        : 'Pemantauan Kebakaran untuk Sumatera, dari provinsi hingga desa.' }}
                </p>
            </div>

            {{-- Explore links --}}
            <div>
                <h4 class="text-sm font-semibold text-lime mb-4 uppercase tracking-wider">{{ $lang === 'en' ? 'Explore' : 'Jelajahi' }}</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('about') }}" class="text-white/60 text-sm hover:text-white transition-colors">{{ $lang === 'en' ? 'About' : 'Tentang' }}</a></li>
                    <li><a href="{{ route('teams') }}" class="text-white/60 text-sm hover:text-white transition-colors">{{ $lang === 'en' ? 'Teams' : 'Tim' }}</a></li>
                    <li><a href="{{ route('faq') }}" class="text-white/60 text-sm hover:text-white transition-colors">FAQ</a></li>
                    <li><a href="{{ route('articles.index') }}" class="text-white/60 text-sm hover:text-white transition-colors">{{ $lang === 'en' ? 'Articles' : 'Artikel' }}</a></li>
                </ul>
            </div>

            {{-- Platform links --}}
            <div>
                <h4 class="text-sm font-semibold text-lime mb-4 uppercase tracking-wider">{{ $lang === 'en' ? 'Platform' : 'Platform' }}</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('map') }}" class="text-white/60 text-sm hover:text-white transition-colors">{{ $lang === 'en' ? 'Monitoring map' : 'Peta pemantauan' }}</a></li>
                    <li><a href="{{ route('home') }}#articles" class="text-white/60 text-sm hover:text-white transition-colors">{{ $lang === 'en' ? 'Latest reports' : 'Laporan terbaru' }}</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div>
                <h4 class="text-sm font-semibold text-lime mb-4 uppercase tracking-wider">{{ $lang === 'en' ? 'Contact' : 'Kontak' }}</h4>
                <ul class="space-y-2 text-white/60 text-sm">
                    <li>Sumatera, Indonesia</li>
                    <li class="pt-1">
                        <a href="https://www.instagram.com/tamaravta.df?igsh=a2toc2x6Y3F6Nnow&utm_source=qr" target="_blank" rel="noopener" class="hover:text-white transition-colors">{{ $lang === 'en' ? 'Get in touch' : 'Hubungi kami' }}</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-white/40 text-xs">
            <p>&copy; 2026 TELF. {{ $lang === 'en' ? 'All rights reserved.' : 'Hak cipta dilindungi.' }}</p>
            <div class="flex gap-6">
                <a href="{{ route('about') }}" class="hover:text-white/70 transition-colors">{{ $lang === 'en' ? 'Privacy' : 'Privasi' }}</a>
                <a href="{{ route('about') }}" class="hover:text-white/70 transition-colors">{{ $lang === 'en' ? 'Terms' : 'Ketentuan' }}</a>
            </div>
        </div>
    </div>
</footer>

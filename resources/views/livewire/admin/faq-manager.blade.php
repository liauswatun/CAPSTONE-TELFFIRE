<div>
<div class="max-w-4xl mx-auto px-5 md:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="font-jetbrains-mono text-[10px] uppercase tracking-[0.2em] text-[#5C6770]">Konten · FAQ</p>
            <h2 class="font-fraunces text-2xl text-[#14181A] mt-0.5">FAQ</h2>
        </div>
        <button type="button" wire:click="create"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1C3A14] text-cream text-sm font-semibold rounded-full hover:bg-[#0F2109] transition-colors">
            + Tambah item
        </button>
    </div>

    @if ($successMessage)
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3500)"
             class="mb-5 rounded-xl bg-[#1C3A14]/8 border border-[#1C3A14]/25 px-4 py-3 text-sm text-[#1C3A14] font-medium">
            {{ $successMessage }}
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($items as $item)
            <div class="bg-white rounded-2xl border border-[#E3E6E4] p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            @if ($item->category)
                                <span class="font-jetbrains-mono text-[10px] uppercase tracking-[0.15em] text-[#5C6770] bg-[#F4F6F5] px-2 py-0.5 rounded">{{ $item->category }}</span>
                            @endif
                            <span class="font-jetbrains-mono text-[10px] text-[#9AA3A0]">#{{ $item->sort_order }}</span>
                        </div>
                        <h3 class="font-fraunces text-base text-[#14181A]">{{ $item->question }}</h3>
                        <p class="text-sm text-[#5C6770] leading-relaxed mt-1.5 line-clamp-2">{{ $item->answer }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <button wire:click="toggleActive({{ $item->id }})" type="button"
                                class="inline-block w-9 h-5 rounded-full transition-colors {{ $item->is_active ? 'bg-[#2F7A3C]' : 'bg-[#14181A]/15' }}">
                            <span class="block w-4 h-4 bg-white rounded-full transition-transform {{ $item->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                        </button>
                        <div class="flex gap-3 text-xs">
                            <button wire:click="edit({{ $item->id }})" type="button" class="text-[#1C3A14]/80 hover:text-[#1C3A14] font-medium">Edit</button>
                            <button wire:click="confirmDelete({{ $item->id }})" type="button" class="text-[#C84A26]/80 hover:text-[#C84A26] font-medium">Hapus</button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-[#E3E6E4] p-12 text-center text-[#5C6770] text-sm">
                Belum ada item FAQ. Klik <strong class="text-[#1C3A14]">Tambah item</strong>.
            </div>
        @endforelse
    </div>
</div>

{{-- Modal form --}}
<div wire:show="showModal" wire:cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:keydown.escape.window="$set('showModal', false)">
        <div class="absolute inset-0 bg-[#0F2109]/55" wire:click="$set('showModal', false)"></div>
        <div class="relative w-full max-w-lg bg-white rounded-2xl border border-[#E3E6E4] shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E3E6E4]">
                <h3 class="font-fraunces text-lg text-[#14181A]">{{ $editingId ? 'Edit item' : 'Tambah item FAQ' }}</h3>
                <button wire:click="$set('showModal', false)" type="button" class="text-[#9AA3A0] hover:text-[#14181A] text-xl leading-none">&times;</button>
            </div>
            <form wire:submit="save" x-data="{ lang: 'id', setLang(next) { this.lang = next; this.$nextTick(() => window.dispatchEvent(new CustomEvent('tl-editor:activate', { detail: { id: next === 'id' ? 'answer' : 'answer_en' } }))); } }" class="px-6 py-5 space-y-4">
                <div class="flex items-center gap-2">
                    <button type="button" @click="setLang('id')"
                            :class="lang === 'id' ? 'bg-[#1C3A14] text-cream' : 'text-[#5C6770] hover:text-[#14181A] border border-[#E3E6E4]'"
                            class="font-jetbrains-mono text-[10px] uppercase tracking-[0.16em] rounded-full px-3 py-1.5 transition-colors">
                        Indonesia
                    </button>
                    <button type="button" @click="setLang('en')"
                            :class="lang === 'en' ? 'bg-[#1C3A14] text-cream' : 'text-[#5C6770] hover:text-[#14181A] border border-[#E3E6E4]'"
                            class="font-jetbrains-mono text-[10px] uppercase tracking-[0.16em] rounded-full px-3 py-1.5 transition-colors">
                        English
                    </button>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Pertanyaan *</label>
                    <input x-show="lang === 'id'" wire:model="question" type="text" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                    <input x-show="lang === 'en'" x-cloak wire:model="question_en" type="text" placeholder="Question (EN)"
                           class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                    @error('question') <p class="text-[#C84A26] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Jawaban *</label>
                    <div wire:ignore x-show="lang === 'id'"
                         x-data="tinymceField({ id: 'answer', model: @entangle('answer') })">
                        <textarea id="answer" rows="5" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none"></textarea>
                    </div>
                    <div wire:ignore x-show="lang === 'en'" x-cloak
                         x-data="tinymceField({ id: 'answer_en', model: @entangle('answer_en') })">
                        <textarea id="answer_en" rows="5" placeholder="Answer (EN)"
                                  class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none"></textarea>
                    </div>
                    @error('answer') <p class="text-[#C84A26] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Kategori</label>
                        <input wire:model="category" type="text" placeholder="Umum" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Urutan</label>
                        <input wire:model="sort_order" type="number" min="0" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="is_active" type="checkbox" class="accent-[#1C3A14] w-4 h-4"> Tampilkan di situs
                </label>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-sm text-[#5C6770] hover:text-[#14181A]">Batal</button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="px-5 py-2 bg-[#1C3A14] text-cream text-sm font-semibold rounded-full hover:bg-[#0F2109] transition-colors disabled:opacity-50">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

{{-- Delete confirm --}}
<div wire:show="confirmingDelete" wire:cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#0F2109]/55"></div>
        <div class="relative w-full max-w-sm bg-white rounded-2xl border border-[#E3E6E4] shadow-2xl p-6 text-center">
            <p class="font-fraunces text-lg text-[#14181A] mb-2">Hapus item ini?</p>
            <p class="text-sm text-[#5C6770] mb-5">Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex gap-3 justify-center">
                <button wire:click="$set('confirmingDelete', false)" type="button" class="px-4 py-2 text-sm text-[#5C6770] hover:text-[#14181A]">Batal</button>
                <button wire:click="delete" type="button" class="px-5 py-2 bg-[#C84A26] text-cream text-sm font-semibold rounded-full hover:bg-[#a83a1e] transition-colors">Hapus</button>
            </div>
        </div>
    </div>
</div>
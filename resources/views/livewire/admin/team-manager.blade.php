<div>
<div class="max-w-5xl mx-auto px-5 md:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="font-jetbrains-mono text-[10px] uppercase tracking-[0.2em] text-[#5C6770]">Konten · Tim</p>
            <h2 class="font-fraunces text-2xl text-[#14181A] mt-0.5">Tim</h2>
        </div>
        <button type="button" wire:click="create"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1C3A14] text-cream text-sm font-semibold rounded-full hover:bg-[#0F2109] transition-colors">
            + Tambah anggota
        </button>
    </div>

    @if ($successMessage)
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3500)"
             class="mb-5 rounded-xl bg-[#1C3A14]/8 border border-[#1C3A14]/25 px-4 py-3 text-sm text-[#1C3A14] font-medium">
            {{ $successMessage }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-[#E3E6E4] overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#F4F6F5] text-left font-jetbrains-mono text-[10px] uppercase tracking-[0.15em] text-[#5C6770]">
                <tr>
                    <th class="px-5 py-3">Anggota</th>
                    <th class="px-5 py-3 hidden md:table-cell">Peran</th>
                    <th class="px-5 py-3 text-center">Urut</th>
                    <th class="px-5 py-3 text-center">Aktif</th>
                    <th class="px-5 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#E3E6E4]">
                @forelse ($members as $member)
                    <tr class="hover:bg-[#F4F6F5]/60">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @if ($member->photo)
                                    <img src="{{ str_starts_with($member->photo, 'http') ? $member->photo : asset('storage/'.$member->photo) }}" alt="{{ $member->name }}" class="w-9 h-9 rounded-full object-cover border border-[#E3E6E4]">
                                @else
                                    <span class="w-9 h-9 rounded-full bg-[#1C3A14]/10 text-[#1C3A14] flex items-center justify-center text-xs font-medium">{{ strtoupper(substr($member->name, 0, 1)) }}</span>
                                @endif
                                <div class="min-w-0">
                                    <div class="font-medium text-[#14181A] truncate">{{ $member->name }}</div>
                                    <div class="text-xs text-[#5C6770] md:hidden truncate">{{ $member->role }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-[#5C6770]">{{ $member->role }}</td>
                        <td class="px-5 py-3 text-center font-jetbrains-mono text-[#5C6770]">{{ $member->sort_order }}</td>
                        <td class="px-5 py-3 text-center">
                            <button wire:click="toggleActive({{ $member->id }})" type="button"
                                    class="inline-block w-9 h-5 rounded-full transition-colors {{ $member->is_active ? 'bg-[#2F7A3C]' : 'bg-[#14181A]/15' }}">
                                <span class="block w-4 h-4 bg-white rounded-full transition-transform {{ $member->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button wire:click="edit({{ $member->id }})" type="button" class="text-[#1C3A14]/80 hover:text-[#1C3A14] text-xs font-medium mr-3">Edit</button>
                            <button wire:click="confirmDelete({{ $member->id }})" type="button" class="text-[#C84A26]/80 hover:text-[#C84A26] text-xs font-medium">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-[#5C6770] text-sm">Belum ada anggota tim. Klik <strong class="text-[#1C3A14]">Tambah anggota</strong>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal form --}}
<div wire:show="showModal" wire:cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:keydown.escape.window="$set('showModal', false)">
        <div class="absolute inset-0 bg-[#0F2109]/55" wire:click="$set('showModal', false)"></div>
        <div class="relative w-full max-w-lg bg-white rounded-2xl border border-[#E3E6E4] shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E3E6E4]">
                <h3 class="font-fraunces text-lg text-[#14181A]">{{ $editingId ? 'Edit anggota' : 'Tambah anggota' }}</h3>
                <button wire:click="$set('showModal', false)" type="button" class="text-[#9AA3A0] hover:text-[#14181A] text-xl leading-none">&times;</button>
            </div>
            <form wire:submit="save" x-data="{ lang: 'id', setLang(next) { this.lang = next; this.$nextTick(() => window.dispatchEvent(new CustomEvent('tl-editor:activate', { detail: { id: next === 'id' ? 'bio' : 'bio_en' } }))); } }" class="px-6 py-5 space-y-4">
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
                    <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Nama *</label>
                    <input wire:model="name" type="text" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                    @error('name') <p class="text-[#C84A26] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Peran / Jabatan</label>
                    <input x-show="lang === 'id'" wire:model="role" type="text" placeholder="Founder & Lead Designer" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                    <input x-show="lang === 'en'" x-cloak wire:model="role_en" type="text" placeholder="Role (EN)" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Bio</label>
                    <div wire:ignore x-show="lang === 'id'"
                         x-data="tinymceField({ id: 'bio', model: @entangle('bio') })">
                        <textarea id="bio" rows="3" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none"></textarea>
                    </div>
                    <div wire:ignore x-show="lang === 'en'" x-cloak
                         x-data="tinymceField({ id: 'bio_en', model: @entangle('bio_en') })">
                        <textarea id="bio_en" rows="3" placeholder="Bio (EN)"
                                  class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none"></textarea>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Foto</label>
                    @if ($photo)
                        <img src="{{ str_starts_with($photo, 'http') ? $photo : asset('storage/'.$photo) }}" alt="foto" class="w-24 h-24 object-cover rounded-lg mb-2 border border-[#E3E6E4]">
                    @endif
                    <input wire:model="photo_upload" type="file" accept="image/*"
                           class="text-sm text-[#5C6770] file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:bg-[#1C3A14]/10 file:text-[#1C3A14] file:text-xs file:font-medium file:cursor-pointer">
                    @error('photo_upload') <p class="text-[#C84A26] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#5C6770] mb-1.5">GitHub URL</label>
                        <input wire:model="github_url" type="url" placeholder="https://github.com/username" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                        @error('github_url') <p class="text-[#C84A26] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Instagram URL</label>
                        <input wire:model="instagram_url" type="url" placeholder="https://instagram.com/username" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                        @error('instagram_url') <p class="text-[#C84A26] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Urutan</label>
                        <input wire:model="sort_order" type="number" min="0" class="w-full rounded-lg border border-[#E3E6E4] bg-[#F4F6F5] px-3 py-2.5 text-sm focus:border-[#1C3A14] focus:ring-2 focus:ring-[#1C3A14]/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#5C6770] mb-1.5">Status</label>
                        <label class="flex items-center gap-2 h-[42px] text-sm">
                            <input wire:model="is_active" type="checkbox" class="accent-[#1C3A14] w-4 h-4"> Tampilkan di situs
                        </label>
                    </div>
                </div>
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
            <p class="font-fraunces text-lg text-[#14181A] mb-2">Hapus anggota ini?</p>
            <p class="text-sm text-[#5C6770] mb-5">Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex gap-3 justify-center">
                <button wire:click="$set('confirmingDelete', false)" type="button" class="px-4 py-2 text-sm text-[#5C6770] hover:text-[#14181A]">Batal</button>
                <button wire:click="delete" type="button" class="px-5 py-2 bg-[#C84A26] text-cream text-sm font-semibold rounded-full hover:bg-[#a83a1e] transition-colors">Hapus</button>
            </div>
        </div>
    </div>
</div>
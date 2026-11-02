<?php

namespace App\Livewire\Admin;

use App\Models\TeamMember;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Tim · Admin TELF')]
class TeamManager extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public bool $showModal = false;

    public bool $confirmingDelete = false;

    public ?int $deleteId = null;

    public ?string $name = '';

    public ?string $role = '';

    public ?string $bio = '';

    public ?string $role_en = '';

    public ?string $bio_en = '';

    public ?string $photo = '';

    public ?string $github_url = '';

    public ?string $instagram_url = '';

    public $photo_upload = null;

    public int $sort_order = 0;

    public bool $is_active = true;

    public ?string $successMessage = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $member = TeamMember::findOrFail($id);
        $this->editingId = $id;
        $this->name = $member->name;
        $this->role = (string) $member->role;
        $this->bio = (string) $member->bio;
        $this->role_en = (string) $member->role_en;
        $this->bio_en = (string) $member->bio_en;
        $this->photo = (string) $member->photo;
        $this->github_url = (string) $member->github_url;
        $this->instagram_url = (string) $member->instagram_url;
        $this->sort_order = (int) $member->sort_order;
        $this->is_active = (bool) $member->is_active;
        $this->photo_upload = null;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'role_en' => ['nullable', 'string', 'max:120'],
            'bio_en' => ['nullable', 'string', 'max:1000'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'photo_upload' => ['nullable', 'image', 'max:10048'],
        ]);

        $data = [
            'name' => $validated['name'],
            'role' => $validated['role'],
            'bio' => $validated['bio'],
            'role_en' => $validated['role_en'],
            'bio_en' => $validated['bio_en'],
            'github_url' => $validated['github_url'],
            'instagram_url' => $validated['instagram_url'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->photo_upload) {
            $data['photo'] = $this->photo_upload->store('team-photos', 'public');
        }

        if ($this->editingId) {
            TeamMember::find($this->editingId)->update($data);
            $this->successMessage = 'Anggota tim diperbarui.';
        } else {
            TeamMember::create($data);
            $this->successMessage = 'Anggota tim ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            TeamMember::find($this->deleteId)?->delete();
            $this->successMessage = 'Anggota tim dihapus.';
        }
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    public function toggleActive(int $id): void
    {
        $member = TeamMember::find($id);
        if ($member) {
            $member->update(['is_active' => ! $member->is_active]);
        }
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->role = '';
        $this->bio = '';
        $this->role_en = '';
        $this->bio_en = '';
        $this->photo = '';
        $this->github_url = '';
        $this->instagram_url = '';
        $this->photo_upload = null;
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $members = TeamMember::ordered()->get();

        return view('livewire.admin.team-manager', ['members' => $members])
            ->layout('layouts.admin', ['header' => 'Tim']);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\About;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Tentang · Admin TELF')]
class AboutManager extends Component
{
    use WithFileUploads;

    public ?string $hero_eyebrow = '';

    public ?string $hero_title = '';

    public ?string $hero_subtitle = '';

    public ?string $story_eyebrow = '';

    public ?string $story_title = '';

    public ?string $story_body = '';

    public ?string $mission = '';

    public ?string $vision = '';

    public ?string $hero_eyebrow_en = '';

    public ?string $hero_title_en = '';

    public ?string $hero_subtitle_en = '';

    public ?string $story_eyebrow_en = '';

    public ?string $story_title_en = '';

    public ?string $story_body_en = '';

    public ?string $mission_en = '';

    public ?string $vision_en = '';

    /** Upload sementara (Livewire\TemporaryUploadedFile|null). */
    public $hero_image_upload = null;

    public $story_image_upload = null;

    public ?int $aboutId = null;

    public ?string $hero_image = '';

    public ?string $story_image = '';

    public ?string $successMessage = null;

    public function mount(): void
    {
        $about = About::latest('id')->first();

        if ($about) {
            $this->fill($about->only([
                'hero_eyebrow', 'hero_title', 'hero_subtitle', 'hero_image',
                'story_eyebrow', 'story_title', 'story_body', 'story_image',
                'mission', 'vision',
                'hero_eyebrow_en', 'hero_title_en', 'hero_subtitle_en',
                'story_eyebrow_en', 'story_title_en', 'story_body_en',
                'mission_en', 'vision_en',
            ]));
            $this->aboutId = $about->id;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'hero_eyebrow' => ['nullable', 'string', 'max:80'],
            'hero_title' => ['nullable', 'string', 'max:200'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],
            'story_eyebrow' => ['nullable', 'string', 'max:80'],
            'story_title' => ['nullable', 'string', 'max:200'],
            'story_body' => ['nullable', 'string', 'max:2000'],
            'mission' => ['nullable', 'string', 'max:1000'],
            'vision' => ['nullable', 'string', 'max:1000'],
            'hero_eyebrow_en' => ['nullable', 'string', 'max:80'],
            'hero_title_en' => ['nullable', 'string', 'max:200'],
            'hero_subtitle_en' => ['nullable', 'string', 'max:500'],
            'story_eyebrow_en' => ['nullable', 'string', 'max:80'],
            'story_title_en' => ['nullable', 'string', 'max:200'],
            'story_body_en' => ['nullable', 'string', 'max:2000'],
            'mission_en' => ['nullable', 'string', 'max:1000'],
            'vision_en' => ['nullable', 'string', 'max:1000'],
            'hero_image_upload' => ['nullable', 'image', 'max:10240'],
            'story_image_upload' => ['nullable', 'image', 'max:10240'],
        ]);

        $data = collect($validated)->except(['hero_image_upload', 'story_image_upload'])->toArray();

        if ($this->hero_image_upload) {
            $data['hero_image'] = $this->hero_image_upload->store('about', 'public');
        }
        if ($this->story_image_upload) {
            $data['story_image'] = $this->story_image_upload->store('about', 'public');
        }

        if ($this->aboutId) {
            About::find($this->aboutId)->update($data);
        } else {
            $about = About::create(array_merge($data, ['is_active' => true]));
            $this->aboutId = $about->id;
        }

        $this->hero_image = $data['hero_image'] ?? $this->hero_image;
        $this->story_image = $data['story_image'] ?? $this->story_image;
        $this->hero_image_upload = null;
        $this->story_image_upload = null;

        $this->successMessage = 'Konten Tentang berhasil disimpan.';
    }

    public function render()
    {
        return view('livewire.admin.about-manager')->layout('layouts.admin', ['header' => 'Tentang']);
    }
}

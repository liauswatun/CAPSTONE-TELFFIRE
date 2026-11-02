<?php

use App\Livewire\Admin\AboutManager;
use App\Livewire\Admin\TeamManager;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Real padded JPEG — fake()->size() only changes the *reported* size, which
 * does not survive Livewire's temporary-upload round trip (the actual file is
 * only ~1 KB). Padding the fake's real temp file keeps the Testing\File API
 * (->$name) Livewire's test harness needs while making the size honest.
 */
function paddedImage(string $name, int $kilobytes): UploadedFile
{
    $file = UploadedFile::fake()->image($name);

    $path = $file->getRealPath();
    $handle = fopen($path, 'ab');
    $remaining = ($kilobytes * 1024) - filesize($path);
    $chunk = str_repeat("\0", 1024);
    while ($remaining > 0) {
        fwrite($handle, $chunk);
        $remaining -= 1024;
    }
    fclose($handle);

    return $file;
}

it('accepts a team photo up to 10 MB', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(TeamManager::class)
        ->set('name', 'Rina Kartika')
        ->set('photo_upload', paddedImage('foto-tim.jpg', 9216))
        ->call('save')
        ->assertHasNoErrors();
});

it('rejects a team photo larger than 10 MB', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(TeamManager::class)
        ->set('name', 'Rina Kartika')
        ->set('photo_upload', paddedImage('foto-besar.jpg', 11264))
        ->call('save')
        ->assertHasErrors(['photo_upload']);
});

it('accepts about images up to 10 MB', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(AboutManager::class)
        ->set('hero_image_upload', paddedImage('hero.jpg', 9216))
        ->call('save')
        ->assertHasNoErrors();
});

it('rejects about images larger than 10 MB', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(AboutManager::class)
        ->set('story_image_upload', paddedImage('cerita-besar.jpg', 11264))
        ->call('save')
        ->assertHasErrors(['story_image_upload']);
});

it('accepts an article featured image up to 10 MB', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.articles.store'), [
            'title' => 'Laporan Uji',
            'slug' => 'laporan-uji',
            'status' => 'draft',
            'featured_image' => paddedImage('sampul.jpg', 9216),
        ])
        ->assertSessionHasNoErrors();
});

it('rejects an article featured image larger than 10 MB', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.articles.store'), [
            'title' => 'Laporan Uji Besar',
            'slug' => 'laporan-uji-besar',
            'status' => 'draft',
            'featured_image' => paddedImage('sampul-besar.jpg', 11264),
        ])
        ->assertSessionHasErrors('featured_image');
});

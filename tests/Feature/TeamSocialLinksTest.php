<?php

use App\Livewire\Admin\TeamManager;
use App\Models\TeamMember;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    TeamMember::query()->delete();
});

it('saves github and instagram links from the admin form', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(TeamManager::class)
        ->set('name', 'Rina Kartika')
        ->set('github_url', 'https://github.com/rinakartika')
        ->set('instagram_url', 'https://instagram.com/rinakartika')
        ->call('save')
        ->assertHasNoErrors();

    $member = TeamMember::first();
    expect($member->github_url)->toBe('https://github.com/rinakartika')
        ->and($member->instagram_url)->toBe('https://instagram.com/rinakartika');
});

it('rejects invalid social urls', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(TeamManager::class)
        ->set('name', 'Rina Kartika')
        ->set('github_url', 'bukan-url')
        ->set('instagram_url', 'juga-bukan-url')
        ->call('save')
        ->assertHasErrors(['github_url', 'instagram_url']);

    expect(TeamMember::count())->toBe(0);
});

it('shows social icons on the public teams page when links exist', function () {
    TeamMember::create([
        'name' => 'Rina Kartika',
        'role' => 'Analis Geospasial',
        'sort_order' => 0,
        'github_url' => 'https://github.com/rinakartika',
        'instagram_url' => 'https://instagram.com/rinakartika',
    ]);

    $this->get('/teams')
        ->assertOk()
        ->assertSee('https://github.com/rinakartika')
        ->assertSee('https://instagram.com/rinakartika');
});

it('shows social icons for roster members beyond the 5-card deck', function () {
    for ($i = 0; $i < 5; $i++) {
        TeamMember::create(['name' => 'Anggota '.$i, 'sort_order' => $i]);
    }
    TeamMember::create([
        'name' => 'Anggota Roster',
        'sort_order' => 5,
        'github_url' => 'https://github.com/roster6',
    ]);

    $this->get('/teams')
        ->assertOk()
        ->assertSee('Anggota Roster')
        ->assertSee('https://github.com/roster6');
});

it('renders no social links when members have none', function () {
    TeamMember::create(['name' => 'Budi', 'role' => 'Analis', 'sort_order' => 0]);

    // Jangan pakai assertDontSee('github.com') polos — footer punya link
    // instagram situs. Yang dicek: tidak ada href sosial milik anggota.
    $this->get('/teams')
        ->assertOk()
        ->assertDontSee('href="https://github.com', false)
        ->assertDontSee('href="https://instagram.com', false);
});

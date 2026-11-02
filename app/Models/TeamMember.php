<?php

namespace App\Models;

use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Anggota tim — ditampilkan di halaman /teams.
 *
 * @property int $id
 * @property string $name
 * @property string|null $role
 * @property string|null $bio
 * @property string|null $photo
 * @property string|null $github_url
 * @property string|null $instagram_url
 * @property string|null $role_en
 * @property string|null $bio_en
 * @property int $sort_order
 * @property bool $is_active
 */
class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'role', 'bio', 'photo', 'sort_order', 'is_active',
        'role_en', 'bio_en', 'github_url', 'instagram_url',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function roleFor(?string $locale): ?string
    {
        return $this->localized('role', $locale);
    }

    public function bioFor(?string $locale): ?string
    {
        return $this->localized('bio', $locale);
    }

    /**
     * English when available and requested, else Indonesian — mirrors the
     * articles pattern so the public site falls back gracefully.
     */
    protected function localized(string $base, ?string $locale): ?string
    {
        if ($locale === 'en') {
            $en = $this->getAttribute($base.'_en');

            if (filled($en)) {
                return $en;
            }
        }

        return $this->getAttribute($base);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'position', 'active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        // Slug automático y único a partir del nombre cuando no se indica.
        static::saving(function (Category $category): void {
            if (blank($category->slug)) {
                $category->slug = $category->uniqueSlug();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    private function uniqueSlug(): string
    {
        $base = Str::slug($this->name) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function activitySubjectLabel(): string
    {
        return 'categoría';
    }
}

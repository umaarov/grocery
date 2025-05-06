<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'unit',
        'unit_value',
        'image',
        'is_featured',
        'is_new',
        'is_organic',
        'discount_percentage',
        'original_price',
        'stock',
        'category_id',
    ];

    protected $casts = [
        'price' => 'float',
        'unit_value' => 'float',
        'original_price' => 'float',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_organic' => 'boolean',
    ];

    protected $appends = ['average_rating', 'reviews_count'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function favoredBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
    }

    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }
}

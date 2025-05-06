<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PaymentMethod extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'type',
        'card_type',
        'card_number',
        'card_holder',
        'expiry_month',
        'expiry_year',
        'is_default'
    ];
    protected $hidden = [
        'card_number',
    ];
    protected $casts = [
        'is_default' => 'boolean',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function getMaskedCardNumberAttribute(): string
    {
        return 'XXXX XXXX XXXX ' . substr($this->card_number, -4);
    }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'shipping_charges',
        'total',
        'status',
        'shipping_method',
        'shipping_address_id',
        'shipping_name',
        'shipping_email',
        'shipping_phone',
        'shipping_address',
        'shipping_city',
        'shipping_zip_code',
        'shipping_country',
        'payment_method',
        'payment_details',
        'notes'
    ];
    protected $casts = [
        'subtotal' => 'float',
        'shipping_charges' => 'float',
        'total' => 'float',
        'payment_details' => 'json',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(ShippingAddress::class, 'shipping_address_id');
    }
}

<?php

namespace App\Models;

use App\Services\EmailVerificationService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'profile_picture',
        'google_id',
        'email_verified_at',
        'email_verification_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $verificationService = app(EmailVerificationService::class);

        if (empty($this->email_verification_token)) {
            $this->email_verification_token = $verificationService->generateToken();
            $this->save();
        }

        $verificationService->sendVerificationEmail($this, true);
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'email_verification_token' => null,
        ])->save();
    }

    public function getEmailForVerification()
    {
        return $this->email;
    }

    public function favorites()
    {
        return $this->belongsToMany(Product::class, 'favorites')
            ->withTimestamps();
    }

    public function hasFavorited(Product $product): bool
    {
        return $this->favorites()->where('product_id', $product->id)->exists();
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function shippingAddresses()
    {
        return $this->hasMany(ShippingAddress::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

}

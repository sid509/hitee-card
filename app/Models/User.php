<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Lab404\Impersonate\Models\Impersonate;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\HasMedia;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Impersonate, HasApiTokens, SoftDeletes, HasMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'avatar',
        'password',
        'status',
    ];

    public function getAvatarUrlAttribute()
    {
        return $this->getFirstMediaUrl('avatar', asset('assets/img/no_image.png'));
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function balanceIns()
    {
        return $this->hasMany(BalanceIn::class);
    }

    public function balanceOuts()
    {
        return $this->hasMany(BalanceOut::class);
    }

    public function balance()
    {
        $in = \App\Models\BalanceIn::where('user_id', $this->id)->where('status', 'completed')->sum('amount');
        // Also include balance added to cards specifically linked to this user (in case user_id is null on balance_in but card is linked)
        $cardIds = $this->cards()->pluck('id');
        $cardIn = \App\Models\BalanceIn::whereIn('card_id', $cardIds)->where('user_id', '!=', $this->id)->where('status', 'completed')->sum('amount');
        
        $out = \App\Models\BalanceOut::where('user_id', $this->id)->sum('amount');
        $cardOut = \App\Models\BalanceOut::whereIn('card_id', $cardIds)->where('user_id', '!=', $this->id)->sum('amount');
        
        return ($in + $cardIn) - ($out + $cardOut);
    }

    public function merchantIncomes()
    {
        return $this->hasMany(MerchantIncome::class, 'merchant_id');
    }

    public function merchantWithdrawals()
    {
        return $this->hasMany(MerchantWithdrawal::class, 'merchant_id');
    }

    public function merchantRoutes()
    {
        return $this->belongsToMany(Route::class, 'merchant_route', 'merchant_id', 'route_id')->withTimestamps();
    }

    public function fares()
    {
        return $this->belongsToMany(Fare::class, 'fare_merchant', 'merchant_id', 'fare_id')->withTimestamps();
    }

    public function merchantBalance()
    {
        $income = $this->merchantIncomes()->sum('amount');
        $withdrawal = $this->merchantWithdrawals()->where('status', 'completed')->sum('amount');
        return $income - $withdrawal;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function activeCard()
    {
        return $this->hasOne(Card::class)->where('is_currently_active', true);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'merchant_user', 'merchant_id', 'user_id')->withTimestamps();
    }

    public function merchants()
    {
        return $this->belongsToMany(User::class, 'merchant_user', 'user_id', 'merchant_id')->withTimestamps();
    }

    public function assignedBuses()
    {
        return $this->belongsToMany(Bus::class, 'bus_user', 'user_id', 'bus_id')->withTimestamps();
    }

    public function assignedParkings()
    {
        return $this->belongsToMany(Parking::class, 'parking_user', 'user_id', 'parking_id')->withTimestamps();
    }

    public function buses()
    {
        return $this->hasMany(Bus::class, 'merchant_id');
    }

    public function parkings()
    {
        return $this->hasMany(Parking::class, 'merchant_id');
    }

    public function hasRole(...$roles)
    {
        foreach ($roles as $role) {
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        }
        return false;
    }

    public function hasPermission($permission)
    {
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('slug', $permission)) {
                return true;
            }
        }
        return false;
    }
}

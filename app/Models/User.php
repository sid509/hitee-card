<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Impersonate, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

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
        $in = $this->balanceIns()->where('status', 'completed')->sum('amount');
        $out = $this->balanceOuts()->sum('amount');
        return $in - $out;
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
        return $this->hasMany(Route::class, 'merchant_id');
    }

    public function merchantFares()
    {
        return $this->hasMany(Fare::class, 'merchant_id');
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

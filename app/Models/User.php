<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Lab404\Impersonate\Models\Impersonate;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\HasMedia;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Impersonate, HasApiTokens, SoftDeletes, HasMedia;

    const STATUS_PENDING = -1;
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

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
        'preferred_language',
        'notification_enabled',
        'last_notified_at',
        'password',
        'status',
    ];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute()
    {
        return $this->getFirstMediaUrl('avatar', asset('assets/img/no_image.png'));
    }

    /**
     * Get the status as a human-readable string.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ((int)$this->status) {
            self::STATUS_ACTIVE => 'active',
            self::STATUS_INACTIVE => 'inactive',
            self::STATUS_PENDING => 'pending',
            default => 'unknown',
        };
    }

    /**
     * Ensure status is always stored as an integer.
     */
    public function setStatusAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['status'] = (int)$value;
        } else {
            $this->attributes['status'] = match ($value) {
                'active' => self::STATUS_ACTIVE,
                'inactive' => self::STATUS_INACTIVE,
                'pending' => self::STATUS_PENDING,
                default => self::STATUS_PENDING,
            };
        }
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
            'notification_enabled' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function fcmTokens()
    {
        return $this->hasMany(FcmToken::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function balanceIns()
    {
        $cardIds = $this->cards()->withTrashed()->pluck('id')->toArray();
        return BalanceIn::where(function($q) use ($cardIds) {
            $q->where('user_id', $this->id);
            if (!empty($cardIds)) {
                $q->orWhereIn('card_id', $cardIds);
            }
        });
    }

    public function balanceOuts()
    {
        $cardIds = $this->cards()->withTrashed()->pluck('id')->toArray();
        return BalanceOut::where(function($q) use ($cardIds) {
            $q->where('user_id', $this->id);
            if (!empty($cardIds)) {
                $q->orWhereIn('card_id', $cardIds);
            }
        });
    }

    public function taps()
    {
        $cardIds = $this->cards()->withTrashed()->pluck('id')->toArray();
        return Tap::where(function($q) use ($cardIds) {
            $q->where('user_id', $this->id);
            if (!empty($cardIds)) {
                $q->orWhereIn('card_id', $cardIds);
            }
        });
    }

    public function rides()
    {
        $cardIds = $this->cards()->withTrashed()->pluck('id')->toArray();
        return Ride::where(function($q) use ($cardIds) {
            $q->where('user_id', $this->id);
            if (!empty($cardIds)) {
                $q->orWhereIn('card_id', $cardIds);
            }
        });
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

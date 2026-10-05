<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Money;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'role',
    'status',
    'permissions',
    'salary_type',
    'salary_monthly_amount',
    'salary_commission_percent',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'permissions' => 'array',
            'salary_monthly_amount' => 'decimal:2',
            'salary_commission_percent' => 'decimal:2',
        ];
    }

    /**
     * Get the avatar initials: first + last initial of the name, falling back to the email.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) ($this->name ?: $this->email)), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        $initials = mb_substr($words[0], 0, 1);

        if (count($words) > 1) {
            $initials .= mb_substr($words[array_key_last($words)], 0, 1);
        }

        return mb_strtoupper($initials);
    }

    /**
     * Get the salary setup as one line (SPEC §8.20): "Monthly Rs. 50,000", "Commission 5%",
     * "Hybrid Rs. 30,000 + 2.5%" or "Not configured".
     */
    public function salarySetupLabel(): string
    {
        $percent = rtrim(rtrim(number_format((float) $this->salary_commission_percent, 2, '.', ''), '0'), '.').'%';

        return match ($this->salary_type) {
            'monthly' => 'Monthly '.Money::format($this->salary_monthly_amount),
            'commission' => "Commission {$percent}",
            'hybrid' => 'Hybrid '.Money::format($this->salary_monthly_amount)." + {$percent}",
            default => 'Not configured',
        };
    }

    /**
     * Get the salary payments issued to the user.
     *
     * @return HasMany<SalaryPayment, $this>
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    /**
     * Get the shifts the user has worked as cashier.
     *
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'cashier_id');
    }
}

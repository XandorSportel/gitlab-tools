<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const array WEEKDAY_NAMES = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    /** @return array{workdays: list<int>, completedLabels: list<string>, personalWorkdays: bool, personalLabels: bool} */
    public function burndownSettings(): array
    {
        return [
            'workdays' => $this->burndown_workdays ?? config('services.gitlab.workdays'),
            'completedLabels' => $this->burndown_completed_labels ?? config('services.gitlab.completed_labels'),
            'personalWorkdays' => $this->burndown_workdays !== null,
            'personalLabels' => $this->burndown_completed_labels !== null,
        ];
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
            'burndown_workdays' => 'array',
            'burndown_completed_labels' => 'array',
        ];
    }
}

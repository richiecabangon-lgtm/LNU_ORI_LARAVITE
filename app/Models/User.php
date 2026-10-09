<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'employee_id',
    'college_id',
    'department_id',
    'designation',
    'academic_rank',
    'orcid_url',
    'google_scholar_url',
    'institutional_profile_url',
    'profile_photo_path',
    'theme_preference',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? '/storage/'.ltrim($this->profile_photo_path, '/')
            : null;
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ResearchSubmission::class, 'faculty_id');
    }

    public function researchAuthorships(): HasMany
    {
        return $this->hasMany(ResearchSubmissionAuthor::class, 'faculty_id');
    }

    public function coauthoredSubmissions(): BelongsToMany
    {
        return $this->belongsToMany(ResearchSubmission::class, 'research_submission_authors', 'faculty_id', 'research_submission_id')
            ->withPivot(['name', 'email', 'institution', 'author_order', 'allocated_points'])
            ->withTimestamps();
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'coordinator_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'admin_id');
    }

    public function hasRole(UserRole|string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        $normalized = array_map(
            fn ($role) => $role instanceof UserRole ? $role->value : (string) $role,
            $roles,
        );

        return in_array($this->role->value, $normalized, true);
    }

    public function isFaculty(): bool
    {
        return $this->role === UserRole::Faculty;
    }

    public function isCoordinator(): bool
    {
        return $this->role === UserRole::Coordinator;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}

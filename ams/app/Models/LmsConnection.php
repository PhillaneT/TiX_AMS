<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class LmsConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'label',
        'base_url',
        'api_token_encrypted',
        'course_ids',
        'last_synced_at',
        'last_error',
        'last_fetched_courses',
    ];

    protected $casts = [
        'course_ids'     => 'array',
        'last_synced_at' => 'datetime',
        'last_fetched_courses' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function setApiToken(string $token): void
    {
        $this->api_token_encrypted = Crypt::encryptString($token);
    }

    public function getApiToken(): string
    {
        return Crypt::decryptString($this->api_token_encrypted);
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->base_url, '/');
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            'moodle' => 'Moodle',
            default  => ucfirst($this->provider),
        };
    }
}

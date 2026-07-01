<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StoreAdmin extends Model
{
    protected $fillable = ['name', 'email', 'password', 'token'];

    protected $hidden = ['password', 'token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function generateToken(): string
    {
        $plain = Str::random(64);
        $this->update(['token' => hash('sha256', $plain)]);
        return $plain;
    }

    public function revokeToken(): void
    {
        $this->update(['token' => null]);
    }

    public static function findByToken(string $plain): ?self
    {
        return static::where('token', hash('sha256', $plain))->first();
    }
}

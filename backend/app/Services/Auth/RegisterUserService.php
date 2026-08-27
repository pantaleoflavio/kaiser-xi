<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUserService
{
    /**
     * @param  array{name:string,email:string,password:string,privacy_acknowledged:bool}  $attributes
     * @return array{token:string,user:User}
     */
    public function execute(array $attributes): array
    {
        $user = User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'privacy_acknowledged_at' => now(),
        ]);

        $user->sendEmailVerificationNotification();

        $userRole = Role::query()->where('name', 'user')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$userRole->id]);

        return [
            'token' => $user->createToken('auth-token')->plainTextToken,
            'user' => $user->load('roles'),
        ];
    }
}

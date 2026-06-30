<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_login_and_fetch_current_user(): void
    {
        User::factory()->create([
            'name' => 'Demo Doctor',
            'email' => 'doctor@test.com',
            'password' => Hash::make('password'),
            'role' => 'doctor',
        ]);

        $response = $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/login', [
                'email' => 'doctor@test.com',
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'doctor@test.com')
            ->assertJsonPath('user.role', 'doctor')
            ->assertJsonPath('permissions.0', 'view_dashboard');

        $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'doctor@test.com')
            ->assertJsonPath('permissions.0', 'view_dashboard');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'doctor@test.com',
            'password' => Hash::make('password'),
            'role' => 'doctor',
        ]);

        $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/login', [
                'email' => 'doctor@test.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(422);
    }
}

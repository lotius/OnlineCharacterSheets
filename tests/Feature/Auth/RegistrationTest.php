<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_available_to_guests(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_guest_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Dungeon Delver',
            'email' => 'delver@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['name' => 'Dungeon Delver', 'email' => 'delver@example.com']);
        $this->assertTrue(Hash::check('password', auth()->user()->password));
        $this->assertTrue(auth()->user()->is_admin);
    }

    public function test_only_the_first_registered_user_becomes_an_administrator(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->post('/register', [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertFalse(User::query()->where('email', 'second@example.com')->sole()->is_admin);
    }

    public function test_registration_requires_a_unique_email_and_confirmed_password(): void
    {
        $this->post('/register', [
            'name' => 'Dungeon Delver',
            'email' => 'invalid',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }
}

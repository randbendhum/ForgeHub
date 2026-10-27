<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Developer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_register_logout_and_login(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Miguel Ferreira',
            'username' => 'miguel_fs25',
            'email' => 'miguel@forgemod.test',
            'bio' => 'Modder FS25',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('upload.create'));
        $this->assertAuthenticated();

        $this->post(route('logout'))->assertRedirect(route('catalogue.index'));
        $this->assertGuest();

        $loginResponse = $this->post(route('login.store'), [
            'email' => 'miguel@forgemod.test',
            'password' => 'secret1234',
        ]);

        $loginResponse->assertRedirect(route('inventory.index'));
        $this->assertAuthenticatedAs(Developer::where('username', 'miguel_fs25')->first());
    }
}


<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_authenticate_with_a_non_email_username(): void
    {
        $user = User::factory()->create(['email' => 'prueba']);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'prueba')
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_elegir_estudiante_pero_autenticarse_con_credenciales_de_personal_es_rechazado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        Volt::test('pages.auth.login')
            ->call('elegirCategoria', 'estudiante')
            ->set('form.email', $docente->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email')
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_elegir_personal_y_autenticarse_con_credenciales_de_docente_completa_el_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        Volt::test('pages.auth.login')
            ->call('elegirCategoria', 'personal')
            ->set('form.email', $docente->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($docente);
    }

    public function test_cambiar_categoria_vuelve_al_selector_y_limpia_el_formulario(): void
    {
        $component = Volt::test('pages.auth.login')
            ->call('elegirCategoria', 'estudiante')
            ->set('form.email', 'alguien@ceba.test');

        $component->assertSet('categoria', 'estudiante');

        $component->call('cambiarCategoria')
            ->assertSet('categoria', null)
            ->assertSet('form.email', '');
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}

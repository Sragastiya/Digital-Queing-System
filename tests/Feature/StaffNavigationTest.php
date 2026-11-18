<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_open_staff_login_but_cannot_open_the_dashboard(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('Masuk staf');
        $this->get('/staf/masuk')->assertOk()->assertViewIs('staff.login');
        $this->get('/staf')->assertRedirect(route('login'));
    }

    public function test_signed_in_staff_can_visit_customer_page_and_return_to_dashboard(): void
    {
        $staff = User::factory()->create();

        $this->post('/staf/masuk', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('staff.index'));
        $this->get('/staf')->assertOk()
            ->assertSee('href="'.route('home').'"', false);
        $this->get('/')->assertOk()
            ->assertSee('href="'.route('staff.index').'"', false)
            ->assertSee('Dasbor staf')
            ->assertDontSee('href="'.route('login').'"', false);
        $this->get('/staf')->assertOk()->assertViewIs('staff.dashboard');
        $this->assertAuthenticatedAs($staff);
    }

    public function test_opening_login_again_with_an_active_session_redirects_to_staff_dashboard(): void
    {
        $staff = User::factory()->create();

        $this->post('/staf/masuk', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('staff.index'));
        $this->get('/')->assertOk();
        $this->get('/staf/masuk')->assertRedirect(route('staff.index'));
        $this->get('/staf/masuk')->assertRedirect(route('staff.index'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_signed_in_login_post_also_redirects_without_changing_the_account(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->post('/staf/masuk', [])
            ->assertRedirect(route('staff.index'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_logout_restores_the_guest_login_flow(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/staf/keluar')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/staf/masuk')->assertOk()->assertViewIs('staff.login');
        $this->get('/')->assertSee('Masuk staf')
            ->assertSee('href="'.route('login').'"', false);
        $this->get('/staf')->assertRedirect(route('login'));
    }
}

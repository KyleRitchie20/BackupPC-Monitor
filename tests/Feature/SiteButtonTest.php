<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SiteButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required roles
        Role::create(['name' => 'admin', 'description' => 'Administrator']);
        Role::create(['name' => 'client', 'description' => 'Client']);
    }

    /** @test */
    public function admin_can_access_add_new_site_button()
    {
        // Create admin user
        $admin = User::factory()->create([
            'role_id' => 1, // admin role
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Add New Site');
        $response->assertSee('Manage Sites');
    }

    /** @test */
    public function admin_can_access_site_create_page()
    {
        // Create admin user
        $admin = User::factory()->create([
            'role_id' => 1, // admin role
        ]);

        $response = $this->actingAs($admin)->get('/sites/create');

        $response->assertStatus(200);
        $response->assertSee('Create New Site');
    }

    /** @test */
    public function admin_can_access_site_index_page()
    {
        // Create admin user
        $admin = User::factory()->create([
            'role_id' => 1, // admin role
        ]);

        $response = $this->actingAs($admin)->get('/sites');

        $response->assertStatus(200);
        $response->assertSee('Site List');
    }

    /** @test */
    public function client_cannot_access_add_new_site_button()
    {
        // Create client user
        $client = User::factory()->create([
            'role_id' => 2, // client role
        ]);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('Add New Site');
        $response->assertDontSee('Manage Sites');
    }

    /** @test */
    public function client_cannot_access_site_create_page()
    {
        // Create client user
        $client = User::factory()->create([
            'role_id' => 2, // client role
        ]);

        $response = $this->actingAs($client)->get('/sites/create');

        $response->assertRedirect('/dashboard');
    }
}

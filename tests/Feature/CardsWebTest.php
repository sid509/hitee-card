<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardsWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(
            Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin'])
        );

        $this->customer = User::factory()->create();
        $this->customer->roles()->attach(
            Role::firstOrCreate(['slug' => 'customers'], ['name' => 'Customers'])
        );

        $this->card = Card::create([
            'user_id' => $this->customer->id,
            'card_number' => '1122334455',
            'hwid' => 'CARD-HWID-WEB-01',
            'status' => 'ACTIVE',
            'is_currently_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/cards')->assertRedirect('/login');
        $this->get('/cards/' . $this->card->id)->assertRedirect('/login');
        $this->get('/card-reader/enroll')->assertRedirect('/login');
    }

    public function test_admin_can_view_index_and_subpages(): void
    {
        foreach (['/cards', '/cards/customers', '/cards/validators', '/cards/settlement', '/card-applications'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_view_card_detail_edit_and_reader_pages(): void
    {
        foreach ([
            '/cards/' . $this->card->id,
            '/cards/' . $this->card->id . '/edit',
            '/card-reader/enroll',
            '/card-reader/tap-test',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_admin_index_ajax_returns_datatables_payload(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/cards?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_customer_is_redirected_to_their_active_card(): void
    {
        $this->actingAs($this->customer)
            ->get('/cards')
            ->assertRedirect(route('cards.show', $this->card->id));
    }

    public function test_customer_can_view_own_card_but_not_others(): void
    {
        $this->actingAs($this->customer)
            ->get('/cards/' . $this->card->id)
            ->assertOk();

        $otherCard = Card::create([
            'card_number' => '9988776655',
            'hwid' => 'CARD-HWID-WEB-02',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->customer)
            ->get('/cards/' . $otherCard->id)
            ->assertForbidden();
    }

    public function test_admin_can_toggle_card_status(): void
    {
        $this->actingAs($this->admin)
            ->post('/cards/' . $this->card->id . '/toggle-status', ['_token' => csrf_token()], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertSame('INACTIVE', $this->card->fresh()->status);
    }
}

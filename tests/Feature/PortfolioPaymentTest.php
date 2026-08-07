<?php

namespace Tests\Feature;

use App\Models\PortfolioPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortfolioPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test public portfolio payment checkout initialization.
     */
    public function test_public_portfolio_payment_initialization(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_key']);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/mock-auth-url',
                    'access_code' => 'mock-code',
                    'reference' => 'mock-ref-123',
                ]
            ], 200),
        ]);

        $response = $this->post(route('portfolio.pay'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'amount' => 150.50,
            'currency' => 'GHS',
            'item' => 'company_profile',
            'description' => 'Need standard company profile document.',
        ]);

        $response->assertRedirect('https://checkout.paystack.com/mock-auth-url');

        $this->assertDatabaseHas('portfolio_payments', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'amount' => 150.50,
            'currency' => 'GHS',
            'item' => 'company_profile',
            'status' => 'pending',
        ]);
    }

    /**
     * Test public portfolio payment validation rules.
     */
    public function test_public_portfolio_payment_validation(): void
    {
        $response = $this->post(route('portfolio.pay'), [
            'name' => '',
            'email' => 'invalid-email',
            'amount' => -10,
            'item' => 'invalid_item',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'amount', 'item']);
    }

    /**
     * Test unsupported currency falls back to GHS with notice.
     */
    public function test_unsupported_currency_falls_back_to_ghs(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_key']);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/mock-auth-url',
                ]
            ], 200),
        ]);

        $response = $this->post(route('portfolio.pay'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'amount' => 100.00,
            'currency' => 'EUR', // Unsupported currency
            'item' => 'other',
            'custom_item' => 'Custom UI Wireframe',
        ]);

        $response->assertRedirect('https://checkout.paystack.com/mock-auth-url');
        $response->assertSessionHas('currency_notice');

        $this->assertDatabaseHas('portfolio_payments', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'currency' => 'GHS',
            'item' => 'other:Custom UI Wireframe',
        ]);
    }

    /**
     * Test payment verification callback handling.
     */
    public function test_payment_verification_callback_success(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_key']);

        $payment = PortfolioPayment::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'item' => 'design_brief',
            'amount' => 200.00,
            'currency' => 'USD',
            'reference' => 'CMIH-MOCK-REF',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/CMIH-MOCK-REF' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'reference' => 'CMIH-MOCK-REF',
                    'amount' => 20000,
                    'currency' => 'USD',
                    'gateway_response' => 'Successful',
                ]
            ], 200),
        ]);

        $response = $this->get(route('portfolio.pay.callback', ['reference' => 'CMIH-MOCK-REF']));

        $response->assertOk();
        $response->assertViewIs('pages.portfolio-payment-success');
        $response->assertSee('Payment Successful');

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->raw_response);
    }

    /**
     * Test payment verification callback failure.
     */
    public function test_payment_verification_callback_failure(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_key']);

        $payment = PortfolioPayment::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'item' => 'design_brief',
            'amount' => 200.00,
            'currency' => 'USD',
            'reference' => 'CMIH-MOCK-REF-FAIL',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/CMIH-MOCK-REF-FAIL' => Http::response([
                'status' => true,
                'message' => 'Verification failed',
                'data' => [
                    'status' => 'failed',
                    'reference' => 'CMIH-MOCK-REF-FAIL',
                    'gateway_response' => 'Declined',
                ]
            ], 200),
        ]);

        $response = $this->get(route('portfolio.pay.callback', ['reference' => 'CMIH-MOCK-REF-FAIL']));

        $response->assertRedirect(route('portfolio'));
        $response->assertSessionHas('error');

        $this->assertSame('failed', $payment->fresh()->status);
    }

    /**
     * Test strict super_admin access controls for payments dashboard.
     */
    public function test_superadmin_access_controls(): void
    {
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);

        $cvo = User::factory()->create([
            'status' => 'active',
            'access_role' => 'admin',
            'position_title' => 'CVO',
            'job_level' => 'CVO',
        ]);

        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'hr_admin',
        ]);

        $regularStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        // 1. Super Admin is allowed
        $this->actingAs($superAdmin)
            ->get(route('admin.portfolio-payments'))
            ->assertOk()
            ->assertSee('Portfolio Payments');

        // 2. CVO is forbidden
        $this->actingAs($cvo)
            ->get(route('admin.portfolio-payments'))
            ->assertForbidden();

        // 3. HR Manager is forbidden
        $this->actingAs($hrManager)
            ->get(route('admin.portfolio-payments'))
            ->assertForbidden();

        // 4. Regular staff is forbidden
        $this->actingAs($regularStaff)
            ->get(route('admin.portfolio-payments'))
            ->assertForbidden();
    }
}

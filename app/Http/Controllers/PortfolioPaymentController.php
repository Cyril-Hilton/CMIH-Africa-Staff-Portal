<?php

namespace App\Http\Controllers;

use App\Models\PortfolioPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PortfolioPaymentController extends Controller
{
    /**
     * Initialize payment with Paystack.
     */
    public function initialize(Request $request)
    {
        /** Allowed Paystack currencies. Enable more via your Paystack dashboard. */
        $allowedCurrencies = ['GHS', 'NGN', 'KES', 'ZAR', 'USD'];

        $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'currency'    => ['required', 'string'],   // Sanitized below — not hard-rejected
            'item'        => ['required', 'string', 'in:company_profile,design_brief,mockup,buy_a_plan,buy_a_catalogue,other'],
            'custom_item' => ['required_if:item,other', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Sanitize currency — fall back to GHS if not a supported Paystack currency
        $submittedCurrency = strtoupper(trim($request->currency));
        $currencyNotice    = null;

        if (!in_array($submittedCurrency, $allowedCurrencies)) {
            $currencyNotice    = "The currency '{$request->currency}' is not currently supported by Paystack. "
                               . 'Your payment has been defaulted to GHS (Ghana Cedis). '
                               . 'Accepted currencies: ' . implode(', ', $allowedCurrencies) . '.';
            $submittedCurrency = 'GHS';
        }

        // If the user chose "Other", store their custom description as the item
        $item = $request->item === 'other'
            ? 'other:' . trim($request->custom_item)
            : $request->item;

        $reference = 'CMIH-PAY-' . strtoupper(Str::random(10)) . '-' . time();

        // Store the transaction locally first as pending
        $payment = PortfolioPayment::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'item'        => $item,
            'description' => $request->description,
            'amount'      => $request->amount,
            'currency'    => $submittedCurrency,
            'reference'   => $reference,
            'status'      => 'pending',
        ]);

        $secretKey = config('services.paystack.secret_key');

        if (!$secretKey) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Paystack payment key is not configured. Please contact the administrator.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transaction/initialize', [
                'email'        => $request->email,
                'amount'       => (int) ($request->amount * 100), // Amount in subunits
                'currency'     => $submittedCurrency,
                'reference'    => $reference,
                'callback_url' => route('portfolio.pay.callback'),
                'metadata'     => [
                    'name'        => $request->name,
                    'item'        => $item,
                    'description' => $request->description,
                ],
            ]);

            if ($response->successful() && isset($response->json()['data']['authorization_url'])) {
                // Store currency notice in session so it shows on the callback/success page
                if ($currencyNotice) {
                    session()->flash('currency_notice', $currencyNotice);
                }
                return redirect()->away($response->json()['data']['authorization_url']);
            }

            $payment->update([
                'status' => 'failed',
                'raw_response' => json_encode($response->json()),
            ]);

            $errorMessage = $response->json()['message'] ?? 'Unable to initialize transaction with Paystack.';
            return redirect()->back()
                ->withInput()
                ->with('error', $errorMessage);

        } catch (\Exception $e) {
            $payment->update([
                'status' => 'failed',
                'raw_response' => json_encode(['exception_message' => $e->getMessage()]),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'An error occurred while communicating with Paystack: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment verification callback.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        if (!$reference) {
            return redirect()->route('portfolio')->with('error', 'No reference returned from Paystack.');
        }

        $payment = PortfolioPayment::where('reference', $reference)->firstOrFail();

        // If transaction already marked successful, skip verification
        if ($payment->status === 'success') {
            return view('pages.portfolio-payment-success', compact('payment'));
        }

        $secretKey = config('services.paystack.secret_key');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['data']['status']) && $result['data']['status'] === 'success') {
                    $payment->update([
                        'status' => 'success',
                        'raw_response' => json_encode($result),
                    ]);

                    return view('pages.portfolio-payment-success', compact('payment'));
                }

                $payment->update([
                    'status' => 'failed',
                    'raw_response' => json_encode($result),
                ]);

                return redirect()->route('portfolio')->with('error', 'Payment verification failed: ' . ($result['data']['gateway_response'] ?? 'Transaction was not successful'));
            }

            $payment->update([
                'status' => 'failed',
                'raw_response' => json_encode($response->json()),
            ]);

            return redirect()->route('portfolio')->with('error', 'Unable to verify payment with Paystack.');

        } catch (\Exception $e) {
            $payment->update([
                'status' => 'failed',
                'raw_response' => json_encode(['exception_message' => $e->getMessage()]),
            ]);

            return redirect()->route('portfolio')->with('error', 'Verification error: ' . $e->getMessage());
        }
    }
}

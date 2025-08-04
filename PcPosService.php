<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PcPosService
 *
 * This service is designed to interact with SepPay's PC POS (Point-of-Sale) system.
 * It handles the full transaction flow, including:
 * 1. Fetching an access token
 * 2. Receiving a unique identifier
 * 3. Inquiring the transaction status
 * 4. Sending a payment transaction
 * 5. Optionally cancelling a pending request
 *
 * You should configure the following in your config/seppay.php file:
 *
 * return [
 *     'client_secret' => env('SEPPAY_CLIENT_SECRET'),
 *     'username' => env('SEPPAY_USERNAME'),
 *     'password' => env('SEPPAY_PASSWORD'),
 *     'token_url' => env('SEPPAY_TOKEN_URL'),
 *     'identifier_url' => env('SEPPAY_IDENTIFIER_URL'),
 *     'inquiry_url' => env('SEPPAY_INQUIRY_URL'),
 *     'payment_url' => env('SEPPAY_PAYMENT_URL'),
 *     'terminal_id' => env('SEPPAY_TERMINAL_ID'),
 *     'scope' => env('SEPPAY_SCOPE', 'SepCentralPcPos openid'),
 * ];
 */
class PcPosService
{
    protected $token;
    protected $identifier;

    /**
     * Main method to process a payment.
     */
    public function processPayment($orderId, $amount)
    {
        try {
            // Step 1: Get Access Token
            $this->token = $this->getAccessToken();

            // Step 2: Receive Identifier
            $identifierResponse = Http::withToken($this->token)
                ->post(config('seppay.identifier_url'));

            if ($identifierResponse->failed()) {
                return $this->formatErrorResponse('ReciveIdentifier Failed', 'خطا در دریافت شناسه تراکنش', $identifierResponse);
            }

            $this->identifier = $identifierResponse->json()['Data']['Identifier'] ?? null;

            // Step 3: Check transaction status (inquiry)
            $inquiryResponse = Http::withToken($this->token)
                ->post(config('seppay.inquiry_url'), [
                    'Identifier' => $this->identifier,
                    'TerminalID' => config('seppay.terminal_id'),
                ]);

            if ($inquiryResponse->failed()) {
                // log or handle
            } elseif (
                isset($inquiryResponse->json()['IsSuccess']) &&
                $inquiryResponse->json()['IsSuccess'] === false &&
                $inquiryResponse->json()['ErrorCode'] === 30
            ) {
                return $this->cancelRequest();
            }

            // Step 4: Send Payment Request
            $paymentResponse = Http::withToken($this->token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(config('seppay.payment_url'), [
                    'TerminalID' => config('seppay.terminal_id'),
                    'Amount' => $amount,
                    'AccountType' => 0,
                    'Additional' => "تراکنش سفارش شماره {$orderId}",
                    'ResNum' => "ORDER-{$orderId}-" . now()->timestamp,
                    'Identifier' => $this->identifier,
                    'TotalAmount' => $amount,
                    'userNotifiable' => [
                        'FooterMessage' => null,
                        'PrintItems' => [[
                            'Item' => 'کد سفارش',
                            'Value' => "ORDER-{$orderId}",
                            'Alignment' => 0,
                            'ReceiptType' => 2,
                        ]],
                    ],
                    'TransactionType' => 0,
                ]);

            $data = $paymentResponse->json();

            return [
                'step' => 'Transaction Sent',
                'transaction_number' => $data['Data']['TraceNumber'] ?? null,
                'response' => array_merge($data ?? [], [
                    'ErrorDescription' => $paymentResponse->successful()
                        ? ($data['ErrorDescription'] ?? null)
                        : ($data['ErrorDescription'] ?? 'تراکنش کارتخوان ناموفق بود'),
                ]),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return $this->formatExceptionResponse('Connection Failed', $e);
        } catch (\Exception $e) {
            return $this->formatExceptionResponse('Exception', $e);
        }
    }

    /**
     * Cancel a pending request.
     */
    public function cancelRequest()
    {
        $cancelResponse = Http::withToken($this->token)
            ->post(config('seppay.inquiry_url'), [
                'Identifier' => $this->identifier,
                'TerminalID' => config('seppay.terminal_id'),
                'CancelPendingRequest' => true,
            ]);

        if ($cancelResponse->failed()) {
            return $this->formatErrorResponse('Cancel Pending Failed', 'خطا در لغو تراکنش در حال انتظار', $cancelResponse);
        }

        sleep(2); // Wait before retry

        return [
            'step' => 'Cancelled',
            'response' => ['message' => 'درخواست لغو با موفقیت انجام شد. لطفاً مجدداً تلاش کنید.'],
        ];
    }

    /**
     * Retrieve a new or cached access token.
     */
    protected function getAccessToken()
    {
        $tokenData = Cache::get('seppay_token_data');

        if ($tokenData && isset($tokenData['access_token']) && now()->lt($tokenData['expires_at'])) {
            return $tokenData['access_token'];
        }

        if ($tokenData && isset($tokenData['refresh_token'])) {
            $refresh = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . config('seppay.client_secret'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post(config('seppay.token_url'), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $tokenData['refresh_token'],
            ]);

            if ($refresh->successful()) {
                $data = $refresh->json();
                $this->cacheTokenData($data);
                return $data['access_token'];
            }
        }

        // Get new token
        $newToken = Http::asForm()->withHeaders([
            'Authorization' => 'Basic ' . config('seppay.client_secret'),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post(config('seppay.token_url'), [
            'grant_type' => 'password',
            'username' => config('seppay.username'),
            'password' => config('seppay.password'),
            'scope' => config('seppay.scope'),
        ]);

        if ($newToken->failed()) {
            throw new \Exception('Token request failed: ' . $newToken->body());
        }

        $data = $newToken->json();
        $this->cacheTokenData($data);

        return $data['access_token'];
    }

    /**
     * Cache the token data.
     */
    protected function cacheTokenData(array $data)
    {
        $expiresIn = $data['expires_in'] ?? 3600;

        Cache::put('seppay_token_data', [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($expiresIn - 60),
        ], now()->addSeconds($expiresIn - 60));
    }

    /**
     * Format a failed HTTP response.
     */
    protected function formatErrorResponse($step, $message, $response)
    {
        return [
            'step' => $step,
            'response' => array_merge($response->json() ?: [], [
                'ErrorDescription' => $message,
            ]),
        ];
    }

    /**
     * Format exceptions (network or general).
     */
    protected function formatExceptionResponse($step, \Throwable $e)
    {
        $message = 'خطایی در اتصال یا پردازش درخواست رخ داد. لطفاً دوباره تلاش کنید.';
        if (strpos($e->getMessage(), 'cURL error 28') !== false) {
            $message = 'مهلت زمانی اتصال به سرویس پرداخت به پایان رسید. لطفاً دوباره تلاش کنید.';
        }

        return [
            'step' => $step,
            'response' => ['ErrorDescription' => $message],
        ];
    }
}

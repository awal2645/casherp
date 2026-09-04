<?php

namespace App\Services;

use App\Business;
use App\User;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\Superadmin\Entities\SubscriptionPaymentAttempt;
use SimpleXMLElement;

class DpoPaymentService
{
    public function configured(): bool
    {
        return (bool) config('dpo.enabled')
            && in_array(config('dpo.mode'), ['test', 'live'], true)
            && ! empty(config('dpo.company_token'))
            && ctype_digit((string) config('dpo.service_type'))
            && ! empty(config('dpo.service_description'))
            && ! empty(config('app.key'));
    }

    public function createToken(
        SubscriptionPaymentAttempt $attempt,
        Business $business,
        User $user,
        string $redirectUrl,
        string $backUrl
    ): array {
        $this->assertConfigured();
        $amount = (float) $attempt->amount;
        if (abs(round($amount, 2) - $amount) > 0.00001) {
            throw ValidationException::withMessages([
                'payment' => 'DPO Pay supports subscription amounts with no more than two decimal places.',
            ]);
        }

        $document = $this->newDocument('createToken');
        $root = $document->documentElement;
        $transaction = $root->appendChild($document->createElement('Transaction'));
        $this->append($document, $transaction, 'PaymentAmount', number_format($amount, 2, '.', ''));
        $this->append($document, $transaction, 'PaymentCurrency', strtoupper($attempt->currency_code));
        $this->append($document, $transaction, 'CompanyRef', $attempt->reference);
        $this->append($document, $transaction, 'RedirectURL', $redirectUrl);
        $this->append($document, $transaction, 'BackURL', $backUrl);
        $this->append($document, $transaction, 'CompanyRefUnique', '1');
        $this->append($document, $transaction, 'PTL', (string) max(5, min(1440, (int) config('dpo.payment_time_limit_minutes', 30))));
        $this->append($document, $transaction, 'PTLtype', 'minutes');
        $this->append($document, $transaction, 'TransactionChargeType', '1');
        $this->append($document, $transaction, 'customerFirstName', $this->limited($user->first_name ?: $business->name, 100));
        $this->append($document, $transaction, 'customerLastName', $this->limited($user->last_name, 100));
        $this->append($document, $transaction, 'customerEmail', $this->limited($user->email, 255));
        $this->append($document, $transaction, 'TransactionSource', 'Website');

        $services = $root->appendChild($document->createElement('Services'));
        $service = $services->appendChild($document->createElement('Service'));
        $this->append($document, $service, 'ServiceType', (string) config('dpo.service_type'));
        $this->append($document, $service, 'ServiceDescription', $this->limited(config('dpo.service_description'), 100));
        $this->append($document, $service, 'ServiceDate', Carbon::now()->format('Y/m/d H:i'));

        $response = $this->request($document->saveXML());
        if (($response['Result'] ?? null) !== '000' || empty($response['TransToken'])) {
            throw ValidationException::withMessages([
                'payment' => 'DPO Pay could not start this checkout. Please try again or select another payment method.',
            ]);
        }

        return $response;
    }

    public function verifyToken(string $transactionToken, bool $markWebsiteVerified = false): array
    {
        $this->assertConfigured();
        if (! preg_match('/^[A-Za-z0-9-]{16,191}$/', $transactionToken)) {
            throw ValidationException::withMessages(['payment' => 'The DPO Pay transaction token is invalid.']);
        }

        $document = $this->newDocument('verifyToken');
        $root = $document->documentElement;
        $this->append($document, $root, 'TransactionToken', $transactionToken);
        $this->append($document, $root, 'VerifyTransaction', $markWebsiteVerified ? '1' : '0');

        return $this->request($document->saveXML());
    }

    public function paymentUrl(string $transactionToken): string
    {
        return rtrim((string) config('dpo.payment_url'), '?&').'?' . http_build_query(['ID' => $transactionToken]);
    }

    public function publicResult(array $response): array
    {
        return array_filter([
            'result' => $response['Result'] ?? null,
            'explanation' => $response['ResultExplanation'] ?? null,
            'transaction_reference' => $response['TransRef'] ?? null,
            'approval' => $response['TransactionApproval'] ?? null,
            'amount' => $response['TransactionAmount'] ?? null,
            'currency' => $response['TransactionCurrency'] ?? null,
            'fraud_alert' => $response['FraudAlert'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages([
                'gateway' => 'DPO Pay is not fully configured.',
            ]);
        }
    }

    private function newDocument(string $request): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->appendChild($document->createElement('API3G'));
        $this->append($document, $root, 'CompanyToken', (string) config('dpo.company_token'));
        $this->append($document, $root, 'Request', $request);

        return $document;
    }

    private function append(DOMDocument $document, \DOMNode $parent, string $name, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode((string) $value));
        $parent->appendChild($element);
    }

    private function request(string $xml): array
    {
        $endpoint = (string) config('dpo.api_endpoint');
        if (! str_starts_with($endpoint, 'https://')) {
            throw ValidationException::withMessages(['gateway' => 'DPO Pay requires an HTTPS API endpoint.']);
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/xml'])
                ->withBody($xml, 'application/xml; charset=utf-8')
                ->timeout(max(5, min(60, (int) config('dpo.http_timeout_seconds', 20))))
                ->retry(2, 250)
                ->post($endpoint)
                ->throw();
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'payment' => 'DPO Pay is temporarily unavailable. No subscription was activated.',
            ]);
        }

        return $this->parseResponse($response->body());
    }

    private function parseResponse(string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                throw ValidationException::withMessages(['payment' => 'DPO Pay returned an unreadable response.']);
            }

            return json_decode(json_encode($xml), true) ?: [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function limited($value, int $length): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }
}

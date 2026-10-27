<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

/**
 * Billing domain.
 *
 * Date-range parameters (startDate, endDate) on list and history methods
 * accept ISO-8601 date or timestamp strings. The server normalizes them to
 * full UTC-day boundaries: startDate -> start-of-UTC-day, endDate -> end-of-
 * UTC-day. The end day is always included; hourly resolution is not supported.
 */
class Billing
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get transaction history with optional filters
     *
     * @param array $params Filter parameters (groupId, subgroupId, brandId, campaignId, entryType, transactionType, startDate, endDate)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getTransactionHistory(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/billing/wallet/transactionHistory{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get payment history (wallet recharge transactions) with server-side pagination
     *
     * @param array $params Filter parameters (groupId, startDate, endDate, page, limit, timezone)
     * @param array $options Additional request options
     * @return array The response from the server with data and pagination metadata
     */
    public function getPaymentHistory(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/billing/wallet/paymentHistory{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get the wallet information for a specific group
     *
     * @param string $groupId The ID of the group
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getWallet(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/billing/wallet/{$safeGroupId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Update the wallet settings for a specific group
     *
     * @param string $groupId The ID of the group
     * @param array $walletData Wallet settings (autoRechargeEnabled, autoRechargeThreshold, autoRechargeToAmount, primaryPaymentMethodId, secondaryPaymentMethodId)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateWallet(string $groupId, array $walletData, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/billing/wallet/{$safeGroupId}", array_merge([
            'method' => 'PUT',
            'body' => $walletData,
        ], $options));
    }

    /**
     * Get the payment methods for a specific group
     *
     * @param string $groupId The ID of the group
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getPaymentMethods(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/billing/wallet/paymentMethods/{$safeGroupId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Add funds to a group's wallet
     *
     * @param string $groupId The ID of the group
     * @param int $amount The amount in microdollars (e.g., $10 = 10000000)
     * @param string|null $paymentMethodId Optional payment method ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function addFunds(string $groupId, int $amount, ?string $paymentMethodId = null, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'amount' => $amount]);
        $safeGroupId = rawurlencode($groupId);
        $body = ['amount' => $amount];
        if ($paymentMethodId !== null) {
            $body['paymentMethodId'] = $paymentMethodId;
        }
        return $this->client->request("/billing/wallet/addfunds/{$safeGroupId}", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Add a payment method to a group's wallet
     *
     * @param string $groupId The ID of the group
     * @param string $paymentMethodId The payment method ID to add
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function addPaymentMethod(string $groupId, string $paymentMethodId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'paymentMethodId' => $paymentMethodId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/billing/wallet/paymentMethod/{$safeGroupId}", array_merge([
            'method' => 'POST',
            'body' => ['paymentMethodId' => $paymentMethodId],
        ], $options));
    }

    /**
     * Remove a payment method from a group's wallet
     *
     * @param string $groupId The ID of the group
     * @param string $paymentMethodId The payment method ID to remove
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function removePaymentMethod(string $groupId, string $paymentMethodId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'paymentMethodId' => $paymentMethodId]);
        $safeGroupId = rawurlencode($groupId);
        $safePaymentMethodId = rawurlencode($paymentMethodId);
        return $this->client->request("/billing/wallet/paymentMethod/{$safeGroupId}/{$safePaymentMethodId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Get invoice details with optional filters
     *
     * @param array $params Filter parameters (groupId, subgroupId, startDate, endDate)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getInvoiceDetails(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/billing/invoiceDetails{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get the fee schedule for a specific group.
     * When the group is on a custom volume-tiered plan, the response also includes volumePricing: tiers, the current period's dates, count and minimum, ramp, and current rate (rates in microdollars per segment).
     *
     * @param string $groupId The ID of the group
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getFees(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/billing/fees/{$safeGroupId}", array_merge([
            'method' => 'GET',
        ], $options));
    }
}

<?php
// ====================================================================
// Eanne Boarding House - Overdue & Eviction Rule Engine Helper
// ====================================================================

/**
 * Calculates overdue metrics, minimum payment requirement, and eviction risk.
 *
 * Rules:
 * 1. Overdue months = floor(balance / monthly_rent) if balance > 0 and monthly_rent > 0.
 * 2. An admin can grant exemption months (e.g., 1 month extension) with a reason.
 *    Effective overdue months = max(0, overdue_months - overdue_exemption_months).
 * 3. If effective overdue months >= 3:
 *    - Tenant is subject to eviction warning / action.
 *    - Tenant MUST pay at least 50% of the total outstanding balance (or 100% if balance is small).
 *    - Payments under 50% are blocked until the required minimum is met.
 *
 * @param array $tenant Array containing balance, monthly_rent, overdue_exemption_months, overdue_exemption_reason, etc.
 * @return array
 */
function calculateOverdueStatus(array $tenant): array {
    $balance = (float)($tenant['balance'] ?? 0);
    $monthlyRent = (float)($tenant['monthly_rent'] ?? 0);
    $exemptionMonths = (int)($tenant['overdue_exemption_months'] ?? 0);
    $exemptionReason = trim($tenant['overdue_exemption_reason'] ?? '');
    $exemptionGrantedAt = $tenant['overdue_exemption_granted_at'] ?? null;

    if ($monthlyRent <= 0) {
        $monthlyRent = 3500.00; // fallback standard rent
    }

    $rawOverdueMonths = 0;
    if ($balance > 0) {
        $rawOverdueMonths = (int)floor($balance / $monthlyRent);
    }

    $effectiveOverdueMonths = max(0, $rawOverdueMonths - $exemptionMonths);

    // Rule threshold: 3 months
    $isEvictionRisk = ($balance > 0 && $effectiveOverdueMonths >= 3);
    $isExempted = ($exemptionMonths > 0 && $rawOverdueMonths >= 3 && $effectiveOverdueMonths < 3);

    // Minimum required payment:
    // If at eviction risk (3+ effective months), 50% of current outstanding balance is mandatory
    $minPaymentRequired = 1.00;
    if ($isEvictionRisk) {
        $minPaymentRequired = round($balance * 0.50, 2);
    }

    return [
        'balance' => $balance,
        'monthly_rent' => $monthlyRent,
        'raw_overdue_months' => $rawOverdueMonths,
        'exemption_months' => $exemptionMonths,
        'exemption_reason' => $exemptionReason,
        'exemption_granted_at' => $exemptionGrantedAt,
        'effective_overdue_months' => $effectiveOverdueMonths,
        'is_eviction_risk' => $isEvictionRisk,
        'is_exempted' => $isExempted,
        'min_payment_required' => $minPaymentRequired,
        'required_percent' => 50,
    ];
}

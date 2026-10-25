<?php

namespace App\Services;

/**
 * Computes Tanzanian statutory payroll deductions.
 *
 * PAYE brackets (TRA 2024/2025, monthly gross in TZS):
 *   0 – 270,000        → 0%
 *   270,001 – 520,000  → 8%  on excess
 *   520,001 – 760,000  → 20% on excess
 *   760,001 – 1,000,000 → 25% on excess
 *   > 1,000,000        → 30% on excess
 *
 * NSSF, WCF, SDL rates are configurable (defaults match current law).
 */
class TanzaniaTaxService
{
    public function __construct(
        private float $nssfEmployeePct = 10.0,
        private float $nssfEmployerPct = 10.0,
        private float $wcfPct = 0.50,
        private float $sdlPct = 4.50,
    ) {}

    public static function fromRates(array $rates): self
    {
        return new self(
            nssfEmployeePct: (float) ($rates['nssf_employee_pct'] ?? 10.0),
            nssfEmployerPct: (float) ($rates['nssf_employer_pct'] ?? 10.0),
            wcfPct: (float) ($rates['wcf_pct'] ?? 0.50),
            sdlPct: (float) ($rates['sdl_pct'] ?? 4.50),
        );
    }

    /**
     * Compute all statutory deductions for a payroll entry.
     *
     * @param  int  $grossCents  basic + allowances (in cents)
     * @return array{
     *   paye_cents: int,
     *   nssf_employee_cents: int,
     *   nssf_employer_cents: int,
     *   wcf_cents: int,
     *   sdl_cents: int,
     *   total_employee_deductions_cents: int,
     *   total_employer_deductions_cents: int,
     * }
     */
    public function compute(int $grossCents): array
    {
        $paye = $this->computePaye($grossCents);
        $nssfEmployee = (int) round($grossCents * $this->nssfEmployeePct / 100);
        $nssfEmployer = (int) round($grossCents * $this->nssfEmployerPct / 100);
        $wcf = (int) round($grossCents * $this->wcfPct / 100);
        $sdl = (int) round($grossCents * $this->sdlPct / 100);
        $employeeTotal = $paye + $nssfEmployee;
        $employerTotal = $nssfEmployer + $wcf + $sdl;

        return [
            'paye_cents' => $paye,
            'nssf_employee_cents' => $nssfEmployee,
            'nssf_employer_cents' => $nssfEmployer,
            'wcf_cents' => $wcf,
            'sdl_cents' => $sdl,
            'total_employee_deductions_cents' => $employeeTotal,
            'total_employer_deductions_cents' => $employerTotal,
        ];
    }

    /** PAYE in cents, computed from gross in cents. */
    public function computePaye(int $grossCents): int
    {
        $gross = $grossCents / 100; // work in TZS

        if ($gross <= 270_000) {
            return 0;
        }
        if ($gross <= 520_000) {
            return (int) round(($gross - 270_000) * 0.08 * 100);
        }
        if ($gross <= 760_000) {
            return (int) round((250_000 * 0.08 + ($gross - 520_000) * 0.20) * 100);
        }
        if ($gross <= 1_000_000) {
            return (int) round((250_000 * 0.08 + 240_000 * 0.20 + ($gross - 760_000) * 0.25) * 100);
        }

        return (int) round((250_000 * 0.08 + 240_000 * 0.20 + 240_000 * 0.25 + ($gross - 1_000_000) * 0.30) * 100);
    }
}

<?php

namespace App\Services\Leads;

use App\Models\Lead;

class LeadQualityService
{
    /**
     * @return array{
     *     score: int,
     *     grade: string,
     *     grade_label: string,
     *     email: array{status: string, label: string, passed: ?bool, detail: ?string, fraud_score: ?int},
     *     hlr: array{status: string, label: string, passed: ?bool, detail: ?string, fraud_score: ?int},
     *     ip: array{status: string, label: string, passed: ?bool, detail: ?string, fraud_score: ?int},
     *     completeness: array{status: string, label: string, filled: int, total: int, missing: list<string>}
     * }
     */
    public static function analyze(?array $metadata, ?array $fieldData = []): array
    {
        $metadata = $metadata ?? [];
        $fieldData = $fieldData ?? [];

        $email = self::emailStatus($metadata);
        $hlr = self::hlrStatus($metadata);
        $ip = self::ipStatus($metadata);
        $completeness = self::completenessStatus($fieldData);

        $score = isset($metadata['quality_score'])
            ? (int) $metadata['quality_score']
            : self::scoreFromChecks($email, $hlr, $ip, $completeness, $metadata);

        $score = max(0, min(100, $score));
        $grade = self::grade($score);

        return [
            'score' => $score,
            'grade' => $grade,
            'grade_label' => self::gradeLabel($grade),
            'email' => $email,
            'hlr' => $hlr,
            'ip' => $ip,
            'completeness' => $completeness,
        ];
    }

    public static function analyzeLead(Lead $lead): array
    {
        return self::analyze($lead->metadata ?? [], $lead->field_data ?? []);
    }

    public static function computeScore(Lead $lead): int
    {
        $metadata = $lead->metadata ?? [];

        return self::scoreFromChecks(
            self::emailStatus($metadata),
            self::hlrStatus($metadata),
            self::ipStatus($metadata),
            self::completenessStatus($lead->field_data ?? []),
            $metadata,
        );
    }

    /**
     * @param  array{status: string, passed: ?bool, fraud_score: ?int}  $email
     * @param  array{status: string, passed: ?bool, fraud_score: ?int}  $hlr
     * @param  array{status: string, passed: ?bool, fraud_score: ?int}  $ip
     * @param  array{missing: list<string>}  $completeness
     */
    public static function scoreFromChecks(array $email, array $hlr, array $ip, array $completeness, array $metadata = []): int
    {
        $score = 100;

        if (isset($metadata['field_validation']) && ! ($metadata['field_validation']['passed'] ?? true)) {
            $score -= 20;
        }

        if ($email['passed'] === false) {
            $score -= 30;
        }

        if ($hlr['passed'] === false) {
            $score -= 25;
        }

        if ($ip['passed'] === false) {
            $score -= 20;
        }

        $score -= count($completeness['missing'] ?? []) * 5;

        if ($email['passed'] === true) {
            $score += 5;
        }

        if ($hlr['passed'] === true) {
            $score += 5;
        }

        if ($ip['passed'] === true) {
            $score += 5;
        }

        foreach ([$email, $hlr, $ip] as $check) {
            $fraudScore = $check['fraud_score'] ?? null;
            if ($check['passed'] !== false && is_int($fraudScore) && $fraudScore >= 75) {
                $score -= 5;
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * @deprecated Use scoreFromChecks()
     */
    public static function computeScoreFromComponents(array $email, array $hlr, array $completeness): int
    {
        return self::scoreFromChecks($email, $hlr, self::ipStatus([]), $completeness);
    }

    public static function grade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'excellent',
            $score >= 60 => 'good',
            $score >= 40 => 'fair',
            default => 'poor',
        };
    }

    public static function gradeLabel(string $grade): string
    {
        return match ($grade) {
            'excellent' => 'Excellent',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor',
            default => 'Unknown',
        };
    }

    /**
     * @return array{status: string, label: string, passed: ?bool, detail: ?string, fraud_score: ?int}
     */
    private static function emailStatus(array $metadata): array
    {
        if (! isset($metadata['email_validation'])) {
            return self::uncheckedStatus();
        }

        $validation = $metadata['email_validation'];
        $passed = (bool) ($validation['passed'] ?? false);

        return [
            'status' => $passed ? 'passed' : 'failed',
            'label' => $passed ? 'Deliverable' : 'Failed',
            'passed' => $passed,
            'detail' => $validation['status'] ?? $validation['check'] ?? null,
            'fraud_score' => isset($validation['fraud_score']) ? (int) $validation['fraud_score'] : null,
        ];
    }

    /**
     * @return array{status: string, label: string, passed: ?bool, detail: ?string, fraud_score: ?int}
     */
    private static function hlrStatus(array $metadata): array
    {
        if (! isset($metadata['hlr_validation'])) {
            return self::uncheckedStatus();
        }

        $validation = $metadata['hlr_validation'];
        $passed = (bool) ($validation['passed'] ?? false);

        return [
            'status' => $passed ? 'passed' : 'failed',
            'label' => $passed ? 'Reachable' : 'Unreachable',
            'passed' => $passed,
            'detail' => $validation['status'] ?? $validation['check'] ?? null,
            'fraud_score' => isset($validation['fraud_score']) ? (int) $validation['fraud_score'] : null,
        ];
    }

    /**
     * @return array{status: string, label: string, passed: ?bool, detail: ?string, fraud_score: ?int}
     */
    private static function ipStatus(array $metadata): array
    {
        if (! isset($metadata['ip_validation'])) {
            return self::uncheckedStatus();
        }

        $validation = $metadata['ip_validation'];
        $passed = (bool) ($validation['passed'] ?? false);
        $fraudScore = isset($validation['fraud_score']) ? (int) $validation['fraud_score'] : null;

        $label = match (true) {
            ! $passed && ($validation['vpn'] ?? false) => 'VPN detected',
            ! $passed && ($validation['proxy'] ?? false) => 'Proxy detected',
            ! $passed && ($validation['tor'] ?? false) => 'Tor detected',
            $passed => 'Clean',
            default => 'High risk',
        };

        return [
            'status' => $passed ? 'passed' : 'failed',
            'label' => $label,
            'passed' => $passed,
            'detail' => $validation['status'] ?? $validation['check'] ?? null,
            'fraud_score' => $fraudScore,
        ];
    }

    /**
     * @return array{status: string, label: string, passed: ?bool, detail: ?string, fraud_score: ?int}
     */
    private static function uncheckedStatus(): array
    {
        return [
            'status' => 'unchecked',
            'label' => 'Not checked',
            'passed' => null,
            'detail' => null,
            'fraud_score' => null,
        ];
    }

    /**
     * @return array{status: string, label: string, filled: int, total: int, missing: list<string>}
     */
    private static function completenessStatus(array $fieldData): array
    {
        $required = ['email', 'phone1', 'zipcode', 'lastname'];
        $missing = [];

        foreach ($required as $field) {
            if (blank($fieldData[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        $filled = count($required) - count($missing);

        return [
            'status' => $missing === [] ? 'complete' : 'partial',
            'label' => $missing === [] ? 'Complete' : count($missing).' missing',
            'filled' => $filled,
            'total' => count($required),
            'missing' => $missing,
        ];
    }
}

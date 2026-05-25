<?php

namespace App\Support\Billing;

use App\Models\WorkshopSubscription;

class TenantPlanResolver
{
    /**
     * @return array<string, mixed>|null
     */
    public function forWorkshopId(string $workshopId): ?array
    {
        return $this->forTenantId($workshopId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forTenantId(string $tenantId): ?array
    {
        $now = now();

        $subscription = WorkshopSubscription::query()
            ->with('planPrice.plan')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($now): void {
                $query
                    ->where(function ($trialQuery) use ($now): void {
                        $trialQuery
                            ->where('status', 'trial')
                            ->where(function ($trialWindowQuery) use ($now): void {
                                $trialWindowQuery
                                    ->whereNull('trial_ends_at')
                                    ->orWhere('trial_ends_at', '>=', $now);
                            });
                    })
                    ->orWhere(function ($activeQuery) use ($now): void {
                        $activeQuery
                            ->where('status', 'active')
                            ->where(function ($activeWindowQuery) use ($now): void {
                                $activeWindowQuery
                                    ->whereNull('expired_at')
                                    ->orWhere('expired_at', '>=', $now);
                            });
                    });
            })
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $subscription || ! $subscription->planPrice || ! $subscription->planPrice->plan) {
            return null;
        }

        $planPrice = $subscription->planPrice;
        $plan = $planPrice->plan;

        return [
            'id' => (string) $subscription->getKey(),
            'status' => (string) $subscription->status,
            'started_at' => $subscription->started_at,
            'expired_at' => $subscription->expired_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'plan' => [
                'id' => (int) $plan->id,
                'name' => (string) $plan->name,
                'slug' => (string) $plan->slug,
                'max_workshops' => (int) $plan->max_workshops,
                'max_users_per_ws' => (int) $plan->max_users_per_ws,
                'has_ai_feature' => (bool) $plan->has_ai_feature,
                'has_notification' => (bool) $plan->has_notification,
                'has_loyalty' => (bool) $plan->has_loyalty,
                'has_trial' => (bool) $plan->has_trial,
                'trial_duration_days' => (int) $plan->trial_duration_days,
            ],
            'price' => [
                'id' => (int) $planPrice->id,
                'label' => (string) $planPrice->label,
                'duration_months' => (int) $planPrice->duration_months,
                'amount' => (float) $planPrice->price,
                'discount_pct' => (int) $planPrice->discount_pct,
            ],
        ];
    }
}

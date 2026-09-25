<?php

namespace App\Services;

use App\Enums\AdmissionPeriodStatus;
use App\Models\AdmissionPeriod;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdmissionPeriodService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): AdmissionPeriod
    {
        Gate::forUser($actor)->authorize('create', AdmissionPeriod::class);
        $this->assertDates($data);

        $period = AdmissionPeriod::query()->create([
            ...Arr::only($data, [
                'academic_year_id',
                'name',
                'slug',
                'description',
                'instructions',
                'internal_notes',
                'opens_at',
                'closes_at',
                'quota',
            ]),
            'status' => AdmissionPeriodStatus::Draft,
            'published_at' => null,
        ]);

        $this->cache->invalidate('ppdb');
        $this->audit->record($actor, 'admission_period.created', 'admission_period', $period->getKey(), context: ['status' => $period->status->value]);

        return $period->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AdmissionPeriod $period, array $data): AdmissionPeriod
    {
        Gate::forUser($actor)->authorize('update', $period);
        $this->assertDates([...$period->only(['opens_at', 'closes_at']), ...$data]);

        $period->fill(Arr::only($data, [
            'academic_year_id',
            'name',
            'slug',
            'description',
            'instructions',
            'internal_notes',
            'opens_at',
            'closes_at',
            'quota',
        ]))->save();

        $this->cache->invalidate('ppdb');
        $this->audit->record($actor, 'admission_period.updated', 'admission_period', $period->getKey(), context: ['status' => $period->status->value]);

        return $period->refresh();
    }

    public function open(User $actor, AdmissionPeriod $period): AdmissionPeriod
    {
        Gate::forUser($actor)->authorize('open', $period);
        $this->assertDates($period->only(['opens_at', 'closes_at']));

        if ($period->status === AdmissionPeriodStatus::Archived) {
            throw ValidationException::withMessages(['status' => 'An archived admission period cannot be reopened.']);
        }

        $period->forceFill([
            'status' => AdmissionPeriodStatus::Open,
            'published_at' => $period->published_at ?? now(),
        ])->save();
        $this->afterTransition($actor, $period, 'opened');

        return $period->refresh();
    }

    public function close(User $actor, AdmissionPeriod $period): AdmissionPeriod
    {
        Gate::forUser($actor)->authorize('close', $period);
        $period->forceFill(['status' => AdmissionPeriodStatus::Closed])->save();
        $this->afterTransition($actor, $period, 'closed');

        return $period->refresh();
    }

    public function archive(User $actor, AdmissionPeriod $period): AdmissionPeriod
    {
        Gate::forUser($actor)->authorize('archive', $period);
        $period->forceFill([
            'status' => AdmissionPeriodStatus::Archived,
            'published_at' => null,
        ])->save();
        $this->afterTransition($actor, $period, 'archived');

        return $period->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDates(array $data): void
    {
        $opensAt = $data['opens_at'] ?? null;
        $closesAt = $data['closes_at'] ?? null;

        if ($opensAt === null || $closesAt === null || strtotime((string) $opensAt) >= strtotime((string) $closesAt)) {
            throw ValidationException::withMessages(['closes_at' => 'The admission closing date must be after the opening date.']);
        }
    }

    private function afterTransition(User $actor, AdmissionPeriod $period, string $action): void
    {
        $this->cache->invalidate('ppdb');
        $this->audit->record($actor, 'admission_period.'.$action, 'admission_period', $period->getKey(), context: ['status' => $period->status->value]);
    }
}

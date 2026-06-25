<?php

namespace App\Services\Pricing;

use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RatePrice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RatePlanService
{
    public function __construct(
        protected PricingService $pricingService,
    ) {}

    public function createRatePlan(Hotel $hotel, array $data): RatePlan
    {
        // return DB::transaction(function () use ($hotel, $data) {
        //     $ratePlan = $hotel->ratePlans()->create(
        //         Arr::except($data, ['pricing'])
        //     );
        //     if (!empty($data['pricing'])) {
        //         $this->pricingService->generateFuturePrices(
        //             ratePlan: $ratePlan,
        //             pricing: $data['pricing'],
        //             from: now()->startOfDay()
        //         );
        //     }
        //     return $ratePlan->load(['parent']);
        // });
        $ratePlan = $hotel->plans()->create($data);
        return $ratePlan;
    }

    public function updateRatePlan(RatePlan $ratePlan, array $data): RatePlan
    {
        // return DB::transaction(function () use ($ratePlan, $data) {
        //      $ratePlan->update(
        //         Arr::except($data, ['pricing'])
        //     );
        //     if (!empty($data['pricing'])) {
        //         $this->pricingService->generateFuturePrices(
        //             ratePlan: $ratePlan,
        //             pricing: $data['pricing'],
        //             from: now()->startOfDay()->addDay()
        //         );
        //     }
        //     return $ratePlan->load(['parent']);
        // });
        return DB::transaction(function () use ($ratePlan, $data) {
            if (array_key_exists('parent_id', $data) && $data['parent_id'] === null) {
                $data['modifier_percent'] = null;
            }
            $ratePlan->update($data);
            return $ratePlan;
        });
    }

    public function deleteRatePlan(RatePlan $ratePlan): bool
    {
        return DB::transaction(function () use ($ratePlan) {
            if ($ratePlan->bookings()->exists()) {
                throw new ConflictHttpException(
                    'Cannot delete rate plan with bookings.'
                );
            }
            RatePlan::where('parent_id', $ratePlan->id)->update(['parent_id' => null]);
            $ratePlan->prices()->delete();
            $ratePlan->overrides()->delete();
            return $ratePlan->delete();
        });
    }

    // protected function syncBasePrices(RatePlan $ratePlan, array $basePrices, bool $isUpdate = false): void
    // {
    //     if ($ratePlan->parent_id !== null) {
    //         return;
    //     }

    //     $startDate = Carbon::tomorrow();
    //     $endDate = Carbon::tomorrow()->addYears(2);
    //     $period = CarbonPeriod::create($startDate, $endDate);

    //     $toUpsert = [];

    //     foreach ($basePrices as $setup) {
    //         foreach ($period as $date) {
    //             $dayKey = strtolower($date->englishDayOfWeek);

    //             $toUpsert[] = [
    //                 'rate_plan_id' => $ratePlan->id,
    //                 'room_category_id' => $setup['room_category_id'],
    //                 'date' => $date->format('Y-m-d'),
    //                 'price' => $setup[$dayKey],
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ];

    //             if (count($toUpsert) >= 500) {
    //                 $this->performRatePriceUpsert($toUpsert);
    //                 $toUpsert = [];
    //             }
    //         }
    //     }

    //     if (!empty($toUpsert)) {
    //         $this->performRatePriceUpsert($toUpsert);
    //     }
    // }

    // protected function performRatePriceUpsert(array $rows): void
    // {
    //     RatePrice::upsert(
    //         $rows,
    //         ['rate_plan_id', 'room_category_id', 'date'],
    //         ['price', 'updated_at']
    //     );
    // }
}

<?php

namespace App\Services\Reservation;

use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RoomCategory;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AvailabilityService
{
	public function __construct(
		protected PricingService $pricingService
	) {}

	/**
	 * Получить список доступных категорий с тарифами и ценами.
	 */
	public function getPublicAvailability(Hotel $hotel, Carbon $checkIn, Carbon $checkOut, int $adults, int $children): Collection
	{
		// Берём все категории отеля
		$categories = RoomCategory::where('hotel_id', $hotel->id)
			->with(['amenities', 'media'])
			->get();

		// Берём все активные тарифы отеля включая дочерние
		$ratePlans = RatePlan::where('hotel_id', $hotel->id)
			->where('is_active', true)
			->get();

		$result = collect();

		foreach ($categories as $category) {
			// Проверяем вместимость
			if (!$category->canAccommodate($adults, $children)) {
				continue;
			}

			// Проверяем есть ли свободные номера
			$freeRoomsCount = $category->getAvailableRoomsCount($checkIn, $checkOut);
			if ($freeRoomsCount === 0) {
				continue;
			}

			// Для каждого тарифа проверяем доступность и считаем цену
			$availableRatePlans = collect();

			foreach ($ratePlans as $plan) {
				if (!$plan->isAvailableForCategory($category, $checkIn, $checkOut)) {
					continue;
				}

				$totalRoomPrice = $this->pricingService->calculateRoomPrice($plan, $category, $checkIn, $checkOut);

				$availableRatePlans->push([
					'rate_plan_id'           => $plan->id,
					'parent_id'              => $plan->parent_id,
					'modifier_percent'       => $plan->modifier_percent,
					'name'                   => $plan->name,
					'description'            => $plan->description,
					'meal_plan'              => $plan->meal_plan->value,
					'meal_plan_label'        => $plan->meal_plan->label(),
					'prepayment_percent'     => $plan->prepayment_percent,
					'cancellation_free_days' => $plan->cancellation_free_days,
					'cancellation_penalty_percent' => $plan->cancellation_penalty_percent,
					'total_room_price'       => $totalRoomPrice,
				]);
			}

			if ($availableRatePlans->isEmpty()) {
				continue;
			}

			$result->push([
				'room_category_id' => $category->id,
				'name'             => $category->name,
				'description'      => $category->description,
				'area'             => $category->area,
				'base_capacity'    => $category->base_capacity,
				'extra_capacity'   => $category->extra_capacity,
				'bedding_options'  => $category->bedding_options,
				'free_rooms_count' => $freeRoomsCount,
				'price_from'       => $availableRatePlans->min('total_room_price'),
				'rate_plans'       => $availableRatePlans,
				'amenities'        => $category->amenities,
				'media'            => $category->media->map(fn($m) => [
					'id'  => $m->id,
					'url' => url(Storage::url($m->src)),
				]),
			]);
		}

		return $result;
	}

	/**
	 * Получить доступные номера, категории и тарифы с ценами.
	 */
	public function getAvailability(
		Hotel $hotel,
		Carbon $checkIn,
		Carbon $checkOut,
		int $adults,
		int $children,
		?int $excludeBookingId = null
	): Collection {
		$categories = RoomCategory::where('hotel_id', $hotel->id)
			->with(['rooms' => fn($q) => $q->where('is_active', true)])
			->get();

		$ratePlans = RatePlan::where('hotel_id', $hotel->id)
			->where('is_active', true)
			->get();

		$activeStatuses = [
			\App\Enums\BookingStatusEnum::NEW->value,
			\App\Enums\BookingStatusEnum::CONFIRMED->value,
			\App\Enums\BookingStatusEnum::CHECKED_IN->value,
			// \App\Enums\BookingStatusEnum::CHECKED_OUT->value,
		];

		$result = collect();

		foreach ($categories as $category) {
			if (!$category->canAccommodate($adults, $children)) {
				continue;
			}

			$freeRoomsCount = $category->getAvailableRoomsCount($checkIn, $checkOut, $excludeBookingId);
			if ($freeRoomsCount === 0) {
				continue;
			}

			$availableRatePlans = collect();
			foreach ($ratePlans as $plan) {
				if (!$plan->isAvailableForCategory($category, $checkIn, $checkOut)) {
					continue;
				}

				$totalRoomPrice = $this->pricingService->calculateRoomPrice($plan, $category, $checkIn, $checkOut);
				$availableRatePlans->push([
					'rate_plan_id'                 => $plan->id,
					'parent_id'                    => $plan->parent_id,
					'modifier_percent'             => $plan->modifier_percent,
					'name'                         => $plan->name,
					'description'                  => $plan->description,
					'meal_plan'                    => $plan->meal_plan->value,
					'meal_plan_label'              => $plan->meal_plan->label(),
					'prepayment_percent'           => $plan->prepayment_percent,
					'cancellation_free_days'       => $plan->cancellation_free_days,
					'cancellation_penalty_percent' => $plan->cancellation_penalty_percent,
					'total_room_price'             => $totalRoomPrice,
				]);
			}

			if ($availableRatePlans->isEmpty()) {
				continue;
			}

			// Получаем занятые номера на эти даты
			$occupiedRoomIds = \App\Models\Booking::where('room_category_id', $category->id)
				->whereNotNull('room_id')
				->whereHas('status', fn($q) => $q->whereIn('slug', $activeStatuses))
				->where('check_in_date', '<', $checkOut)
				->where('check_out_date', '>', $checkIn)
				->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
				->pluck('room_id');

			$availableRooms = $category->rooms
				->whereNotIn('id', $occupiedRoomIds)
				->values()
				->map(fn($room) => [
					'id'   => $room->id,
					'name' => $room->name,
				]);

			$result->push([
				'room_category_id' => $category->id,
				'name'             => $category->name,
				'description'      => $category->description,
				'area'             => $category->area,
				'base_capacity'    => $category->base_capacity,
				'extra_capacity'   => $category->extra_capacity,
				'bedding_options'  => $category->bedding_options,
				'free_rooms_count' => $freeRoomsCount,
				'available_rooms'  => $availableRooms,
				'price_from'       => $availableRatePlans->min('total_room_price'),
				'rate_plans'       => $availableRatePlans,
			]);
		}

		return $result;
	}

	/**
	 * Полная проверка данных перед созданием бронирования.
	 */
	public function validateBookingData(
		Hotel $hotel,
		RoomCategory $category,
		RatePlan $ratePlan,
		Carbon $checkIn,
		Carbon $checkOut,
		int $adults,
		int $children,
		?int $roomId = null,
		?int $excludeBookingId = null
	): void {
		// 1. Категория принадлежит отелю
		if ($category->hotel_id !== $hotel->id) {
			throw new UnprocessableEntityHttpException(
				'Категория не принадлежит этому отелю'
			);
		}

		// 2. Вместимость подходит
		if (!$category->canAccommodate($adults, $children)) {
			throw new UnprocessableEntityHttpException(
				'Категория не может вместить столько гостей'
			);
		}

		// 3. Есть свободные номера
		if (!$category->hasAvailableRooms($checkIn, $checkOut, $excludeBookingId)) {
			throw new UnprocessableEntityHttpException(
				'No available rooms in this category for the selected dates.'
			);
		}

		// 4. Если передан конкретный номер — проверяем его
		if ($roomId !== null) {
			$this->validateRoom($category, $roomId, $checkIn, $checkOut, $excludeBookingId);
		}

		// 5. Тариф принадлежит отелю и активен
		if ($ratePlan->hotel_id !== $hotel->id || !$ratePlan->is_active) {
			throw new UnprocessableEntityHttpException(
				'Rate plan is not valid for this hotel.'
			);
		}

		// 6. Тариф доступен для категории на даты
		if (!$ratePlan->isAvailableForCategory($category, $checkIn, $checkOut)) {
			throw new UnprocessableEntityHttpException(
				'Rate plan is not available for this category on selected dates.'
			);
		}
	}

	/**
	 * Проверяет конкретный номер.
	 */
	private function validateRoom(RoomCategory $category, int $roomId, Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId = null): void
	{
		$room = $category->rooms()
			->where('id', $roomId)
			->where('is_active', true)
			->first();

		if (!$room) {
			throw new UnprocessableEntityHttpException(
				'Room does not belong to this category or is not active.'
			);
		}

		$activeStatuses = [
			\App\Enums\BookingStatusEnum::NEW->value,
			\App\Enums\BookingStatusEnum::CONFIRMED->value,
			\App\Enums\BookingStatusEnum::CHECKED_IN->value,
		];

		$isOccupied = $room->bookings()
			->whereHas('status', fn($q) => $q->whereIn('slug', $activeStatuses))
			->where('check_in_date', '<', $checkOut)
			->where('check_out_date', '>', $checkIn)
			->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
			->exists();

		if ($isOccupied) {
			throw new UnprocessableEntityHttpException(
				'This room is already booked for the selected dates.'
			);
		}
	}
}

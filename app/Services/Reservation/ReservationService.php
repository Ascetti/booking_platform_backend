<?php

namespace App\Services\Reservation;

use App\Enums\BookingStatusEnum;
use App\Events\BookingCreated;
use App\Events\BookingDetailsChanged;
use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RoomCategory;
use App\Models\Service;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReservationService
{
	public function __construct(
		protected AvailabilityService $availabilityService,
		protected PricingService $pricingService,
		protected GuestService $guestService,
	) {}

	/**
	 * Создать бронирование.
	 * Всё происходит в одной транзакции — если что-то пошло не так, все изменения откатываются.
	 */
	public function create(Hotel $hotel, array $data): Booking
	{
		$booking = DB::transaction(function () use ($hotel, $data) {
			$checkIn  = Carbon::parse($data['check_in_date']);
			$checkOut = Carbon::parse($data['check_out_date']);
			$adults   = $data['adults_count'];
			$children = $data['children_count'];

			// Загружаем нужные модели
			$category = RoomCategory::findOrFail($data['room_category_id']);
			$ratePlan = RatePlan::findOrFail($data['rate_plan_id']);

			// Шаг 1 — полная проверка данных
			// Если что-то не так — бросит исключение и транзакция откатится
			$this->availabilityService->validateBookingData(
				hotel: $hotel,
				category: $category,
				ratePlan: $ratePlan,
				checkIn: $checkIn,
				checkOut: $checkOut,
				adults: $adults,
				children: $children,
				roomId: $data['room_id'] ?? null,
			);

			// Шаг 2 — считаем стоимость проживания
			$roomPrice = $this->pricingService->calculateRoomPrice(
				$ratePlan,
				$category,
				$checkIn,
				$checkOut
			);

			// Шаг 3 — считаем стоимость услуг если они переданы
			$servicesData = $data['services'] ?? [];
			$servicesForCalculation = [];

			foreach ($servicesData as $serviceItem) {
				$service = Service::findOrFail($serviceItem['id']);

				// Проверяем что услуга принадлежит этому отелю
				if ($service->hotel_id !== $hotel->id) {
					throw new UnprocessableEntityHttpException(
						"Service {$service->id} does not belong to this hotel."
					);
				}

				$servicesForCalculation[] = [
					'service'  => $service,
					'quantity' => $serviceItem['quantity'],
				];
			}

			$totalPrice = $this->pricingService->calculateTotalPrice(
				ratePlan: $ratePlan,
				category: $category,
				checkIn: $checkIn,
				checkOut: $checkOut,
				services: $servicesForCalculation,
			);

			// Шаг 4 — получаем статус "new" из справочника
			$status = BookingStatus::where('slug', BookingStatusEnum::NEW->value)->firstOrFail();

			// Шаг 5 — создаём бронирование
			$booking = Booking::create([
				'hotel_id'             => $hotel->id,
				'room_category_id'     => $category->id,
				'room_id'              => $data['room_id'] ?? null,
				'rate_plan_id'         => $ratePlan->id,
				'status_id'            => $status->id,
				'check_in_date'        => $checkIn,
				'check_out_date'       => $checkOut,
				'adults_count'         => $adults,
				'children_count'       => $children,
				'room_price_at_booking' => $roomPrice,
				'total_price'          => $totalPrice,
				'comment'              => $data['comment'] ?? null,
			]);

			// Шаг 6 — находим или создаём гостя заказчика
			$primaryGuestData = collect($data['guests'])->firstWhere('is_primary', true);
			$primaryGuest = $this->guestService->findOrCreate($hotel, $primaryGuestData);

			// Привязываем гостя к брони со снимком данных
			// Снимок фиксирует данные на момент бронирования
			$booking->guests()->attach($primaryGuest->id, [
				'is_primary' => true,
				'first_name' => $primaryGuestData['first_name'],
				'last_name'  => $primaryGuestData['last_name'],
				'email'      => $primaryGuestData['email'] ?? null,
				'phone'      => $primaryGuestData['phone'] ?? null,
			]);

			// Привязываем остальных гостей если есть
			foreach ($data['guests'] as $guestData) {
				if (!empty($guestData['is_primary'])) {
					continue; // заказчика уже привязали
				}
				$guest = $this->guestService->findOrCreate($hotel, $guestData);
				$booking->guests()->attach($guest->id, [
					'is_primary' => false,
					'first_name' => $guestData['first_name'],
					'last_name'  => $guestData['last_name'],
					'email'      => $guestData['email'] ?? null,
					'phone'      => $guestData['phone'] ?? null,
				]);
			}

			// Шаг 7 — привязываем услуги со снимком цен
			$nights = $checkIn->diffInDays($checkOut);
			foreach ($servicesForCalculation as $item) {
				$service  = $item['service'];
				$quantity = $item['quantity'];
				$unitPrice = (float) $service->price;

				$booking->services()->attach($service->id, [
					'quantity'         => $quantity,
					'price_at_booking' => $unitPrice,
				]);
			}

			// Возвращаем бронирование со всеми связями
			return $booking->load([
				'status',
				'category',
				'room',
				'plan',
				'guests',
				'services',
			]);
		});
		BookingCreated::dispatch($booking);
		return $booking;
	}

	/**
	 * Подтвердить бронирование.
	 */
	public function confirm(Booking $booking): Booking
	{
		$this->transitionStatus($booking, BookingStatusEnum::CONFIRMED);
		$booking->load([
			'status',
			'category',
			'room',
			'plan',
			'guests',
			'services',
		]);
		BookingStatusChanged::dispatch($booking);
		return $booking;
	}

	/**
	 * Отменить бронирование.
	 */
	public function cancel(Booking $booking): Booking
	{
		$this->transitionStatus($booking, BookingStatusEnum::CANCELLED);
		$booking->load([
			'status',
			'category',
			'room',
			'plan',
			'guests',
			'services',
		]);

		BookingDetailsChanged::dispatch($booking);

		return $booking;
	}

	/**
	 * Заселить гостя.
	 * При заселении номер обязателен.
	 */
	public function checkIn(Booking $booking): Booking
	{
		if (!$booking->room_id) {
			throw new UnprocessableEntityHttpException(
				'Please assign a room before check-in.'
			);
		}

		$today = now()->startOfDay();
		$checkInDate = Carbon::parse($booking->check_in_date)->startOfDay();

		if ($checkInDate->gt($today)) {
			throw new UnprocessableEntityHttpException(
				'Cannot check in before the arrival date.'
			);
		}

		$this->transitionStatus($booking, BookingStatusEnum::CHECKED_IN);
		$booking->load([
			'status',
			'category',
			'room',
			'plan',
			'guests',
			'services',
		]);

		BookingDetailsChanged::dispatch($booking);

		return $booking;
	}

	/**
	 * Выселить гостя.
	 */
	public function checkOut(Booking $booking): Booking
	{
		$this->transitionStatus($booking, BookingStatusEnum::CHECKED_OUT);
		$booking->load([
			'status',
			'category',
			'room',
			'plan',
			'guests',
			'services',
		]);

		BookingDetailsChanged::dispatch($booking);

		return $booking;
	}

	/**
	 * Назначить конкретный номер бронированию.
	 */
	public function assignRoom(Booking $booking, ?int $roomId): Booking
	{
		// Бронирование должно быть активным
		if ($booking->isCheckedOut() || $booking->isCancelled()) {
			throw new UnprocessableEntityHttpException(
				'Cannot assign room to a completed or cancelled booking.'
			);
		}

		if ($roomId === null) {
			$booking->update(['room_id' => null]);
			BookingDetailsChanged::dispatch($booking);
			return $booking->load([
				'status',
				'category',
				'room',
				'plan',
				'guests',
				'services',
			]);
		}

		$category = $booking->category;

		$room = $category->rooms()
			->where('id', $roomId)
			->where('is_active', true)
			->firstOrFail();

		// Проверяем что номер свободен
		$activeStatuses = [
			BookingStatusEnum::NEW->value,
			BookingStatusEnum::CONFIRMED->value,
			BookingStatusEnum::CHECKED_IN->value,
		];

		$isOccupied = $room->bookings()
			->where('id', '!=', $booking->id) // исключаем текущее бронирование
			->whereHas('status', fn($q) => $q->whereIn('slug', $activeStatuses))
			->where('check_in_date', '<', $booking->check_out_date)
			->where('check_out_date', '>', $booking->check_in_date)
			->exists();

		if ($isOccupied) {
			throw new UnprocessableEntityHttpException(
				'This room is already booked for these dates.'
			);
		}

		$booking->update(['room_id' => $roomId]);

		$booking->load([
			'status',
			'category',
			'room',
			'plan',
			'guests',
			'services',
		]);

		BookingDetailsChanged::dispatch($booking);

		return $booking;
	}

	public function restore(Booking $booking): Booking
	{
		// Проверяем доступность — пока бронь была отменена могли занять место
		$this->availabilityService->validateBookingData(
			hotel: $booking->hotel,
			category: $booking->category,
			ratePlan: $booking->plan,
			checkIn: Carbon::parse($booking->check_in_date),
			checkOut: Carbon::parse($booking->check_out_date),
			adults: $booking->adults_count,
			children: $booking->children_count,
			roomId: null, // номер не проверяем — он мог уже занят
			excludeBookingId: $booking->id,
		);

		// Сбрасываем номер — он мог стать недоступен
		$booking->update(['room_id' => null]);

		$this->transitionStatus($booking, BookingStatusEnum::NEW);

		return $booking->load([
			'status',
			'category',
			'room',
			'plan',
			'guests',
			'services',
		]);
	}

	/**
	 * Удалить бронирование.
	 * Только отменённые брони можно удалять.
	 */
	public function delete(Booking $booking): void
	{
		if (!$booking->isCancelled()) {
			throw new ConflictHttpException(
				'Only cancelled bookings can be deleted.'
			);
		}

		$booking->delete();
	}

	/**
	 * Вспомогательный метод для смены статуса.
	 * Проверяет что переход разрешён через Enum.
	 */
	private function transitionStatus(Booking $booking, BookingStatusEnum $newStatus): void
	{
		// Получаем текущий статус как Enum
		$currentStatus = BookingStatusEnum::from($booking->status->slug);

		// Проверяем разрешён ли переход
		if (!$currentStatus->canTransitionTo($newStatus)) {
			throw new UnprocessableEntityHttpException(
				"Cannot transition booking from '{$currentStatus->value}' to '{$newStatus->value}'."
			);
		}

		// Находим новый статус в справочнике
		$status = BookingStatus::where('slug', $newStatus->value)->firstOrFail();

		$booking->update(['status_id' => $status->id]);
		$booking->refresh(); // обновляем модель чтобы статус был актуальным
	}

	public function applyExternalStatusChange(Booking $booking, BookingStatusEnum $newStatus): void
	{
		$this->transitionStatus($booking, $newStatus);
	}

	/**
	 * Изменить критические данные бронирования.
	 * Даты, категория, тариф, количество гостей.
	 * Требует перепроверки доступности и пересчёта цены.
	 */
	public function updateStayDetails(Booking $booking, array $data): Booking
	{
		return DB::transaction(function () use ($booking, $data) {
			if (!$booking->isModifiable()) {
				throw new UnprocessableEntityHttpException(
					'Cannot modify a booking that is checked in, checked out, cancelled.'
				);
			}

			$checkIn  = Carbon::parse($data['check_in_date'] ?? $booking->check_in_date);
			$checkOut = Carbon::parse($data['check_out_date'] ?? $booking->check_out_date);
			$adults   = $data['adults_count'] ?? $booking->adults_count;
			$children = $data['children_count'] ?? $booking->children_count;

			$category = isset($data['room_category_id'])
				? RoomCategory::findOrFail($data['room_category_id'])
				: $booking->category;

			$ratePlan = isset($data['rate_plan_id'])
				? RatePlan::findOrFail($data['rate_plan_id'])
				: $booking->plan;

			// Определяем room_id
			// Если категория поменялась — сбрасываем номер
			// Если категория та же — берём из запроса если передан, иначе текущий
			$categoryChanged = isset($data['room_category_id']) && (int)$data['room_category_id'] !== $booking->room_category_id;

			if ($categoryChanged) {
				$roomId = null;
			} elseif (array_key_exists('room_id', $data)) {
				$roomId = $data['room_id']; // может быть null если передали явно
			} else {
				$roomId = $booking->room_id;
			}

			$this->availabilityService->validateBookingData(
				hotel: $booking->hotel,
				category: $category,
				ratePlan: $ratePlan,
				checkIn: $checkIn,
				checkOut: $checkOut,
				adults: $adults,
				children: $children,
				roomId: $roomId,
				excludeBookingId: $booking->id,
			);

			$roomPrice = $this->pricingService->calculateRoomPrice(
				$ratePlan,
				$category,
				$checkIn,
				$checkOut
			);

			$nights = $checkIn->diffInDays($checkOut);
			$servicesTotal = 0.0;
			foreach ($booking->services as $service) {
				$servicesTotal += $this->pricingService->calculateServicePrice(
					$service->price_type,
					(float) $service->pivot->price_at_booking,
					$service->pivot->quantity,
					$nights,
				);
			}

			$totalPrice = round($roomPrice + $servicesTotal, 2);

			$booking->update([
				'room_category_id'      => $category->id,
				'room_id'               => $roomId,
				'rate_plan_id'          => $ratePlan->id,
				'check_in_date'         => $checkIn,
				'check_out_date'        => $checkOut,
				'adults_count'          => $adults,
				'children_count'        => $children,
				'room_price_at_booking' => $roomPrice,
				'total_price'           => $totalPrice,
			]);

			$booking->load([
				'status',
				'category',
				'room',
				'plan',
				'guests',
				'services',
			]);

			BookingDetailsChanged::dispatch($booking);
			return $booking;
		});
	}

	/**
	 * Обновить состав гостей бронирования.
	 * Заказчик (is_primary) обязателен.
	 */
	public function updateGuests(Booking $booking, array $data): Booking
	{
		return DB::transaction(function () use ($booking, $data) {
			if (!$booking->isModifiable()) {
				throw new UnprocessableEntityHttpException(
					'Cannot modify guests of a booking that is checked in, checked out, cancelled or no-show.'
				);
			}

			$booking->guests()->detach();

			foreach ($data['guests'] as $guestData) {
				$guest = $this->guestService->updateOrCreate($booking->hotel, $guestData);
				$booking->guests()->attach($guest->id, [
					'is_primary' => $guestData['is_primary'],
				]);
			}

			$booking->load([
				'status',
				'category',
				'room',
				'plan',
				'guests',
				'services',
			]);

			BookingDetailsChanged::dispatch($booking);
			return $booking;
		});
	}

	public function updateServices(Booking $booking, array $data): Booking
	{
		return DB::transaction(function () use ($booking, $data) {
			if ($booking->isCheckedOut() || $booking->isCancelled()) {
				throw new UnprocessableEntityHttpException(
					'Cannot modify services of a completed or cancelled booking.'
				);
			}

			$nights = $booking->calculateNights();

			$newServices     = collect($data['services'] ?? []);
			$currentServices = $booking->services->keyBy('id');
			$newServiceIds   = $newServices->pluck('id');

			// Удаляем услуги которых нет в новом списке
			$toRemove = $currentServices->keys()->diff($newServiceIds);
			if ($toRemove->isNotEmpty()) {
				$booking->services()->detach($toRemove->toArray());
			}

			$servicesTotal = 0.0;

			foreach ($newServices as $serviceItem) {
				$serviceId = $serviceItem['id'];
				$quantity  = $serviceItem['quantity'];

				if ($currentServices->has($serviceId)) {
					// Услуга уже была — используем зафиксированную цену за единицу
					$existingService = $currentServices->get($serviceId);
					$unitPrice       = (float) $existingService->pivot->price_at_booking;

					if ($existingService->pivot->quantity !== $quantity) {
						$booking->services()->updateExistingPivot($serviceId, [
							'quantity' => $quantity,
						]);
					}

					$servicesTotal += $this->pricingService->calculateServicePrice(
						$existingService->price_type,
						$unitPrice,
						$quantity,
						$nights,
					);
				} else {
					// Новая услуга — берём текущую цену из модели и фиксируем
					$service   = Service::findOrFail($serviceId);
					if ($service->hotel_id !== $booking->hotel_id) {
						throw new UnprocessableEntityHttpException(
							"Service {$service->id} does not belong to this hotel."
						);
					}

					$unitPrice    = (float) $service->price;
					$servicePrice = $this->pricingService->calculateServicePrice(
						$service->price_type,
						$unitPrice,
						$quantity,
						$nights,
					);

					$booking->services()->attach($serviceId, [
						'quantity'         => $quantity,
						'price_at_booking' => $unitPrice,
					]);

					$servicesTotal += $servicePrice;
				}
			}

			$totalPrice = round($booking->room_price_at_booking + $servicesTotal, 2);
			$booking->update(['total_price' => $totalPrice]);

			$booking->load([
				'status',
				'category',
				'room',
				'plan',
				'guests',
				'services',
			]);

			BookingDetailsChanged::dispatch($booking);
			return $booking;
		});
	}
}

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
			foreach ($servicesForCalculation as $item) {
				$service  = $item['service'];
				$quantity = $item['quantity'];

				// Считаем цену этой услуги для снимка
				$nights = $checkIn->diffInDays($checkOut);
				$servicePrice = $this->pricingService->calculateServicePrice(
					$service,
					$quantity,
					$nights,
				);

				$booking->services()->attach($service->id, [
					'quantity'         => $quantity,
					'price_at_booking' => $servicePrice,
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
		// Log::info('About to dispatch BookingCreated', ['booking_id' => $booking->id]);
		// BookingCreated::dispatch($booking);
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
		BookingStatusChanged::dispatch($booking);
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

		$this->transitionStatus($booking, BookingStatusEnum::CHECKED_IN);
		BookingStatusChanged::dispatch($booking);
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
	 * Выселить гостя.
	 */
	public function checkOut(Booking $booking): Booking
	{
		$this->transitionStatus($booking, BookingStatusEnum::CHECKED_OUT);
		BookingStatusChanged::dispatch($booking);
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
	 * Гость не приехал.
	 */
	public function noShow(Booking $booking): Booking
	{
		$this->transitionStatus($booking, BookingStatusEnum::NO_SHOW);
		// BookingStatusChanged::dispatch($booking);
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
	 * Назначить конкретный номер бронированию.
	 * Можно делать отдельно от заселения.
	 */
	public function assignRoom(Booking $booking, int $roomId): Booking
	{
		// Бронирование должно быть активным
		if (!$booking->isModifiable()) {
			throw new UnprocessableEntityHttpException(
				'Cannot assign room to a booking that is not modifiable.'
			);
		}

		$category = $booking->category;

		// Проверяем номер через AvailabilityService
		// Используем приватный метод через рефлексию — или сделаем его публичным
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

	/**
	 * Удалить бронирование.
	 * Только отменённые или no_show брони можно удалять.
	 */
	public function delete(Booking $booking): void
	{
		if (!$booking->isCancelled() && !$booking->isNoShow()) {
			throw new ConflictHttpException(
				'Only cancelled or no-show bookings can be deleted.'
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
			// Бронирование должно быть изменяемым
			if (!$booking->isModifiable()) {
				throw new UnprocessableEntityHttpException(
					'Cannot modify a booking that is checked in, checked out, cancelled or no-show.'
				);
			}

			// Берём новые значения или оставляем старые если не переданы
			$checkIn  = Carbon::parse($data['check_in_date'] ?? $booking->check_in_date);
			$checkOut = Carbon::parse($data['check_out_date'] ?? $booking->check_out_date);
			$adults   = $data['adults_count'] ?? $booking->adults_count;
			$children = $data['children_count'] ?? $booking->children_count;

			// Если поменялась категория — загружаем новую, иначе берём текущую
			$category = isset($data['room_category_id'])
				? RoomCategory::findOrFail($data['room_category_id'])
				: $booking->category;

			// Если поменялся тариф — загружаем новый, иначе берём текущий
			$ratePlan = isset($data['rate_plan_id'])
				? RatePlan::findOrFail($data['rate_plan_id'])
				: $booking->plan;

			// Проверяем доступность с новыми данными
			$this->availabilityService->validateBookingData(
				hotel: $booking->hotel,
				category: $category,
				ratePlan: $ratePlan,
				checkIn: $checkIn,
				checkOut: $checkOut,
				adults: $adults,
				children: $children,
				roomId: null,
				excludeBookingId: $booking->id,
			);

			// Пересчитываем цену с новыми данными
			$roomPrice = $this->pricingService->calculateRoomPrice(
				$ratePlan,
				$category,
				$checkIn,
				$checkOut
			);

			// Пересчитываем услуги с новыми данными
			$nights = $checkIn->diffInDays($checkOut);
			$servicesTotal = 0.0;

			foreach ($booking->services as $service) {
				$servicesTotal += $this->pricingService->calculateServicePrice(
					$service,
					$service->pivot->quantity,
					$nights,
				);
			}

			$totalPrice = round($roomPrice + $servicesTotal, 2);

			// Обновляем бронирование
			$booking->update([
				'room_category_id'      => $category->id,
				'room_id'               => null,
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
	 * Заменяем весь список гостей новым.
	 * Заказчик (is_primary) обязателен.
	 */
	public function updateGuests(Booking $booking, array $data): Booking
	{
		return DB::transaction(function () use ($booking, $data) {
			// Бронирование должно быть изменяемым
			if (!$booking->isModifiable()) {
				throw new UnprocessableEntityHttpException(
					'Cannot modify guests of a booking that is checked in, checked out, cancelled or no-show.'
				);
			}

			// Отвязываем всех текущих гостей
			// Это удаляет записи из booking_guest но не удаляет самих гостей
			$booking->guests()->detach();

			// Привязываем новый список гостей
			foreach ($data['guests'] as $guestData) {
				$isPrimary = $guestData['is_primary'] ?? false;

				// Находим или создаём гостя
				$guest = $this->guestService->findOrCreate($booking->hotel, $guestData);

				// Привязываем со снимком данных
				$booking->guests()->attach($guest->id, [
					'is_primary' => $isPrimary,
					'first_name' => $guestData['first_name'],
					'last_name'  => $guestData['last_name'],
					'email'      => $guestData['email'] ?? null,
					'phone'      => $guestData['phone'] ?? null,
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
			// Нельзя менять услуги у завершённых или отменённых броней
			// Но при checked_in можно — гость может добавить услугу во время проживания
			if ($booking->isCheckedOut() || $booking->isCancelled() || $booking->isNoShow()) {
				throw new UnprocessableEntityHttpException(
					'Cannot modify services of a completed or cancelled booking.'
				);
			}

			$nights   = $booking->calculateNights();
			$adults   = $booking->adults_count;
			$children = $booking->children_count;

			// Новый список услуг из запроса
			// Например: [{id: 1, quantity: 2}, {id: 3, quantity: 1}]
			$newServices = collect($data['services'] ?? []);

			// Текущие услуги брони сгруппированные по id
			// keyBy('id') делает ключом коллекции id услуги
			// Было: [{id:1, ...}, {id:2, ...}]
			// Стало: [1 => {id:1, ...}, 2 => {id:2, ...}]
			// Это нужно чтобы быстро проверить — была ли услуга уже в брони
			$currentServices = $booking->services->keyBy('id');

			// Собираем id из нового списка — просто массив чисел [1, 3]
			$newServiceIds = $newServices->pluck('id');

			// Находим услуги которые были но их нет в новом списке — их надо удалить
			// keys() — берём текущие id: [1, 2]
			// diff($newServiceIds) — вычитаем новые id [1, 3], остаётся [2]
			// Значит услугу 2 удаляем
			$toRemove = $currentServices->keys()->diff($newServiceIds);
			if ($toRemove->isNotEmpty()) {
				$booking->services()->detach($toRemove->toArray());
			}

			$servicesTotal = 0.0;

			// Проходим по каждой услуге из нового списка
			foreach ($newServices as $serviceItem) {
				$serviceId = $serviceItem['id'];
				$quantity  = $serviceItem['quantity'];

				if ($currentServices->has($serviceId)) {
					// Услуга уже была в брони — берём зафиксированную цену
					// НЕ пересчитываем — цена зафиксирована на момент добавления
					$existingService = $currentServices->get($serviceId);
					$priceAtBooking  = (float) $existingService->pivot->price_at_booking;

					// Если quantity изменилось — обновляем только его
					// updateExistingPivot обновляет запись в сводной таблице по id услуги
					if ($existingService->pivot->quantity !== $quantity) {
						$booking->services()->updateExistingPivot($serviceId, [
							'quantity' => $quantity,
						]);
					}

					// Итого по этой услуге = зафиксированная цена × новое количество
					$servicesTotal += $priceAtBooking * $quantity;
				} else {
					// Новая услуга которой раньше не было — считаем цену сейчас
					$service = Service::findOrFail($serviceId);

					if ($service->hotel_id !== $booking->hotel_id) {
						throw new UnprocessableEntityHttpException(
							"Service {$service->id} does not belong to this hotel."
						);
					}

					// Считаем цену на текущий момент и фиксируем
					$servicePrice = $this->pricingService->calculateServicePrice(
						$service,
						$quantity,
						$nights,
					);

					// Привязываем новую услугу со снимком цены
					$booking->services()->attach($serviceId, [
						'quantity'         => $quantity,
						'price_at_booking' => $servicePrice,
					]);

					$servicesTotal += $servicePrice;
				}
			}

			// Пересчитываем итог — стоимость номера не меняется
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

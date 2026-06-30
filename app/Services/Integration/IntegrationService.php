<?php

namespace App\Services\Integration;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\BookingIntegration;
use App\Models\HotelIntegration;
use App\Models\IntegrationLog;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IntegrationService
{
	public function __construct(
		protected ReservationService $reservationService
	) {}

	private const CHANNEL = 'bitrix24';

	public function handleBookingCreated(Booking $booking): void
	{
		$integration = $this->getActiveIntegration($booking->hotel_id, self::CHANNEL);
		if (!$integration) {
			return;
		}

		$adapter = new Bitrix24Adapter($integration);

		try {
			$booking->loadMissing(['hotel', 'category', 'plan', 'guests', 'status']);

			$primaryGuest = $booking->getPrimaryGuest();
			if (!$primaryGuest) {
				Log::warning('No primary guest for booking, skipping Bitrix24 sync', [
					'booking_id' => $booking->id,
				]);
				return;
			}

			// Каждый реальный вызов к Битрикс24 уже логируется внутри адаптера —
			// здесь мы только связываем результаты в единый бизнес-процесс
			$contactId = $adapter->findOrCreateContact($primaryGuest);
			if (!$contactId) {
				Log::error('Failed to find or create Bitrix24 contact', [
					'booking_id' => $booking->id,
				]);
				return;
			}

			$dealId = $adapter->createDeal($booking, $contactId);
			if (!$dealId) {
				Log::error('Failed to create Bitrix24 deal', [
					'booking_id' => $booking->id,
				]);
				return;
			}

			$this->saveDealId($booking, $dealId);
		} catch (\Exception $e) {
			Log::error('Integration error on booking created', [
				'booking_id' => $booking->id,
				'message'    => $e->getMessage(),
			]);
		}
	}

	public function syncDeal(Booking $booking): void
	{
		$integration = $this->getActiveIntegration($booking->hotel_id, self::CHANNEL);
		if (!$integration) {
			return;
		}

		$adapter = new Bitrix24Adapter($integration);

		try {
			$booking->loadMissing(['hotel', 'category', 'plan', 'guests', 'status']);

			$dealId = $this->getDealId($booking);
			if (!$dealId) {
				Log::warning('No Bitrix24 deal found for booking, skipping sync', [
					'booking_id' => $booking->id,
				]);
				return;
			}

			// Каждый раз пересматриваем актуального заказчика — вдруг он поменялся
			$primaryGuest = $booking->getPrimaryGuest();
			$contactId = null;

			if ($primaryGuest) {
				$contactId = $adapter->findOrCreateContact($primaryGuest);
			}

			$adapter->updateDeal($dealId, $booking, $contactId);
		} catch (\Exception $e) {
			Log::error('Integration error on booking sync', [
				'booking_id' => $booking->id,
				'message'    => $e->getMessage(),
			]);
		}
	}

	public function handleIncomingWebhook(array $payload): void
	{
		$event  = $payload['event'] ?? null;
		$dealId = $payload['data']['FIELDS']['ID'] ?? null;

		if (!$event || !$dealId) {
			return;
		}

		$dealId = (int) $dealId;

		$booking = $this->findBookingByDealId($dealId);
		if (!$booking) {
			Log::info('Bitrix24 webhook: booking not found for deal', ['deal_id' => $dealId]);
			return;
		}

		$integration = $this->getActiveIntegration($booking->hotel_id, self::CHANNEL);
		if (!$integration) {
			return;
		}

		$adapter = new Bitrix24Adapter($integration);

		// Запрашиваем актуальное состояние сделки — вебхук не сообщает что именно изменилось
		$deal = $adapter->getDeal($dealId);
		if (!$deal) {
			return;
		}

		$this->logIncoming($integration->id, $event, $dealId, $deal);

		$newStatus = $adapter->mapStageToStatus($deal['STAGE_ID']);
		if (!$newStatus) {
			// Стадия не соответствует ни одному нашему статусу — игнорируем
			return;
		}

		$this->applyStatusFromBitrix24($booking, $newStatus, $adapter, $dealId);
	}

	/**
	 * Пытается применить статус полученный от Битрикс24 к бронированию.
	 * Меняет статус только если переход разрешён бизнес-логикой брони.
	 * Если переход недопустим — откатывает стадию сделки в Битрикс24 обратно,
	 * чтобы CRM не показывала недостоверное состояние.
	 */
	private function applyStatusFromBitrix24(Booking $booking, string $newStatusSlug, Bitrix24Adapter $adapter, int $dealId): void
	{
		$booking->loadMissing('status');

		if ($booking->status->slug === $newStatusSlug) {
			return;
		}

		$currentStatus = BookingStatusEnum::from($booking->status->slug);
		$targetStatus  = BookingStatusEnum::tryFrom($newStatusSlug);

		if (!$targetStatus || !$currentStatus->canTransitionTo($targetStatus)) {
			Log::warning('Bitrix24 webhook: status transition not allowed, reverting deal stage', [
				'booking_id'     => $booking->id,
				'current_status' => $currentStatus->value,
				'attempted'      => $newStatusSlug,
			]);

			// Возвращаем сделке стадию, которая реально соответствует нашему статусу,
			// чтобы CRM не врала менеджеру о состоянии брони
			$adapter->updateDeal($dealId, $booking);

			return;
		}

		if ($targetStatus === BookingStatusEnum::CHECKED_IN) {
			if (!$booking->room_id) {
				$adapter->updateDeal($dealId, $booking);
				return;
			}

			$today = now()->startOfDay();
			$checkInDate = Carbon::parse($booking->check_in_date)->startOfDay();
			if ($checkInDate->gt($today)) {
				$adapter->updateDeal($dealId, $booking);
				return;
			}
		}

		$this->reservationService->applyExternalStatusChange($booking, $targetStatus);
	}

	/**
	 * Записывает факт входящего вебхука в журнал интеграции.
	 */
	private function logIncoming(int $integrationId, string $event, int $dealId, array $deal): void
	{
		IntegrationLog::create([
			'integration_id' => $integrationId,
			'direction'      => 'incoming',
			'payload'        => ['event' => $event, 'deal_id' => $dealId],
			'response'       => ['stage_id' => $deal['STAGE_ID'] ?? null],
			'http_status'    => 200,
		]);
	}

	/**
	 * Запускает первоначальную настройку интеграции с Битрикс24 —
	 * создаёт воронку, стадии и кастомные поля.
	 * Вызывается один раз при подключении.
	 */
	public function setupIntegration(HotelIntegration $integration): array
	{
		$adapter = new Bitrix24Adapter($integration);
		$settings = $adapter->setupIntegration();

		$integration->update(['settings' => $settings]);

		return $settings;
	}

	private function getActiveIntegration(int $hotelId, string $channelSlug): ?HotelIntegration
	{
		return HotelIntegration::where('hotel_id', $hotelId)
			->where('channel_slug', $channelSlug)
			->where('is_active', true)
			->first();
	}

	/**
	 * Сохраняет актуальную связь бронирования и сделки Битрикс24.
	 */
	private function saveDealId(Booking $booking, int $dealId): void
	{
		BookingIntegration::updateOrCreate(
			[
				'booking_id'   => $booking->id,
				'channel_slug' => self::CHANNEL,
			],
			[
				'external_id' => (string) $dealId,
			]
		);
	}

	private function getDealId(Booking $booking): ?int
	{
		$record = BookingIntegration::where('booking_id', $booking->id)
			->where('channel_slug', self::CHANNEL)
			->first();

		return $record ? (int) $record->external_id : null;
	}

	private function findBookingByDealId(int $dealId): ?Booking
	{
		$record = BookingIntegration::where('channel_slug', self::CHANNEL)
			->where('external_id', (string) $dealId)
			->first();

		return $record ? Booking::find($record->booking_id) : null;
	}
}

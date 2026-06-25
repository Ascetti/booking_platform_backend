<?php

namespace App\Services\Integration;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\HotelIntegration;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Bitrix24Adapter
{
	private ?string $webhookUrl;
	private ?int $pipelineId;
	private array $stageMapping;
	private int $integrationId;

	public function __construct(HotelIntegration $integration)
	{
		$this->integrationId = $integration->id;

		$credentials = $integration->credentials;
		$this->webhookUrl = $credentials['webhook_url'] ?? null;

		if (!$this->webhookUrl) {
			throw new \RuntimeException('Bitrix24 webhook URL is not configured.');
		}

		$settings = $integration->settings ?? [];
		$this->pipelineId   = $settings['pipeline_id'] ?? null;
		$this->stageMapping = $settings['stage_mapping'] ?? [];
	}

	/**
	 * Полная первоначальная настройка интеграции.
	 * Вызывается один раз когда отель подключает Битрикс24.
	 * Возвращает данные для сохранения в settings интеграции.
	 */
	public function setupIntegration(): array
	{
		$pipelineId = $this->createPipeline();
		$stageMapping = [];

		if ($pipelineId) {
			$stageMapping = $this->setupStages($pipelineId);
		}

		$this->setupDealFields();
		$this->setupContactFields();

		return [
			'pipeline_id'   => $pipelineId,
			'stage_mapping' => $stageMapping,
		];
	}

	/**
	 * Создаёт воронку "Бронирования отеля" в Битрикс24.
	 */
	private function createPipeline(): ?int
	{
		$response = $this->call('crm.dealcategory.add', [
			'fields' => [
				'NAME' => 'Бронирования отеля',
			],
		]);

		return $response ? (int) $response['result']['ID'] : null;
	}

	/**
	 * Настраивает стадии для воронки бронирований.
	 * Возвращает маппинг [наш_статус => STAGE_ID].
	 */
	private function setupStages(int $pipelineId): array
	{
		$stagesToCreate = [
			'new'         => 'Новое бронирование',
			'confirmed'   => 'Подтверждено',
			'checked_in'  => 'Гость заселён',
			'checked_out' => 'Завершено (выселен)',
			'cancelled'   => 'Отменено',
		];

		$stageMapping = [];
		$sort = 10;

		foreach ($stagesToCreate as $ourStatus => $name) {
			$response = $this->call('crm.status.add', [
				'fields' => [
					'ENTITY_ID' => "DEAL_STAGE_{$pipelineId}",
					'NAME'      => $name,
					'SORT'      => $sort,
					'SEMANTICS' => match ($ourStatus) {
						'checked_out' => 'S',
						'cancelled'   => 'F',
						default       => null,
					},
				],
			]);

			if ($response) {
				$stageMapping[$ourStatus] = $response['result'];
			}

			$sort += 10;
		}

		return $stageMapping;
	}

	/**
	 * Создаёт пользовательские поля сделки для отельной специфики.
	 */
	private function setupDealFields(): void
	{
		$fields = [
			['FIELD_NAME' => 'UF_HOTEL_NAME',     'LABEL' => 'Отель',              'TYPE' => 'string'],
			['FIELD_NAME' => 'UF_CHECK_IN_DATE',  'LABEL' => 'Дата заезда',        'TYPE' => 'date'],
			['FIELD_NAME' => 'UF_CHECK_OUT_DATE', 'LABEL' => 'Дата выезда',        'TYPE' => 'date'],
			['FIELD_NAME' => 'UF_ROOM_CATEGORY',  'LABEL' => 'Категория номера',   'TYPE' => 'string'],
			['FIELD_NAME' => 'UF_ROOM',           'LABEL' => 'Номер',              'TYPE' => 'string'],
			['FIELD_NAME' => 'UF_RATE_PLAN',      'LABEL' => 'Тариф',              'TYPE' => 'string'],
			['FIELD_NAME' => 'UF_ADULTS_COUNT',   'LABEL' => 'Взрослых',           'TYPE' => 'integer'],
			['FIELD_NAME' => 'UF_CHILDREN_COUNT', 'LABEL' => 'Детей',              'TYPE' => 'integer'],
			['FIELD_NAME' => 'UF_SERVICES',       'LABEL' => 'Доп. услуги',        'TYPE' => 'string'],
			['FIELD_NAME' => 'UF_BOOKING_ID',     'LABEL' => 'ID бронирования',    'TYPE' => 'integer'],
		];

		foreach ($fields as $field) {
			$this->call('crm.deal.userfield.add', [
				'FIELD_NAME'        => $field['FIELD_NAME'],
				'EDIT_FORM_LABEL'   => ['ru' => $field['LABEL']],
				'LIST_COLUMN_LABEL' => ['ru' => $field['LABEL']],
				'USER_TYPE_ID'      => $field['TYPE'],
			]);
		}
	}

	/**
	 * Создаёт пользовательские поля контакта для отельной специфики.
	 */
	private function setupContactFields(): void
	{
		$fields = [
			['FIELD_NAME' => 'UF_DOCUMENT_TYPE',   'LABEL' => 'Тип документа',   'TYPE' => 'string'],
			['FIELD_NAME' => 'UF_DOCUMENT_NUMBER', 'LABEL' => 'Номер документа', 'TYPE' => 'string'],
		];

		foreach ($fields as $field) {
			$this->call('crm.contact.userfield.add', [
				'FIELD_NAME'        => $field['FIELD_NAME'],
				'EDIT_FORM_LABEL'   => ['ru' => $field['LABEL']],
				'LIST_COLUMN_LABEL' => ['ru' => $field['LABEL']],
				'USER_TYPE_ID'      => $field['TYPE'],
			]);
		}
	}

	/**
	 * Найти или создать контакт в Битрикс24.
	 * Возвращает ID контакта.
	 */
	public function findOrCreateContact(Guest $guest): ?int
	{
		$existingId = $this->findContact($guest);
		if ($existingId) {
			$this->updateContact($existingId, $guest);
			return $existingId;
		}

		return $this->createContact($guest);
	}

	private function findContact(Guest $guest): ?int
	{
		$filter = [];
		if ($guest->email) {
			$filter['EMAIL'] = $guest->email;
		} elseif ($guest->phone) {
			$filter['PHONE'] = $guest->phone;
		} else {
			return null;
		}

		$response = $this->call('crm.contact.list', [
			'filter' => $filter,
			'select' => ['ID'],
		]);

		if ($response && !empty($response['result'])) {
			return (int) $response['result'][0]['ID'];
		}

		return null;
	}

	private function createContact(Guest $guest): ?int
	{
		$response = $this->call('crm.contact.add', [
			'fields' => $this->buildContactFields($guest),
		]);

		return $response ? (int) $response['result'] : null;
	}

	private function updateContact(int $contactId, Guest $guest): void
	{
		$this->call('crm.contact.update', [
			'id'     => $contactId,
			'fields' => $this->buildContactFields($guest),
		]);
	}

	/**
	 * Собирает поля контакта из данных гостя.
	 * Используется и при создании, и при обновлении.
	 */
	private function buildContactFields(Guest $guest): array
	{
		$fields = [
			'NAME'        => $guest->first_name,
			'SECOND_NAME' => $guest->middle_name,
			'LAST_NAME'   => $guest->last_name,
			'TYPE_ID'     => 'CLIENT',
			'SOURCE_ID'   => 'WEB',
		];

		if ($guest->birth_date) {
			$fields['BIRTHDATE'] = $guest->birth_date->format('Y-m-d');
		}

		if ($guest->email) {
			$fields['EMAIL'] = [
				['VALUE' => $guest->email, 'VALUE_TYPE' => 'WORK'],
			];
		}

		if ($guest->phone) {
			$fields['PHONE'] = [
				['VALUE' => $guest->phone, 'VALUE_TYPE' => 'WORK'],
			];
		}

		if ($guest->document_type) {
			$fields['UF_CRM_DOCUMENT_TYPE'] = $guest->document_type->value ?? (string) $guest->document_type;
		}

		if ($guest->document_number) {
			$fields['UF_CRM_DOCUMENT_NUMBER'] = $guest->document_number;
		}

		return $fields;
	}

	/**
	 * Создать сделку в Битрикс24.
	 */
	public function createDeal(Booking $booking, int $contactId): ?int
	{
		$response = $this->call('crm.deal.add', [
			'fields' => $this->buildDealFields($booking, $contactId),
		]);

		return $response ? (int) $response['result'] : null;
	}

	/**
	 * Обновить сделку в Битрикс24.
	 */
	public function updateDeal(int $dealId, Booking $booking, ?int $contactId = null): bool
	{
		$fields = $this->buildDealFields($booking, $contactId);

		$response = $this->call('crm.deal.update', [
			'id'     => $dealId,
			'fields' => $fields,
		]);

		return $response && $response['result'] === true;
	}

	/**
	 * Получает актуальные данные сделки из Битрикс24.
	 */
	public function getDeal(int $dealId): ?array
	{
		$response = $this->call('crm.deal.get', [
			'id' => $dealId,
		]);

		return $response['result'] ?? null;
	}

	/**
	 * Собирает поля сделки — общая логика для создания и обновления.
	 */
	private function buildDealFields(Booking $booking, ?int $contactId = null): array
	{
		$guest = $booking->getPrimaryGuest();

		// Список услуг через запятую, с количеством если больше 1
		$servicesText = $booking->services->map(function ($service) {
			$qty = $service->pivot->quantity;
			return $qty > 1 ? "{$service->name} x{$qty}" : $service->name;
		})->implode(', ');

		$fields = [
			'TITLE'                  => "Бронирование #{$booking->id} — {$guest->pivot->first_name} {$guest->pivot->last_name}",
			'OPPORTUNITY'            => $booking->total_price,
			'CURRENCY_ID'            => 'RUB',
			'STAGE_ID'               => $this->mapStatusToStage($booking->status->slug),
			'BEGINDATE'              => $booking->check_in_date->format('Y-m-d'),
			'CLOSEDATE'              => $booking->check_out_date->format('Y-m-d'),
			'UF_CRM_HOTEL_NAME'      => $booking->hotel->name,
			'UF_CRM_CHECK_IN_DATE'   => $booking->check_in_date->format('Y-m-d'),
			'UF_CRM_CHECK_OUT_DATE'  => $booking->check_out_date->format('Y-m-d'),
			'UF_CRM_ROOM_CATEGORY'   => $booking->category->name,
			'UF_CRM_ROOM'            => $booking->room->name ?? '',
			'UF_CRM_RATE_PLAN'       => $booking->plan->name,
			'UF_CRM_ADULTS_COUNT'    => $booking->adults_count,
			'UF_CRM_CHILDREN_COUNT'  => $booking->children_count,
			'UF_CRM_SERVICES'        => $servicesText,
			'UF_CRM_BOOKING_ID'      => $booking->id,
		];

		if ($this->pipelineId) {
			$fields['CATEGORY_ID'] = $this->pipelineId;
		}

		if ($contactId) {
			$fields['CONTACT_ID'] = $contactId;
		}

		return $fields;
	}

	/**
	 * Маппинг статусов бронирования на стадии сделки.
	 * Берётся из настроек интеграции, с запасным вариантом по умолчанию.
	 */
	private function mapStatusToStage(string $bookingStatus): string
	{
		return $this->stageMapping[$bookingStatus]
			?? $this->defaultStageMapping()[$bookingStatus]
			?? 'NEW';
	}

	/**
	 * Обратный маппинг — определяет наш статус по стадии Битрикс24.
	 * Возвращает null если стадия не соответствует ни одному известному статусу.
	 */
	public function mapStageToStatus(string $stageId): ?string
	{
		$mapping = !empty($this->stageMapping) ? $this->stageMapping : $this->defaultStageMapping();
		$found = array_search($stageId, $mapping, true);
		return $found !== false ? $found : null;
	}

	private function defaultStageMapping(): array
	{
		return [
			'new'         => 'NEW',
			'confirmed'   => 'PREPARATION', // подтверждено — "подготовка документов"
			'checked_in'  => 'EXECUTING',   // заселён — "в работе"
			'checked_out' => 'WON',
			'cancelled'   => 'LOSE',
			'no_show'     => 'LOSE',
		];
	}

	private function call(string $method, array $params = []): ?array
	{
		$url = "{$this->webhookUrl}{$method}";

		try {
			$response = Http::timeout(10)
				->withoutVerifying()
				->post($url, $params);

			$responseData = $response->json() ?? ['raw' => $response->body()];

			// Каждый реальный обмен данными с Битрикс24 — отдельная запись в журнале
			IntegrationLog::create([
				'integration_id' => $this->integrationId,
				'direction'      => 'outgoing',
				'payload'        => ['method' => $method, 'params' => $params],
				'response'       => $responseData,
				'http_status'    => $response->status(),
			]);

			return $response->successful() ? $responseData : null;
		} catch (\Exception $e) {
			IntegrationLog::create([
				'integration_id' => $this->integrationId,
				'direction'      => 'outgoing',
				'payload'        => ['method' => $method, 'params' => $params],
				'response'       => ['error' => $e->getMessage()],
				'http_status'    => null,
			]);

			return null;
		}
	}
}

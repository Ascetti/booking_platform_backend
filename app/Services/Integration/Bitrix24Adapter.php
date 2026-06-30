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
		$response = $this->call('crm.category.add', [
			'entityTypeId' => 2, // 2 = сделки
			'fields' => [
				'name'      => 'Бронирования отеля',
				'isDefault' => 'N',
			],
		]);

		if (!$response) {
			return null;
		}

		return (int) ($response['result']['category']['id'] ?? null);
	}

	/**
	 * Настраивает стадии для воронки бронирований.
	 * Возвращает маппинг [наш_статус => STAGE_ID].
	 */
	private function setupStages(int $pipelineId): array
	{
		$entityId = "DEAL_STAGE_{$pipelineId}";

		$existing = $this->call('crm.status.list', [
			'filter' => ['ENTITY_ID' => $entityId],
		]);

		$stageMapping = [];
		$systemStages = [];

		if ($existing && !empty($existing['result'])) {
			foreach ($existing['result'] as $stage) {
				if (($stage['SYSTEM'] ?? 'N') === 'Y') {
					// Запоминаем системные стадии по семантике
					$semantics = $stage['SEMANTICS'] ?? 'process';
					$systemStages[$semantics] = $stage;
				} else {
					// Удаляем несистемные дефолтные
					$this->call('crm.status.delete', ['id' => $stage['ID']]);
				}
			}
		}

		// Переименовываем системные стадии под наши
		// process = начальная стадия → "Новое бронирование"
		if (isset($systemStages['process'])) {
			$this->call('crm.status.update', [
				'id'     => $systemStages['process']['ID'],
				'fields' => ['NAME' => 'Новое бронирование'],
			]);
			$stageMapping['new'] = $systemStages['process']['STATUS_ID'];
		}

		// S = успешно → "Завершено (выселен)"
		if (isset($systemStages['S'])) {
			$this->call('crm.status.update', [
				'id'     => $systemStages['S']['ID'],
				'fields' => ['NAME' => 'Завершено (выселен)'],
			]);
			$stageMapping['checked_out'] = $systemStages['S']['STATUS_ID'];
		}

		// F = провалено → "Отменено"
		if (isset($systemStages['F'])) {
			$this->call('crm.status.update', [
				'id'     => $systemStages['F']['ID'],
				'fields' => ['NAME' => 'Отменено'],
			]);
			$stageMapping['cancelled'] = $systemStages['F']['STATUS_ID'];
		}

		// Добавляем оставшиеся наши стадии
		$stagesToCreate = [
			'confirmed'  => ['name' => 'Подтверждено',  'sort' => 20],
			'checked_in' => ['name' => 'Гость заселён', 'sort' => 30],
		];

		foreach ($stagesToCreate as $ourStatus => $stage) {
			$statusId = 'HOTEL_' . strtoupper($ourStatus);

			$response = $this->call('crm.status.add', [
				'fields' => [
					'ENTITY_ID' => $entityId,
					'STATUS_ID' => $statusId,
					'NAME'      => $stage['name'],
					'SORT'      => $stage['sort'],
					'SEMANTICS' => null,
				],
			]);

			if ($response && isset($response['result'])) {
				// Формируем полный STAGE_ID как C{pipelineId}:{STATUS_ID}
				$stageMapping[$ourStatus] = "C{$pipelineId}:{$statusId}";
			}
		}

		return $stageMapping;
	}

	/**
	 * Создаёт пользовательские поля сделки для отельной специфики.
	 */
	private function setupDealFields(): void
	{
		$fields = [
			['FIELD_NAME' => 'HOTEL_NAME',     'LABEL' => 'Отель',              'TYPE' => 'string'],
			['FIELD_NAME' => 'CHECK_IN_DATE',  'LABEL' => 'Дата заезда',        'TYPE' => 'date'],
			['FIELD_NAME' => 'CHECK_OUT_DATE', 'LABEL' => 'Дата выезда',        'TYPE' => 'date'],
			['FIELD_NAME' => 'ROOM_CATEGORY',  'LABEL' => 'Категория номера',   'TYPE' => 'string'],
			['FIELD_NAME' => 'ROOM',           'LABEL' => 'Номер',              'TYPE' => 'string'],
			['FIELD_NAME' => 'RATE_PLAN',      'LABEL' => 'Тариф',              'TYPE' => 'string'],
			['FIELD_NAME' => 'ADULTS_COUNT',   'LABEL' => 'Кол-во взрослых',           'TYPE' => 'integer'],
			['FIELD_NAME' => 'CHILDREN_COUNT', 'LABEL' => 'Кол-во детей',              'TYPE' => 'integer'],
			['FIELD_NAME' => 'SERVICES',       'LABEL' => 'Доп. услуги',        'TYPE' => 'string'],
			['FIELD_NAME' => 'BOOKING_ID',     'LABEL' => 'Номер бронирования',    'TYPE' => 'integer'],
		];

		foreach ($fields as $field) {
			$this->call('crm.deal.userfield.add', [
				'fields' => [
					'LABEL'   => ['ru' => $field['LABEL']],
					'FIELD_NAME'        => $field['FIELD_NAME'],
					'USER_TYPE_ID'      => $field['TYPE'],
				]
			]);
		}
	}

	/**
	 * Создаёт пользовательские поля контакта для отельной специфики.
	 */
	private function setupContactFields(): void
	{
		$fields = [
			['FIELD_NAME' => 'DOCUMENT_TYPE',   'LABEL' => 'Тип документа',   'TYPE' => 'string'],
			['FIELD_NAME' => 'DOCUMENT_NUMBER', 'LABEL' => 'Номер документа', 'TYPE' => 'string'],
		];

		foreach ($fields as $field) {
			$this->call('crm.contact.userfield.add', [
				'fields' => [
					'LABEL'   => ['ru' => $field['LABEL']],
					'FIELD_NAME'        => $field['FIELD_NAME'],
					'USER_TYPE_ID'      => $field['TYPE'],
				]
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
			$fields['UF_CRM_DOCUMENT_TYPE'] = $guest->document_type->label();
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
			'TITLE'                  => "Бронирование #{$booking->id}",
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

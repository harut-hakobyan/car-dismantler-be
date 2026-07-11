<?php

namespace App\Services\Telegram;

use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\Part;
use App\Models\TelegramSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TelegramBotService
{
    private const PAGE_SIZE = 8;

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (string) data_get($message, 'chat.id');
        $from = (array) data_get($message, 'from', []);
        $text = trim((string) data_get($message, 'text', ''));

        if ($chatId === '') {
            return;
        }

        if (! $this->isAllowed($from)) {
            $this->sendMessage($chatId, 'Այս Telegram հաշիվը թույլատրված չէ օգտագործել բոտը։');
            return;
        }

        $session = $this->sessionForChat($chatId, $from);

        if ($text === '/start') {
            $this->resetSession($session);
            $this->sendMainMenu($chatId);
            return;
        }

        if ($text === '/cancel') {
            $this->resetSession($session);
            $this->sendMainMenu($chatId);
            return;
        }

        if ($text === '/search' || $text === '/searchpart' || $text === '/search_parts') {
            $this->beginPartSearchFlow($session);
            return;
        }

        if (! $session->flow || ! $session->step) {
            $this->sendMainMenu($chatId);
            return;
        }

        $this->handleTextStep($session, $text);
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $chatId = (string) data_get($callbackQuery, 'message.chat.id');
        $from = (array) data_get($callbackQuery, 'from', []);
        $callbackId = (string) data_get($callbackQuery, 'id', '');
        $data = (string) data_get($callbackQuery, 'data', '');

        if ($chatId === '' || $data === '') {
            return;
        }

        if (! $this->isAllowed($from)) {
            $this->answerCallbackQuery($callbackId, 'Թույլատրված չէ');
            return;
        }

        $session = $this->sessionForChat($chatId, $from);
        $this->answerCallbackQuery($callbackId);

        if ($data === 'menu:add_car') {
            $this->beginCarFlow($session);
            return;
        }

        if ($data === 'menu:add_part') {
            $this->beginPartFlow($session);
            return;
        }

        if ($data === 'menu:list_cars') {
            $this->sendCarsList($session, 1);
            return;
        }

        if ($data === 'menu:list_parts') {
            $this->sendPartsList($session, 1);
            return;
        }

        if ($data === 'menu:search_parts') {
            $this->beginPartSearchFlow($session);
            return;
        }

        if ($data === 'menu:cancel') {
            $this->resetSession($session);
            $this->sendMainMenu($chatId);
            return;
        }

        if ($data === 'flow:cancel') {
            $this->resetSession($session);
            $this->sendMessage($chatId, 'Չեղարկված է։', $this->mainMenuMarkup());
            return;
        }

        if (str_starts_with($data, 'car_make_page:')) {
            $page = max(1, (int) substr($data, strlen('car_make_page:')));
            $this->sendMakePrompt($session, $page);
            return;
        }

        if (str_starts_with($data, 'car_make_select:')) {
            $makeId = (int) substr($data, strlen('car_make_select:'));
            $this->setSession($session, 'car', 'model', ['car_make_id' => $makeId]);
            $this->sendModelPrompt($session, 1);
            return;
        }

        if (str_starts_with($data, 'car_model_page:')) {
            $page = max(1, (int) substr($data, strlen('car_model_page:')));
            $this->sendModelPrompt($session, $page);
            return;
        }

        if (str_starts_with($data, 'car_model_select:')) {
            $modelId = (int) substr($data, strlen('car_model_select:'));
            $this->setSession($session, 'car', 'vin', [
                ...($session->payload ?? []),
                'car_model_id' => $modelId,
            ]);
            $this->sendMessage($chatId, 'Ուղարկեք VIN-ը։');
            return;
        }

        if (str_starts_with($data, 'car_color_select:')) {
            $colorKey = substr($data, strlen('car_color_select:'));
            $color = $this->colorOptions()[$colorKey] ?? $colorKey;
            $this->setSession($session, 'car', 'mileage', [
                ...($session->payload ?? []),
                'color' => $color,
            ]);
            $this->sendMessage($chatId, 'Ուղարկեք վազքը։');
            return;
        }

        if (str_starts_with($data, 'part_car_page:')) {
            $page = max(1, (int) substr($data, strlen('part_car_page:')));
            $this->sendCarPrompt($session, $page);
            return;
        }

        if (str_starts_with($data, 'part_car_select:')) {
            $carId = (int) substr($data, strlen('part_car_select:'));
            $this->setSession($session, 'part', 'name', ['car_id' => $carId]);
            $this->sendMessage($chatId, 'Ուղարկեք մասի անունը։');
            return;
        }

        if (str_starts_with($data, 'part_category_select:')) {
            $categoryKey = substr($data, strlen('part_category_select:'));
            $category = $this->categoryOptions()[$categoryKey] ?? $categoryKey;
            $this->setSession($session, 'part', 'condition', [
                ...($session->payload ?? []),
                'category' => $category,
            ]);
            $this->sendConditionPrompt($session);
            return;
        }

        if (str_starts_with($data, 'part_condition_select:')) {
            $conditionKey = substr($data, strlen('part_condition_select:'));
            $condition = $this->conditionOptions()[$conditionKey] ?? $conditionKey;
            $this->setSession($session, 'part', 'description', [
                ...($session->payload ?? []),
                'condition' => $condition,
            ]);
            $this->sendMessage($chatId, 'Ուղարկեք նկարագրությունը կամ գրեք "-"՝ բաց թողնելու համար։');
            return;
        }

        if (str_starts_with($data, 'cars_page:')) {
            $page = max(1, (int) substr($data, strlen('cars_page:')));
            $this->sendCarsList($session, $page);
            return;
        }

        if (str_starts_with($data, 'car_view:')) {
            $carId = (int) substr($data, strlen('car_view:'));
            $this->sendCarDetails($session, $carId);
            return;
        }

        if (str_starts_with($data, 'parts_page:')) {
            $page = max(1, (int) substr($data, strlen('parts_page:')));
            $this->sendPartsList($session, $page);
            return;
        }

        if (str_starts_with($data, 'part_view:')) {
            $partId = (int) substr($data, strlen('part_view:'));
            $this->sendPartDetails($session, $partId);
            return;
        }

        if (str_starts_with($data, 'part_search_page:')) {
            $page = max(1, (int) substr($data, strlen('part_search_page:')));
            $this->sendPartSearchResults($session, $page);
            return;
        }

        if (str_starts_with($data, 'part_search_view:')) {
            $partId = (int) substr($data, strlen('part_search_view:'));
            $this->sendPartSearchDetails($session, $partId);
            return;
        }

        if (str_starts_with($data, 'part_sell:')) {
            $partId = (int) substr($data, strlen('part_sell:'));
            $this->beginPartSellFlow($session, $partId);
            return;
        }

        if (str_starts_with($data, 'part_duplicate_price:')) {
            $price = (float) substr($data, strlen('part_duplicate_price:'));
            $this->completeDuplicatePartSave($session, $price);
            return;
        }

        if (str_starts_with($data, 'part_sell_qty:')) {
            $payload = explode(':', substr($data, strlen('part_sell_qty:')));
            $payloadPartId = (int) ($payload[0] ?? 0);
            $quantity = (int) ($payload[1] ?? 0);
            if ($payloadPartId > 0 && $quantity > 0) {
                $part = Part::find($payloadPartId);
                if (! $part) {
                    $this->sendMessage($chatId, 'Մասը չի գտնվել։', $this->mainMenuMarkup());
                    return;
                }

                $this->setSession($session, 'part_sell', 'price', [
                    ...($session->payload ?? []),
                    'part_id' => $payloadPartId,
                    'quantity' => $quantity,
                    'sale_price' => (float) $part->price,
                ]);
                $this->sendPartSellPricePrompt($session, $part);
            }
            return;
        }

        if ($data === 'part_sell_default_price') {
            $this->completePartSell($session);
            return;
        }

        if ($data === 'part_search_results') {
            $this->sendPartSearchResults($session, 1);
            return;
        }

        if ($data === 'car:save') {
            $this->saveCar($session);
            return;
        }

        if ($data === 'part:save') {
            $this->savePart($session);
            return;
        }
    }

    private function handleTextStep(TelegramSession $session, string $text): void
    {
        $chatId = $session->telegram_chat_id;
        $payload = $session->payload ?? [];

        try {
            if ($session->flow === 'car') {
                match ($session->step) {
                    'vin' => $this->acceptCarVin($session, $text),
                    'year' => $this->acceptCarYear($session, $text),
                    'mileage' => $this->acceptCarMileage($session, $text),
                    'purchase_price' => $this->acceptCarPurchasePrice($session, $text),
                    'sale_price' => $this->acceptCarSalePrice($session, $text),
                    default => $this->sendMessage($chatId, 'Օգտագործեք կոճակները կամ /cancel հրամանը։'),
                };
                return;
            }

            if ($session->flow === 'part') {
                match ($session->step) {
                    'name' => $this->acceptPartName($session, $text),
                    'sku' => $this->acceptPartSku($session, $text),
                    'description' => $this->acceptPartDescription($session, $text),
                    'price' => $this->acceptPartPrice($session, $text),
                    'quantity' => $this->acceptPartQuantity($session, $text),
                    default => $this->sendMessage($chatId, 'Օգտագործեք կոճակները կամ /cancel հրամանը։'),
                };
                return;
            }

            if ($session->flow === 'part_search') {
                if ($session->step === 'query') {
                    $this->acceptPartSearchQuery($session, $text);
                    return;
                }

                if ($session->step === 'results') {
                    $this->sendMessage($chatId, 'Օգտագործեք կոճակները կամ /cancel հրամանը։');
                    return;
                }
            }

            if ($session->flow === 'part_sell') {
                if ($session->step === 'price') {
                    $this->acceptPartSellPrice($session, $text);
                    return;
                }
            }
        } catch (ValidationException $exception) {
            $this->sendMessage($chatId, 'Սխալ մուտք է։');
            $this->repeatCurrentPrompt($session);
        }
    }

    private function beginCarFlow(TelegramSession $session): void
    {
        $this->setSession($session, 'car', 'make', []);
        $this->sendMakePrompt($session, 1);
    }

    private function beginPartFlow(TelegramSession $session): void
    {
        $this->setSession($session, 'part', 'car', []);
        $this->sendCarPrompt($session, 1);
    }

    private function beginPartSearchFlow(TelegramSession $session): void
    {
        $this->setSession($session, 'part_search', 'query', []);
        $this->sendMessage($session->telegram_chat_id, 'Գրեք մասի անունը, SKU-ն, կատեգորիան կամ վիճակը որոնելու համար։');
    }

    private function beginPartSellFlow(TelegramSession $session, int $partId): void
    {
        $part = Part::find($partId);

        if (! $part) {
            $this->sendMessage($session->telegram_chat_id, 'Մասը չի գտնվել։', $this->mainMenuMarkup());
            return;
        }

        if ($part->quantity <= 0) {
            $this->sendMessage($session->telegram_chat_id, 'Այս մասի պահեստը սպառվել է։', $this->mainMenuMarkup());
            return;
        }

        $this->setSession($session, 'part_sell', 'quantity', [
            'part_id' => $part->id,
            'quantity' => 1,
        ]);

        $this->sendMessage(
            $session->telegram_chat_id,
            sprintf('%s վաճառք։ Ընտրեք քանակը։', $part->name),
            $this->sellQuantityMarkup($part->id, $part->quantity)
        );
    }

    private function acceptPartSellPrice(TelegramSession $session, string $text): void
    {
        Validator::make(['sale_price' => $text], [
            'sale_price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $this->setSession($session, 'part_sell', 'confirm', [
            ...($session->payload ?? []),
            'sale_price' => (float) $text,
        ]);

        $this->completePartSell($session);
    }

    private function acceptCarVin(TelegramSession $session, string $text): void
    {
        Validator::make(['vin' => $text], [
            'vin' => ['required', 'string', 'min:5', 'max:120', Rule::unique('cars', 'vin')],
        ])->validate();

        $this->setSession($session, 'car', 'year', [
            ...($session->payload ?? []),
            'vin' => $text,
        ]);
        $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք տարին։');
    }

    private function acceptCarYear(TelegramSession $session, string $text): void
    {
        Validator::make(['year' => $text], [
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ])->validate();

        $this->setSession($session, 'car', 'color', [
            ...($session->payload ?? []),
            'year' => (int) $text,
        ]);
        $this->sendColorPrompt($session);
    }

    private function acceptCarMileage(TelegramSession $session, string $text): void
    {
        Validator::make(['mileage' => $text], [
            'mileage' => ['required', 'integer', 'min:0'],
        ])->validate();

        $this->setSession($session, 'car', 'purchase_price', [
            ...($session->payload ?? []),
            'mileage' => (int) $text,
        ]);
        $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք գնման գինը։');
    }

    private function acceptCarPurchasePrice(TelegramSession $session, string $text): void
    {
        Validator::make(['purchase_price' => $text], [
            'purchase_price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $this->setSession($session, 'car', 'sale_price', [
            ...($session->payload ?? []),
            'purchase_price' => (float) $text,
        ]);
        $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք վաճառքի գինը։');
    }

    private function acceptCarSalePrice(TelegramSession $session, string $text): void
    {
        Validator::make(['sale_price' => $text], [
            'sale_price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $payload = [
            ...($session->payload ?? []),
            'sale_price' => (float) $text,
        ];
        $this->setSession($session, 'car', 'confirm', $payload);
        $this->sendCarSummary($session, $payload);
    }

    private function acceptPartName(TelegramSession $session, string $text): void
    {
        Validator::make(['name' => $text], [
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ])->validate();

        $this->setSession($session, 'part', 'sku', [
            ...($session->payload ?? []),
            'name' => $text,
        ]);
        $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք SKU-ն։');
    }

    private function acceptPartSku(TelegramSession $session, string $text): void
    {
        Validator::make(['sku' => $text], [
            'sku' => ['required', 'string', 'max:60'],
        ])->validate();

        $this->setSession($session, 'part', 'category', [
            ...($session->payload ?? []),
            'sku' => $text,
        ]);
        $this->sendCategoryPrompt($session);
    }

    private function acceptPartDescription(TelegramSession $session, string $text): void
    {
        $description = $text === '-' ? '' : $text;
        Validator::make(['description' => $description], [
            'description' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $this->setSession($session, 'part', 'price', [
            ...($session->payload ?? []),
            'description' => $description,
        ]);
        $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք գինը։');
    }

    private function acceptPartPrice(TelegramSession $session, string $text): void
    {
        Validator::make(['price' => $text], [
            'price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $this->setSession($session, 'part', 'quantity', [
            ...($session->payload ?? []),
            'price' => (float) $text,
        ]);
        $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք քանակը։');
    }

    private function acceptPartQuantity(TelegramSession $session, string $text): void
    {
        Validator::make(['quantity' => $text], [
            'quantity' => ['required', 'integer', 'min:0'],
        ])->validate();

        $payload = [
            ...($session->payload ?? []),
            'quantity' => (int) $text,
            'status' => 'active',
        ];
        $this->setSession($session, 'part', 'confirm', $payload);
        $this->sendPartSummary($session, $payload);
    }

    private function acceptPartSearchQuery(TelegramSession $session, string $text): void
    {
        $query = trim($text);

        Validator::make(['query' => $query], [
            'query' => ['required', 'string', 'min:2', 'max:120'],
        ])->validate();

        $this->setSession($session, 'part_search', 'results', [
            'query' => $query,
        ]);

        $this->sendPartSearchResults($session, 1);
    }

    private function sendMainMenu(string $chatId): void
    {
        $this->sendHtmlMessage($chatId, '<b>Ընտրեք գործողությունը</b>', $this->mainMenuMarkup());
    }

    private function sendMakePrompt(TelegramSession $session, int $page): void
    {
        $query = CarMake::query()->orderBy('name');
        $total = $query->count();
        $makes = $query->forPage($page, self::PAGE_SIZE)->get();

        $keyboard = [];
        foreach ($makes->chunk(2) as $row) {
            $keyboard[] = $row->map(fn (CarMake $make) => [
                'text' => $make->name,
                'callback_data' => 'car_make_select:'.$make->id,
            ])->values()->all();
        }

        $keyboard[] = $this->paginationRow(
            $page,
            (int) ceil(max(1, $total) / self::PAGE_SIZE),
            'car_make_page:'
        );
        $keyboard[] = [['text' => 'Չեղարկել', 'callback_data' => 'flow:cancel']];

        $this->sendHtmlMessage($session->telegram_chat_id, '<b>Ընտրեք մակնիշը</b>', $this->inlineKeyboard($keyboard));
    }

    private function sendModelPrompt(TelegramSession $session, int $page): void
    {
        $makeId = (int) data_get($session->payload, 'car_make_id');
        $query = CarModel::query()
            ->where('car_make_id', $makeId)
            ->orderBy('name');
        $total = $query->count();
        $models = $query->forPage($page, self::PAGE_SIZE)->get();

        $keyboard = [];
        foreach ($models->chunk(2) as $row) {
            $keyboard[] = $row->map(fn (CarModel $model) => [
                'text' => $model->name,
                'callback_data' => 'car_model_select:'.$model->id,
            ])->values()->all();
        }

        $keyboard[] = $this->paginationRow(
            $page,
            (int) ceil(max(1, $total) / self::PAGE_SIZE),
            'car_model_page:'
        );
        $keyboard[] = [['text' => 'Չեղարկել', 'callback_data' => 'flow:cancel']];

        $make = CarMake::find($makeId);
        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            '<b>Ընտրեք մոդելը</b>'."\n".'համար՝ <b>'.self::escape($make?->name ?? 'ընտրված մակնիշ').'</b>',
            $this->inlineKeyboard($keyboard)
        );
    }

    private function sendColorPrompt(TelegramSession $session): void
    {
        $keyboard = [];
        foreach (array_chunk($this->colorOptions(), 3, true) as $row) {
            $keyboard[] = array_map(
                static fn (string $label, string $key) => [
                    'text' => $label,
                    'callback_data' => 'car_color_select:'.$key,
                ],
                array_values($row),
                array_keys($row)
            );
        }
        $keyboard[] = [['text' => 'Չեղարկել', 'callback_data' => 'flow:cancel']];

        $this->sendHtmlMessage($session->telegram_chat_id, '<b>Ընտրեք գույնը</b>', $this->inlineKeyboard($keyboard));
    }

    private function sendCarPrompt(TelegramSession $session, int $page): void
    {
        $query = Car::query()->orderByDesc('id');
        $total = $query->count();
        $cars = $query->forPage($page, self::PAGE_SIZE)->get();

        $keyboard = [];
        foreach ($cars->chunk(1) as $row) {
            $keyboard[] = $row->map(fn (Car $car) => [
                'text' => sprintf('%s %s %s - %s', $car->year, $car->make, $car->model, $car->vin),
                'callback_data' => 'part_car_select:'.$car->id,
            ])->values()->all();
        }

        $keyboard[] = $this->paginationRow(
            $page,
            (int) ceil(max(1, $total) / self::PAGE_SIZE),
            'part_car_page:'
        );
        $keyboard[] = [['text' => 'Չեղարկել', 'callback_data' => 'flow:cancel']];

        $this->sendHtmlMessage($session->telegram_chat_id, '<b>Ընտրեք դոնոր մեքենան</b>', $this->inlineKeyboard($keyboard));
    }

    private function sendCategoryPrompt(TelegramSession $session): void
    {
        $keyboard = [];
        foreach (array_chunk($this->categoryOptions(), 2, true) as $row) {
            $keyboard[] = array_map(
                static fn (string $label, string $key) => [
                    'text' => $label,
                    'callback_data' => 'part_category_select:'.$key,
                ],
                array_values($row),
                array_keys($row)
            );
        }
        $keyboard[] = [['text' => 'Չեղարկել', 'callback_data' => 'flow:cancel']];

        $this->sendHtmlMessage($session->telegram_chat_id, '<b>Ընտրեք կատեգորիան</b>', $this->inlineKeyboard($keyboard));
    }

    private function sendConditionPrompt(TelegramSession $session): void
    {
        $keyboard = [];
        foreach (array_chunk($this->conditionOptions(), 2, true) as $row) {
            $keyboard[] = array_map(
                static fn (string $label, string $key) => [
                    'text' => $label,
                    'callback_data' => 'part_condition_select:'.$key,
                ],
                array_values($row),
                array_keys($row)
            );
        }
        $keyboard[] = [['text' => 'Չեղարկել', 'callback_data' => 'flow:cancel']];

        $this->sendHtmlMessage($session->telegram_chat_id, '<b>Ընտրեք վիճակը</b>', $this->inlineKeyboard($keyboard));
    }

    private function sendCarSummary(TelegramSession $session, array $payload): void
    {
        $make = CarMake::find(data_get($payload, 'car_make_id'));
        $model = CarModel::find(data_get($payload, 'car_model_id'));

        $text = implode("\n", [
            '<b>Մեքենայի ստուգում</b>',
            'VIN: <b>'.self::escape((string) data_get($payload, 'vin')).'</b>',
            'Մակնիշ: <b>'.self::escape($make?->name ?? '-').'</b>',
            'Մոդել: <b>'.self::escape($model?->name ?? '-').'</b>',
            'Տարի: <b>'.self::escape((string) data_get($payload, 'year')).'</b>',
            'Գույն: <b>'.self::escape((string) data_get($payload, 'color')).'</b>',
            'Վազք: <b>'.self::escape((string) data_get($payload, 'mileage')).'</b>',
            'Գնման գին: <b>$'.number_format((float) data_get($payload, 'purchase_price'), 2).'</b>',
            'Վաճառքի գին: <b>$'.number_format((float) data_get($payload, 'sale_price'), 2).'</b>',
        ]);

        $this->sendHtmlMessage($session->telegram_chat_id, $text, $this->inlineKeyboard([
            [
                ['text' => '✅ Պահպանել մեքենան', 'callback_data' => 'car:save'],
                ['text' => '✖ Չեղարկել', 'callback_data' => 'flow:cancel'],
            ],
        ]));
    }

    private function sendPartSummary(TelegramSession $session, array $payload): void
    {
        $car = Car::find(data_get($payload, 'car_id'));

        $text = implode("\n", [
            '<b>Մասի ստուգում</b>',
            'Ավտոմեքենա: <b>'.self::escape($car ? sprintf('%s %s %s - %s', $car->year, $car->make, $car->model, $car->vin) : '-').'</b>',
            'Անուն: <b>'.self::escape((string) data_get($payload, 'name')).'</b>',
            'SKU: <b>'.self::escape((string) data_get($payload, 'sku')).'</b>',
            'Կատեգորիա: <b>'.self::escape((string) data_get($payload, 'category')).'</b>',
            'Վիճակ: <b>'.self::escape((string) data_get($payload, 'condition')).'</b>',
            'Նկարագրություն: <b>'.self::escape((string) (data_get($payload, 'description') ?: '-')).'</b>',
            'Գին: <b>$'.number_format((float) data_get($payload, 'price'), 2).'</b>',
            'Քանակ: <b>'.self::escape((string) data_get($payload, 'quantity')).'</b>',
        ]);

        $this->sendHtmlMessage($session->telegram_chat_id, $text, $this->inlineKeyboard([
            [
                ['text' => '✅ Պահպանել մասը', 'callback_data' => 'part:save'],
                ['text' => '✖ Չեղարկել', 'callback_data' => 'flow:cancel'],
            ],
        ]));
    }

    private function saveCar(TelegramSession $session): void
    {
        $payload = $session->payload ?? [];

        $validated = Validator::make($payload, [
            'vin' => ['required', 'string', 'min:5', 'max:120', Rule::unique('cars', 'vin')],
            'car_make_id' => ['required', 'integer', 'exists:car_makes,id'],
            'car_model_id' => ['required', 'integer', 'exists:car_models,id'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'color' => ['required', 'string', 'max:120'],
            'mileage' => ['required', 'integer', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $make = CarMake::findOrFail((int) $validated['car_make_id']);
        $model = CarModel::query()
            ->where('id', (int) $validated['car_model_id'])
            ->where('car_make_id', $make->id)
            ->firstOrFail();

        $car = Car::create([
            ...$validated,
            'make' => $make->name,
            'model' => $model->name,
            'status' => 'active',
        ]);

        $this->resetSession($session);
        $this->sendMessage($session->telegram_chat_id, 'Ավտոմեքենան պահպանվեց՝ ID '.$car->id.'.', $this->mainMenuMarkup());
    }

    private function savePart(TelegramSession $session): void
    {
        $payload = $session->payload ?? [];

        $validated = Validator::make($payload, [
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'sku' => ['required', 'string', 'max:60'],
            'category' => ['required', 'string', 'max:120'],
            'condition' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
        ])->validate();

        $matchingParts = Part::query()
            ->where('car_id', (int) $validated['car_id'])
            ->where('name', $validated['name'])
            ->where('category', $validated['category'])
            ->where('condition', $validated['condition'])
            ->orderBy('price')
            ->get();

        if ($matchingParts->isNotEmpty()) {
            $incomingPrice = round((float) $validated['price'], 2);
            $exactMatch = $matchingParts->first(function (Part $part) use ($incomingPrice): bool {
                return round((float) $part->price, 2) === $incomingPrice;
            });

            if ($exactMatch) {
                $this->incrementPartQuantity($session, $exactMatch, (int) $validated['quantity']);
                return;
            }

            $this->setSession($session, 'part_duplicate', 'price', [
                ...$validated,
                'selected_quantity' => (int) $validated['quantity'],
            ]);

            $this->sendDuplicatePartPricePrompt($session, $matchingParts, $incomingPrice);
            return;
        }

        Validator::make($validated, [
            'sku' => ['unique:parts,sku'],
        ])->validate();

        $part = Part::create([
            ...$validated,
            'status' => 'active',
            'image_url' => null,
        ]);

        $this->resetSession($session);
        $this->sendMessage($session->telegram_chat_id, 'Մասը պահպանվեց՝ ID '.$part->id.'.', $this->mainMenuMarkup());
    }

    private function sendDuplicatePartPricePrompt(TelegramSession $session, \Illuminate\Support\Collection $matchingParts, float $incomingPrice): void
    {
        $keyboard = [];
        $priceRows = [];

        foreach ($matchingParts->unique(fn (Part $part) => number_format((float) $part->price, 2, '.', '')) as $part) {
            $priceRows[] = [
                'text' => '$'.number_format((float) $part->price, 2),
                'callback_data' => 'part_duplicate_price:'.number_format((float) $part->price, 2, '.', ''),
            ];
        }

        foreach (array_chunk($priceRows, 2) as $row) {
            $keyboard[] = $row;
        }

        $keyboard[] = [['text' => 'Չեղարկել', 'callback_data' => 'flow:cancel']];

        $summary = [];
        foreach ($matchingParts->take(3) as $part) {
            $summary[] = sprintf(
                '#%d $%s x%d',
                $part->id,
                number_format((float) $part->price, 2),
                $part->quantity
            );
        }

        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            '<b>Այս մասը արդեն գոյություն ունի։</b>'."\n".
            'Ընտրեք գինը, որի վրա ավելացնել քանակը։'."\n".
            'Առկա՝ '.implode(', ', $summary)."\n".
            'Նոր գին՝ <b>$'.number_format($incomingPrice, 2).'</b>',
            $this->inlineKeyboard($keyboard)
        );
    }

    private function completeDuplicatePartSave(TelegramSession $session, float $selectedPrice): void
    {
        $payload = $session->payload ?? [];

        $validated = Validator::make($payload, [
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'sku' => ['required', 'string', 'max:60'],
            'category' => ['required', 'string', 'max:120'],
            'condition' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
        ])->validate();

        $part = Part::query()
            ->where('car_id', (int) $validated['car_id'])
            ->where('name', $validated['name'])
            ->where('category', $validated['category'])
            ->where('condition', $validated['condition'])
            ->whereRaw('ROUND(price, 2) = ?', [number_format($selectedPrice, 2, '.', '')])
            ->first();

        if (! $part) {
            $part = Part::query()
                ->where('car_id', (int) $validated['car_id'])
                ->where('name', $validated['name'])
                ->where('category', $validated['category'])
                ->where('condition', $validated['condition'])
                ->orderBy('id')
                ->first();
        }

        if (! $part) {
            $this->resetSession($session);
            $this->sendMessage($session->telegram_chat_id, 'Մասը չի գտնվել։', $this->mainMenuMarkup());
            return;
        }

        $part->update([
            'quantity' => $part->quantity + (int) $validated['quantity'],
            'price' => $selectedPrice,
        ]);

        $this->resetSession($session);
        $this->sendMessage(
            $session->telegram_chat_id,
            'Մասը արդեն գոյություն ուներ, քանակը ավելացվեց՝ ID '.$part->id.'.',
            $this->mainMenuMarkup()
        );
    }

    private function incrementPartQuantity(TelegramSession $session, Part $part, int $quantity): void
    {
        $part->update([
            'quantity' => $part->quantity + $quantity,
        ]);

        $this->resetSession($session);
        $this->sendMessage(
            $session->telegram_chat_id,
            'Մասը արդեն գոյություն ուներ, քանակը ավելացվեց՝ ID '.$part->id.'.',
            $this->mainMenuMarkup()
        );
    }

    private function sendCarsList(TelegramSession $session, int $page): void
    {
        $query = Car::query()->orderByDesc('id');
        $total = $query->count();
        $cars = $query->forPage($page, self::PAGE_SIZE)->get();

        $keyboard = [];
        foreach ($cars as $car) {
            $keyboard[] = [[
                'text' => sprintf('#%d %s %s %s', $car->id, $car->make, $car->model, $car->year),
                'callback_data' => 'car_view:'.$car->id,
            ]];
        }

        $keyboard[] = $this->paginationRow(
            $page,
            (int) ceil(max(1, $total) / self::PAGE_SIZE),
            'cars_page:'
        );
        $keyboard[] = [['text' => 'Մենյու', 'callback_data' => 'menu:cancel']];

        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            '<b>Ավտոմեքենաներ</b>'."\n".'Ընդամենը՝ <b>'.($total ?: 0).'</b>',
            $this->inlineKeyboard($keyboard)
        );
    }

    private function sendPartsList(TelegramSession $session, int $page): void
    {
        $query = Part::query()->orderByDesc('id');
        $total = $query->count();
        $parts = $query->forPage($page, self::PAGE_SIZE)->get();

        $keyboard = [];
        foreach ($parts as $part) {
            $keyboard[] = [[
                'text' => sprintf('#%d %s', $part->id, $part->name),
                'callback_data' => 'part_view:'.$part->id,
            ]];
        }

        $keyboard[] = $this->paginationRow(
            $page,
            (int) ceil(max(1, $total) / self::PAGE_SIZE),
            'parts_page:'
        );
        $keyboard[] = [['text' => 'Մենյու', 'callback_data' => 'menu:cancel']];

        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            '<b>Մասեր</b>'."\n".'Ընդամենը՝ <b>'.($total ?: 0).'</b>',
            $this->inlineKeyboard($keyboard)
        );
    }

    private function sendPartSearchResults(TelegramSession $session, int $page): void
    {
        $queryText = trim((string) data_get($session->payload, 'query', ''));

        if ($queryText === '') {
            $this->beginPartSearchFlow($session);
            return;
        }

        $query = Part::query()
            ->where(function ($builder) use ($queryText) {
                $builder->where('name', 'like', "%{$queryText}%")
                    ->orWhere('sku', 'like', "%{$queryText}%")
                    ->orWhere('category', 'like', "%{$queryText}%")
                    ->orWhere('condition', 'like', "%{$queryText}%")
                    ->orWhere('description', 'like', "%{$queryText}%");
            })
            ->orderByDesc('id');

        $total = $query->count();
        $parts = $query->forPage($page, self::PAGE_SIZE)->get();

        if ($total === 0) {
        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            '<b>Մասեր չեն գտնվել</b>'."\n".'որոնման համար՝ <i>'.self::escape($queryText).'</i>',
            $this->inlineKeyboard([
                [['text' => '🔎 Կրկին որոնել', 'callback_data' => 'menu:search_parts']],
                [['text' => '🏠 Մենյու', 'callback_data' => 'menu:cancel']],
            ])
        );
            return;
        }

        $keyboard = [];
        foreach ($parts as $part) {
            $keyboard[] = [[
                'text' => sprintf('• %s', $part->name),
                'callback_data' => 'part_search_view:'.$part->id,
            ]];
        }

        $keyboard[] = $this->paginationRow(
            $page,
            (int) ceil(max(1, $total) / self::PAGE_SIZE),
            'part_search_page:'
        );
        $keyboard[] = [['text' => '🔎 Կրկին որոնել', 'callback_data' => 'menu:search_parts']];
        $keyboard[] = [['text' => '🏠 Մենյու', 'callback_data' => 'menu:cancel']];

        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            '<b>Որոնման արդյունքներ</b>'."\n".'Հարցում՝ <i>'.self::escape($queryText).'</i>'."\n".'Համընկնումներ՝ <b>'.$total.'</b>',
            $this->inlineKeyboard($keyboard)
        );
    }

    private function sendPartSearchDetails(TelegramSession $session, int $partId): void
    {
        $part = Part::find($partId);

        if (! $part) {
            $this->sendMessage($session->telegram_chat_id, 'Մասը չի գտնվել։', $this->mainMenuMarkup());
            return;
        }

        $car = $part->car;
        $text = implode("\n", [
            '<b>'.$part->name.'</b> <code>#'.$part->id.'</code>',
            'SKU: <code>'.self::escape($part->sku).'</code>',
            'Ավտոմեքենա: <b>'.self::escape($car ? sprintf('%s %s %s - %s', $car->year, $car->make, $car->model, $car->vin) : '-').'</b>',
            'Կատեգորիա: <b>'.self::escape($part->category).'</b>',
            'Վիճակ: <b>'.self::escape($part->condition).'</b>',
            'Նկարագրություն: '.self::escape($part->description ?: '-'),
            'Գին: <b>$'.number_format((float) $part->price, 2).'</b>',
            'Քանակ: <b>'.$part->quantity.'</b>',
            'Կարգավիճակ: <b>'.self::escape($part->status).'</b>',
        ]);

        $this->sendHtmlMessage($session->telegram_chat_id, $text, $this->inlineKeyboard([
            [
                ['text' => '💰 Վաճառել', 'callback_data' => 'part_sell:'.$part->id],
                ['text' => '↩ Վերադարձ արդյունքներին', 'callback_data' => 'part_search_results'],
                ['text' => '🏠 Մենյու', 'callback_data' => 'menu:cancel'],
            ],
        ]));
    }

    private function completePartSell(TelegramSession $session): void
    {
        $payload = $session->payload ?? [];
        $partId = (int) data_get($payload, 'part_id');
        $quantity = (int) data_get($payload, 'quantity', 0);
        $salePrice = (float) data_get($payload, 'sale_price', 0);
        $customerName = 'Տեղում գնորդ';

        $part = Part::find($partId);
        if (! $part) {
            $this->resetSession($session);
            $this->sendMessage($session->telegram_chat_id, 'Մասը չի գտնվել։', $this->mainMenuMarkup());
            return;
        }

        Validator::make([
            'quantity' => $quantity,
            'sale_price' => $salePrice,
        ], [
            'quantity' => ['required', 'integer', 'min:1'],
            'sale_price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        if ($quantity > $part->quantity) {
            $this->sendMessage($session->telegram_chat_id, 'Քանակը բավարար չէ։', $this->mainMenuMarkup());
            return;
        }

        $remaining = $part->quantity - $quantity;
        $part->update([
            'quantity' => $remaining,
            'status' => $remaining === 0 ? 'inactive' : $part->status,
        ]);

        $total = round($salePrice * $quantity, 2);

        \App\Models\Order::create([
            'customer_name' => $customerName,
            'total' => $total,
            'status' => 'completed',
        ]);

        \App\Models\Activity::create([
            'label' => 'Վաճառված մաս',
            'description' => sprintf(
                '%s x%s վաճառված է %s-ին՝ $%s արժեքով։',
                $part->name,
                $quantity,
                $customerName,
                number_format($total, 2)
            ),
        ]);

        $this->resetSession($session);
        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            sprintf('<b>Վաճառված է</b> %s x <b>%s</b>%sՄնացել է՝ <b>%s</b>.', self::escape($part->name), $quantity, "\n", $remaining),
            $this->mainMenuMarkup()
        );
    }

    private function sendCarDetails(TelegramSession $session, int $carId): void
    {
        $car = Car::find($carId);

        if (! $car) {
            $this->sendMessage($session->telegram_chat_id, 'Ավտոմեքենան չի գտնվել։', $this->mainMenuMarkup());
            return;
        }

        $text = implode("\n", [
            'Ավտոմեքենա #'.$car->id,
            'VIN: '.$car->vin,
            'Մակնիշ: '.$car->make,
            'Մոդել: '.$car->model,
            'Տարի: '.$car->year,
            'Գույն: '.$car->color,
            'Վազք: '.$car->mileage,
            'Վիճակ: '.$car->status,
            'Գնում: '.$car->purchase_price,
            'Վաճառք: '.$car->sale_price,
        ]);

        $this->sendHtmlMessage($session->telegram_chat_id, $text, $this->inlineKeyboard([
            [
                ['text' => '↩ Վերադարձ ավտոմեքենաներին', 'callback_data' => 'menu:list_cars'],
                ['text' => '🏠 Մենյու', 'callback_data' => 'menu:cancel'],
            ],
        ]));
    }

    private function sendPartDetails(TelegramSession $session, int $partId): void
    {
        $part = Part::find($partId);

        if (! $part) {
            $this->sendMessage($session->telegram_chat_id, 'Մասը չի գտնվել։', $this->mainMenuMarkup());
            return;
        }

        $car = $part->car;
        $text = implode("\n", [
            'Մաս #'.$part->id,
            'Ավտոմեքենա: '.($car ? sprintf('%s %s %s - %s', $car->year, $car->make, $car->model, $car->vin) : '-'),
            'Անուն: '.$part->name,
            'SKU: '.$part->sku,
            'Կատեգորիա: '.$part->category,
            'Վիճակ: '.$part->condition,
            'Նկարագրություն: '.($part->description ?: '-'),
            'Գին: '.$part->price,
            'Քանակ: '.$part->quantity,
            'Կարգավիճակ: '.$part->status,
        ]);

        $this->sendHtmlMessage($session->telegram_chat_id, $text, $this->inlineKeyboard([
            [
                ['text' => '↩ Վերադարձ մասերին', 'callback_data' => 'menu:list_parts'],
                ['text' => '💰 Վաճառել', 'callback_data' => 'part_sell:'.$part->id],
                ['text' => '🏠 Մենյու', 'callback_data' => 'menu:cancel'],
            ],
        ]));
    }

    private function sendPartSellPricePrompt(TelegramSession $session, Part $part): void
    {
        $salePrice = (float) data_get($session->payload, 'sale_price', $part->price);

        $this->sendHtmlMessage(
            $session->telegram_chat_id,
            sprintf(
                '<b>%s</b>'."\n".'Լռելյայն վաճառքի գին՝ <b>$%s</b>'."\n".'Ուղարկեք նոր գին կամ սեղմեք լռելյայնը։',
                self::escape($part->name),
                number_format($salePrice, 2)
            ),
            $this->inlineKeyboard([
                [
                    ['text' => '✅ Օգտագործել լռելյայնը', 'callback_data' => 'part_sell_default_price'],
                    ['text' => '✖ Չեղարկել', 'callback_data' => 'menu:cancel'],
                ],
            ])
        );
    }

    private function repeatCurrentPrompt(TelegramSession $session): void
    {
        match ([$session->flow, $session->step]) {
            ['car', 'make'] => $this->sendMakePrompt($session, 1),
            ['car', 'model'] => $this->sendModelPrompt($session, 1),
            ['car', 'vin'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք VIN-ը։'),
            ['car', 'year'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք տարին։'),
            ['car', 'color'] => $this->sendColorPrompt($session),
            ['car', 'mileage'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք վազքը։'),
            ['car', 'purchase_price'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք գնման գինը։'),
            ['car', 'sale_price'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք վաճառքի գինը։'),
            ['part', 'car'] => $this->sendCarPrompt($session, 1),
            ['part', 'name'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք մասի անունը։'),
            ['part', 'sku'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք SKU-ն։'),
            ['part', 'category'] => $this->sendCategoryPrompt($session),
            ['part', 'condition'] => $this->sendConditionPrompt($session),
            ['part', 'description'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք նկարագրությունը կամ գրեք "-"՝ բաց թողնելու համար։'),
            ['part', 'price'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք գինը։'),
            ['part', 'quantity'] => $this->sendMessage($session->telegram_chat_id, 'Ուղարկեք քանակը։'),
            ['part_search', 'query'] => $this->sendMessage($session->telegram_chat_id, 'Գրեք մասի անունը, SKU-ն, կատեգորիան կամ վիճակը որոնելու համար։'),
            ['part_search', 'results'] => $this->sendPartSearchResults($session, 1),
            ['part_sell', 'price'] => ($part = Part::find((int) data_get($session->payload, 'part_id')))
                ? $this->sendPartSellPricePrompt($session, $part)
                : $this->sendMessage($session->telegram_chat_id, 'Մասը չի գտնվել։', $this->mainMenuMarkup()),
            default => null,
        };
    }

    private function sessionForChat(string $chatId, array $from): TelegramSession
    {
        return TelegramSession::query()->firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'telegram_user_id' => (string) data_get($from, 'id'),
                'payload' => [],
            ]
        );
    }

    private function setSession(TelegramSession $session, string $flow, string $step, array $payload): void
    {
        $session->fill([
            'flow' => $flow,
            'step' => $step,
            'payload' => $payload,
            'telegram_user_id' => $session->telegram_user_id,
        ])->save();
    }

    private function resetSession(TelegramSession $session): void
    {
        $session->fill([
            'flow' => null,
            'step' => null,
            'payload' => [],
        ])->save();
    }

    private function isAllowed(array $from): bool
    {
        $allowed = config('telegram.allowed_user_ids', []);
        if ($allowed === []) {
            return true;
        }

        return in_array((string) data_get($from, 'id'), $allowed, true);
    }

    private function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->request('sendMessage', $payload);
    }

    private function sendHtmlMessage(string $chatId, string $html, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->request('sendMessage', $payload);
    }

    private function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        if ($callbackQueryId === '') {
            return;
        }

        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $payload['text'] = $text;
        }

        $this->request('answerCallbackQuery', $payload);
    }

    private function request(string $method, array $payload): array
    {
        $token = config('telegram.bot_token');
        if (! $token) {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/{$method}", $payload);

        return $response->json() ?? [];
    }

    private function mainMenuMarkup(): array
    {
        return $this->inlineKeyboard([
            [
                ['text' => '🚗 Ավելացնել մեքենա', 'callback_data' => 'menu:add_car'],
                ['text' => '🧩 Ավելացնել մաս', 'callback_data' => 'menu:add_part'],
            ],
            [
                ['text' => '🚘 Իմ մեքենաները', 'callback_data' => 'menu:list_cars'],
                ['text' => '📦 Իմ մասերը', 'callback_data' => 'menu:list_parts'],
            ],
            [
                ['text' => '🔎 Որոնել մաս', 'callback_data' => 'menu:search_parts'],
            ],
            [
                ['text' => '🏠 Մենյու', 'callback_data' => 'menu:cancel'],
            ],
        ]);
    }

    private function colorOptions(): array
    {
        return [
            'black' => 'Սև',
            'white' => 'Սպիտակ',
            'gray' => 'Մոխրագույն',
            'silver' => 'Արծաթագույն',
            'blue' => 'Կապույտ',
            'red' => 'Կարմիր',
            'green' => 'Կանաչ',
            'yellow' => 'Դեղին',
            'orange' => 'Նարնջագույն',
            'brown' => 'Շագանակագույն',
            'beige' => 'Բեժ',
            'gold' => 'Ոսկեգույն',
            'purple' => 'Մանուշակագույն',
            'maroon' => 'Բորդո',
            'navy' => 'Ծովային կապույտ',
        ];
    }

    private function categoryOptions(): array
    {
        return [
            'engine' => 'Շարժիչ',
            'transmission' => 'Փոխանցման տուփ',
            'suspension' => 'Կախոց',
            'brake_system' => 'Արգելակային համակարգ',
            'electrical' => 'Էլեկտրականություն',
            'body_parts' => 'Թափքամասեր',
            'interior' => 'Սալոն',
            'exterior' => 'Արտաքին մասեր',
            'wheels_tires' => 'Անիվներ և անվադողեր',
            'lights' => 'Լույսեր',
            'cooling_system' => 'Սառեցման համակարգ',
            'exhaust' => 'Արտանետման համակարգ',
            'fuel_system' => 'Վառելիքի համակարգ',
            'other' => 'Այլ',
        ];
    }

    private function conditionOptions(): array
    {
        return [
            'new' => 'Նոր',
            'excellent' => 'Գերազանց',
            'good' => 'Լավ',
            'used' => 'Օգտագործված',
            'repair' => 'Պահանջում է վերանորոգում',
        ];
    }

    private function sellQuantityMarkup(int $partId, int $maxQuantity): array
    {
        $buttons = [];
        $limit = max(1, min($maxQuantity, 6));
        for ($i = 1; $i <= $limit; $i++) {
            $buttons[] = ['text' => (string) $i, 'callback_data' => 'part_sell_qty:'.$partId.':'.$i];
        }

        return $this->inlineKeyboard([
            $buttons,
            [
                ['text' => 'Չեղարկել', 'callback_data' => 'menu:cancel'],
            ],
        ]);
    }

    private function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function paginationRow(int $page, int $totalPages, string $prefix): array
    {
        $row = [];
        if ($page > 1) {
            $row[] = ['text' => 'Նախորդ', 'callback_data' => $prefix.($page - 1)];
        }

        if ($page < $totalPages) {
            $row[] = ['text' => 'Հաջորդ', 'callback_data' => $prefix.($page + 1)];
        }

        if ($row === []) {
            $row[] = ['text' => 'Թարմացնել', 'callback_data' => $prefix.$page];
        }

        return $row;
    }
}

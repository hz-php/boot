<?php

namespace App\Telegram;

use App\Models\User;
use Carbon\Carbon;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;
use App\Models\DeepseekRequest;
use Illuminate\Support\Facades\Request;
use DefStudio\Telegraph\Facades\Telegraph;


class Handler extends WebhookHandler
{
    private $requestCount = 0;

    public function handleUnknownCommand(Stringable $text): void
    {
        $telegramUserId = Request::input('message.from.id');

        // Проверяем, существует ли пользователь с таким telegram_id, и создаем нового, если нет

        $user = User::firstOrCreate([
            'name' => $telegramUserId,
            'email' => $telegramUserId . '@mail.ru',
            'password' => $telegramUserId
        ]);

        // Если пользователь только что создан, записываем время его регистрации
        if (!$user->registered_at) {
            $user->registered_at = Carbon::now();
            $user->save();
        }

        // Обработка команд
        if ($text->startsWith('/start')) {
            $this->reply('Привет я бот от Sapika. Введи команду /help и я расскажу, что я умею.');
        } elseif ($text->startsWith('/help')) {
            $this->reply('Я могу дать доступ к DeepSeek AI, но не более 10 запросов. Если ты согласен, напиши /startAi.');
        } elseif ($text->startsWith('/hello')) {
            $this->reply('Привет))');
        } elseif ($text->startsWith('/vip')) {
            $this->reply('Неограниченное число запросов за 100 рублей в месяц, связь с админом https://t.me/romasapet');
        } elseif ($text->startsWith('/support')) {
            $this->reply('Неограниченное число запросов за 100 рублей в месяц');
        } else {
            $this->reply('Неизвестная команда');
        }
    }

    public function handleChatMessage(Stringable $text): void
    {

        if ($text) {
            // Получаем объект сообщения
            $telegramId = Request::input('message.from.id');

            $user = User::where('name', $telegramId)->first();

            // Проверяем, найден ли пользователь
            if ($user) {
                // Получаем идентификатор пользователя
                $userId = $user->id;

                // Проверяем, является ли пользователь премиум
                if ($user->premium) {
                    // Если пользователь премиум, то ограничение на количество запросов не применяется
                    $response = $this->generateResponseFromDeepSeekV2($text);

                    $this->reply($response);
                } else {
                    // Проверяем, сколько запросов уже сделано этим пользователем
                    $userRequestsCount = DeepseekRequest::where('user_id', $userId)->count();

                    // Проверяем, достиг ли пользователь лимита в 10 запросов
                    if ($userRequestsCount >= 10) {
                        $this->reply('Вы использовали все бесплатные запросы. Пожалуйста, оформите подписку.');
                    } else {
                        // Создаем запись о запросе в базе данных
                        DeepseekRequest::create(['user_id' => $userId]);

                        // Увеличиваем счетчик запросов пользователя
                        $userRequestsCount++;

                        // Если пользователь еще не достиг лимита, выполняем запрос
                        $response = $this->generateResponseFromDeepSeekV2($text);

                        $this->reply($response);
                    }
                }
            } else {
                // Если пользователь не найден, выводим сообщение об ошибке
                $this->reply('Пользователь не найден.');
            }
        } else {
            $this->reply('Ошибка при получении сообщения.');
        }
    }


    public function startAi(): void
    {
        $this->reply('Добро пожаловать в DeepSeek AI. Теперь вы можете начать использовать наш сервис. Задайте любой вопрос я постараюсь вам помочь');
    }

    function generateResponseFromDeepSeekV2($prompt)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.deepseek.com/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
            "messages": [
                {
                    "content": "' . $prompt . '",
                    "role": "user"
                }
            ],
            "model": "deepseek-chat",
            "frequency_penalty": 0,
            "max_tokens": 2048,
            "presence_penalty": 0,
            "stop": null,
            "stream": false,
            "temperature": 1,
            "top_p": 1,
            "logprobs": false,
            "top_logprobs": null
        }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer sk-3f2ef8bbcb484bacbcb4810e7a7f2c93' // Замените <TOKEN> на ваш API ключ
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response);
        file_put_contents(__DIR__ . '/_DEBUG_', print_r($response->choices[0]->message->content, true));
        $message = $response->choices[0]->message->content;
        return $message;
    }
}

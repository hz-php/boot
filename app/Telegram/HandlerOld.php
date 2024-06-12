<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;

class Handler extends WebhookHandler
{
    public function handleUnknownCommand(Stringable $text): void
    {
        if ($text->startsWith('/start')) {
            $this->reply('Привет я бот от Sapika. Введи команду /help и я расскажу, что я умею.');
        } elseif ($text->startsWith('/help')) {
            $this->reply('Я могу дать доступ к DeepSeek AI это искусвенный интелект
             подробнее почитай по ссылке если интерестно https://www.deepseek.com/, но не более 10-ти бесплатных запросов. Если ты согласен напиши /startAi');
        } else {
            // Здесь вы можете добавить дополнительную логику обработки сообщений
            // Например, использование ChatGPT для ответа на сообщение
            $response = $this->generateResponseFromDeepSeekV2($text->value());
            $this->reply('Неизвестная команда');
        }
    }

    public function handleChatMessage(Stringable $text): void
    {
        $response = $this->generateResponseFromDeepSeekV2($text);
        $this->reply($response);
    }
    function generateResponseFromDeepSeekV2($prompt) {
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
            CURLOPT_POSTFIELDS =>'{
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
        $response =json_decode($response);
        file_put_contents(__DIR__ . '/_DEBUG_', print_r($response->choices[0]->message->content, true));
        $message = $response->choices[0]->message->content;
        return $message;
    }


}


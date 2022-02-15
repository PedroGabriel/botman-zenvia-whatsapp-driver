<?php

namespace BotMan\Drivers\ZenviaWhatsapp;

use BotMan\Drivers\ZenviaWhatsapp\Exceptions\ZenviaWhatsappConnectionException;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\Drivers\ZenviaWhatsapp\Extensions\User;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Drivers\Events\GenericEvent;
use Symfony\Component\HttpFoundation\Response;
use BotMan\BotMan\Messages\Attachments\Location;
use Symfony\Component\HttpFoundation\ParameterBag;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\Drivers\ZenviaWhatsapp\Exceptions\ZenviaWhatsappException;

class ZenviaWhatsappDriver extends HttpDriver
{
    const DRIVER_NAME = 'ZenviaWhatsapp';
    const API_URL = 'https://api.zenvia.com/v2/channels/whatsapp';
    const FILE_API_URL = 'https://api.zenvia.com/v2/channels/whatsapp';

    protected $endpoint = 'channels/whatsapp/messages';

    protected $messages = [];

    /** @var Collection */
    protected $queryParameters;

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $payload = (array) json_decode($request->getContent(), true);
        $this->payload = new ParameterBag($payload);

        if($this->payload->get('message')) {
            $message = $this->payload->get('message');
            if(isset($message['contents'][0]['payload'])){
                $message['contents'][0]['text'] = $message['contents'][0]['payload'];
            }
            $this->event = Collection::make($message);
        }
        $this->config = Collection::make($this->config->get('zenvia_whatsapp'));

        $params = [];
        $params['id'] = $this->payload->get('id');
        $params['timestamp'] = $this->payload->get('timestamp');
        $params['type'] = $this->payload->get('type');
        $params['subscriptionId'] = $this->payload->get('subscriptionId');
        $params['channel'] = $this->payload->get('channel');
        $params['direction'] = $this->payload->get('direction');
        $this->queryParameters = Collection::make($params);
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return User
     * @throws ZenviaWhatsappException
     * @throws ZenviaWhatsappConnectionException
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        return new User(
            $matchingMessage->getSender(),
            'no',
            'name',
            $matchingMessage->getSender()
        );
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        if($this->queryParameters->get('type') != 'MESSAGE') return false;
        if($this->queryParameters->get('direction') != 'IN') return false;
        if(!isset($this->event->get('contents')[0]['type'])) return false;
        if($this->event->get('contents')[0]['type'] != 'text') return false;
        if(is_null($this->event->get('from'))) return false;
        if($this->event->get('direction') != 'IN') return false;
        return true;
    }

    /**
     * This hide the inline keyboard, if is an interactive message.
     */
    public function messagesHandled()
    {
        // print_r("MESSAGE HANDLED");exit;
    }

    /**
     * @param  \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $this->loadMessages();
        }

        return $this->messages;
    }

    /**
     * Load messages.
     */
    public function loadMessages()
    {

        $event = $this->event->all();

        $messages = [
            new IncomingMessage(
                $this->event->get('contents')[0]['text'],
                isset($event['from']) ? $event['from'] : null,
                isset($event['to']) ? $event['to'] : null,
                $this->event
            ),
        ];

        $this->messages = $messages;
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * Convert a Question object into a valid
     * quick reply response object.
     *
     * @param \BotMan\BotMan\Messages\Outgoing\Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $replies = Collection::make($question->getButtons())->map(function ($button) {
            return [
                'id' => (string) $button['value'],
                'title' => (string) $button['text'],
            ];
        });

        return $replies->toArray();
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        // print_r($message);
        // echo "\n\n====================================\n\n";
        // print_r($matchingMessage);
        // echo "\n\n====================================\n\n";
        // print_r($matchingMessage->getRecipient());
        // exit;
        $recipient = $matchingMessage->getRecipient() === '' ? $matchingMessage->getSender() : $matchingMessage->getRecipient();
        $sender = $matchingMessage->getSender() === '' ? $matchingMessage->getRecipient() : $matchingMessage->getSender();
        $defaultAdditionalParameters = $this->config->get('default_additional_parameters', []);
        $parameters = array_merge_recursive([
            'from' => $recipient,
            'to' => $sender,
        ], $additionalParameters + $defaultAdditionalParameters);

        /*
         * If we send a Question with buttons, ignore
         * the text and append the question.
         */
        $contents = [];
        $contents['type'] = 'text';
        $this->endpoint = 'messages';
        if ($message instanceof Question) {
            $contents['type'] = 'button';
            $contents['body'] = $message->getText();
            $contents['buttons'] = $this->convertQuestion($message);
            if(count($contents['buttons']) > 3){
                unset($contents['buttons']);
                $contents['type'] = 'list';
                $contents['button'] = "Abrir";
                $contents['sections'] = [[
                    'title' => 'Selecione',
                    'rows' => $this->convertQuestion($message),
                ]];
            }
        } elseif ($message instanceof OutgoingMessage) {
            if ($message->getAttachment() !== null) {
                $attachment = $message->getAttachment();
                $contents['fileCaption'] = $message->getText();
                if ($attachment instanceof Image || $attachment instanceof Video || $attachment instanceof Audio || $attachment instanceof File) {
                    $contents['type'] = 'file';
                    $contents['fileUrl'] = $attachment->getUrl();
                    if (method_exists($attachment, 'getTitle') && $attachment->getTitle() !== null) {
                        $contents['fileCaption'] = $attachment->getTitle();
                    }
                } elseif ($attachment instanceof Location) {
                    $contents['type'] = 'location';
                    $contents['latitude'] = $attachment->getLatitude();
                    $contents['longitude'] = $attachment->getLongitude();
                    if (isset($parameters['name'])) {
                        $contents['name'] = $parameters['name'];
                    }
                    if (isset($parameters['address'])) {
                        $contents['address'] = $parameters['address'];
                    }
                    if (isset($parameters['url'])) {
                        $contents['url'] = $parameters['url'];
                    }
                }
                //  elseif ($attachment instanceof Contact) {
                //     $contents['type'] = 'contacts';
                //     $contents['phone_number'] = $attachment->getPhoneNumber();
                //     $contents['first_name'] = $attachment->getFirstName();
                //     $contents['last_name'] = $attachment->getLastName();
                //     $contents['user_id'] = $attachment->getUserId();
                //     if (null !== $attachment->getVcard()) {
                //         $contents['vcard'] = $attachment->getVcard();
                //     }
                // }
            } else {
                $contents['text'] = $message->getText();
            }
        } else {
            $contents['text'] = $message;
        }

        $parameters['contents'][] = $contents;
        return $parameters;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        $headers = [];
        $headers[] = "X-API-TOKEN: {$this->config->get('token')}";
        $headers[] = "Content-Type: application/json";
        if ($this->config->get('throw_http_exceptions')) {
            return $this->postWithExceptionHandling($this->buildApiUrl($this->endpoint), [], $payload, $headers, true);
        }

        return $this->http->post($this->buildApiUrl($this->endpoint), [], $payload, $headers, true);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->config->get('token'));
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $parameters = array_replace_recursive([
            'chat_id' => $matchingMessage->getRecipient(),
        ], $parameters);

        $headers = [];
        $headers[] = "X-API-TOKEN: {$this->config->get('token')}";
        $headers[] = "Content-Type: application/json";

        if ($this->config->get('throw_http_exceptions')) {
            return $this->postWithExceptionHandling($this->buildApiUrl($endpoint), [], $parameters);
        }
        return $this->http->post($this->buildApiUrl($endpoint), [], $parameters, $headers);
    }

    /**
     * Generate the API url for the given endpoint.
     *
     * @param $endpoint
     * @return string
     */
    protected function buildApiUrl($endpoint)
    {
        return self::API_URL.'/'.$endpoint;
    }

    /**
     * Generate the File-API url for the given endpoint.
     *
     * @param $endpoint
     * @return string
     */
    protected function buildFileApiUrl($endpoint)
    {
        return self::FILE_API_URL.'/'.$endpoint;
    }

    /**
     * @param $url
     * @param array $urlParameters
     * @param array $postParameters
     * @param array $headers
     * @param bool $asJSON
     * @param int $retryCount
     * @return Response
     * @throws ZenviaWhatsappConnectionException
     */
    private function postWithExceptionHandling(
        $url,
        array $urlParameters = [],
        array $postParameters = [],
        array $headers = [],
        $asJSON = false,
        int $retryCount = 0
    ) {
        $headers = [];
        $headers[] = "X-API-TOKEN: {$this->config->get('token')}";
        $headers[] = "Content-Type: application/json";

        $response = $this->http->post($url, $urlParameters, $postParameters, $headers, $asJSON);
        $responseData = json_decode($response->getContent(), true);
        if ($response->isOk() && isset($responseData['ok']) && true ===  $responseData['ok']) {
            return $response;
        } elseif ($this->config->get('retry_http_exceptions') && $retryCount <= $this->config->get('retry_http_exceptions')) {
            $retryCount++;
            if ($response->getStatusCode() == 429 && isset($responseData['retry_after']) && is_numeric($responseData['retry_after'])) {
                usleep($responseData['retry_after'] * 1000000);
            } else {
                $multiplier = $this->config->get('retry_http_exceptions_multiplier')??2;
                usleep($retryCount*$multiplier* 1000000);
            }
            return $this->postWithExceptionHandling($url, $urlParameters, $postParameters, $headers, $asJSON, $retryCount);
        }
        $responseData['description'] = $responseData['description'] ?? 'No description';
        $responseData['error_code'] = $responseData['error_code'] ?? 'No error code';
        $responseData['parameters'] = $responseData['parameters'] ?? 'No parameters';


        $message = "Status Code: {$response->getStatusCode()}\n".
            "Description: ".print_r($responseData['description'], true)."\n".
            "Error Code: ".print_r($responseData['error_code'], true)."\n".
            "Parameters: ".print_r($responseData['parameters'], true)."\n".
            "URL: $url\n".
            "URL Parameters: ".print_r($urlParameters, true)."\n".
            "Post Parameters: ".print_r($postParameters, true)."\n".
            "Headers: ". print_r($headers, true)."\n";

        $message = str_replace($this->config->get('token'), 'ZENVIA-WHATSAPP-TOKEN-HIDDEN', $message);
        throw new \Exception($message);
    }
}

<?php

namespace BotMan\Drivers\ZenviaWhatsapp;

use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\Drivers\ZenviaWhatsapp\Exceptions\ZenviaWhatsappAttachmentException;

class ZenviaWhatsappVideoDriver extends ZenviaWhatsappDriver
{
    const DRIVER_NAME = 'ZenviaWhatsappVideo';

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->event->get('from')) && (! is_null($this->event->get('video')) || ! is_null($this->event->get('video_note')));
    }

    /**
     * @return bool
     */
    public function hasMatchingEvent()
    {
        return false;
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
        $message = new IncomingMessage(
            Video::PATTERN,
            $this->event->get('from')['id'],
            $this->event->get('chat')['id'],
            $this->event
        );
        $message->setVideos($this->getVideos());

        $this->messages = [$message];
    }

    /**
     * Retrieve a image from an incoming message.
     * @return array A download for the image file.
     * @throws ZenviaWhatsappAttachmentException
     */
    private function getVideos()
    {
        $video = $this->event->get('video') ?: $this->event->get('video_note');
        $response = $this->http->get($this->buildApiUrl('getFile'), [
            'file_id' => $video['file_id'],
        ]);

        $responseData = json_decode($response->getContent());

        if ($response->getStatusCode() !== 200) {
            throw new ZenviaWhatsappAttachmentException('Error retrieving file url: '.$responseData->description);
        }

        $url = $this->buildFileApiUrl($responseData->result->file_path);

        return [new Video($url, $video)];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }
}

<?php

namespace BotMan\Drivers\ZenviaWhatsapp;

use BotMan\BotMan\Messages\Attachments\Image;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\Drivers\ZenviaWhatsapp\Exceptions\ZenviaWhatsappAttachmentException;

class ZenviaWhatsappPhotoDriver extends ZenviaWhatsappDriver
{
    const DRIVER_NAME = 'ZenviaWhatsappPhoto';

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return !is_null($this->event) && !is_null($this->event->get('from')) && !is_null($this->event->get('photo'));
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
            Image::PATTERN,
            $this->event->get('from')['id'],
            $this->event->get('chat')['id'],
            $this->event
        );
        $message->setImages($this->getImages());

        $this->messages = [$message];
    }

    /**
     * Retrieve a image from an incoming message.
     * @return array A download for the image file.
     * @throws ZenviaWhatsappAttachmentException
     */
    private function getImages()
    {
        $photos = $this->event->get('photo');
        $largetstPhoto = array_pop($photos);
        $response = $this->http->get($this->buildApiUrl('getFile'), [
            'file_id' => $largetstPhoto['file_id'],
        ]);

        $responseData = json_decode($response->getContent());

        if ($response->getStatusCode() !== 200) {
            throw new ZenviaWhatsappAttachmentException('Error retrieving file url: '.$responseData->description);
        }

        $url = $this->buildFileApiUrl($responseData->result->file_path);

        return [new Image($url, $largetstPhoto)];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }
}

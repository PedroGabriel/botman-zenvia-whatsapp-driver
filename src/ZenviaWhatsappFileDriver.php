<?php

namespace BotMan\Drivers\ZenviaWhatsapp;

use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\Drivers\ZenviaWhatsapp\Exceptions\ZenviaWhatsappAttachmentException;

class ZenviaWhatsappFileDriver extends ZenviaWhatsappDriver
{
    const DRIVER_NAME = 'ZenviaWhatsappFile';

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->event->get('from')) && (! is_null($this->event->get('document')));
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
            File::PATTERN,
            $this->event->get('from')['id'],
            $this->event->get('chat')['id'],
            $this->event
        );
        $message->setFiles($this->getFiles());

        $this->messages = [$message];
    }

    /**
     * Retrieve a file from an incoming message.
     * @return array A download for the files.
     * @throws ZenviaWhatsappAttachmentException
     */
    private function getFiles()
    {
        $file = $this->event->get('document');

        $response = $this->http->get($this->buildApiUrl('getFile'), [
            'file_id' => $file['file_id'],
        ]);

        $responseData = json_decode($response->getContent());

        if ($response->getStatusCode() !== 200) {
            throw new ZenviaWhatsappAttachmentException('Error retrieving file url: '.$responseData->description);
        }

        $url = $this->buildFileApiUrl($responseData->result->file_path);

        return [new File($url, $file)];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }
}

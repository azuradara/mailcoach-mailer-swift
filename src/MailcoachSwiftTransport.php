<?php

namespace AzuraDara\MailcoachMailerSwift;

use AzuraDara\MailcoachMailerSwift\Exceptions\EmailNotValid;
use AzuraDara\MailcoachMailerSwift\Exceptions\NoHostSet;
use AzuraDara\MailcoachMailerSwift\Exceptions\NotAllowedToSendMail;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Swift_Events_EventListener;
use Swift_Mime_Attachment;
use Swift_Mime_MimePart;
use Swift_Mime_SimpleMessage;
use Swift_Transport;

class MailcoachSwiftTransport implements Swift_Transport
{
    protected Client $client;

    protected string $token;

    protected ?string $host;

    public function __construct(
        string $token,
        ?string $host = null,
        array $config = []
    ) {
        $this->token = $token;
        $this->host = $host;
        $this->client = new Client(array_merge($config, [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$this->token}",
            ],
        ]));
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function isStarted()
    {
        return true;
    }

    public function start()
    {
        return true;
    }

    public function stop()
    {
        return true;
    }

    public function ping()
    {
        return true;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        if (!$this->host) {
            throw NoHostSet::make();
        }

        $payload = $this->getPayload($message);

        try {
            $response = $this->client->post("https://{$this->host}/api/transactional-mails/send", [
                'json' => $payload,
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $content = $response->getBody()->getContents();

            if ($statusCode === 403) {
                throw NotAllowedToSendMail::make($content);
            }

            if ($statusCode === 422) {
                throw EmailNotValid::make($content);
            }

            throw $e;
        }

        return $this->numberOfRecipients($message);
    }

    protected function getPayload(Swift_Mime_SimpleMessage $message): array
    {
        $from = $message->getFrom();
        $fromAddress = '';
        foreach ($from as $email => $name) {
            if ($name) {
                $fromAddress = sprintf('%s <%s>', $name, $email);
            } else {
                $fromAddress = $email;
            }
            break;
        }

        $recipients = array_merge(
            (array)$message->getTo(),
            (array)$message->getCc(),
            (array)$message->getBcc(),
        );

        $payload = [
            'from'        => $fromAddress,
            'to'          => implode(',', $this->stringifyAddresses($recipients)),
            'cc'          => implode(',', $this->stringifyAddresses((array)$message->getCc())),
            'bcc'         => implode(',', $this->stringifyAddresses((array)$message->getBcc())),
            'reply_to'    => implode(',', $this->stringifyAddresses((array)$message->getReplyTo())),
            'subject'     => $message->getSubject(),
            'text'        => null,
            'html'        => null,
            'attachments' => $this->getAttachments($message),
        ];

        $this->setBody($payload, $message);

        foreach ($message->getHeaders()->getAll() as $header) {
            $name = $header->getFieldName();

            if ($name === 'X-Mailcoach-Transactional-Mail') {
                if (isset($payload['mail_name'])) {
                    throw new Exception('Mailcoach only allows a single transactional mail to be defined.');
                }
                $payload['mail_name'] = $header->getFieldBody();
            }

            if (strpos($name, 'X-Mailcoach-Replacement-') === 0) {
                $key = substr($name, strlen('X-Mailcoach-Replacement-'));
                $payload['replacements'][$key] = json_decode($header->getFieldBody(), true);
            }

            if ($name === 'X-Mailcoach-Mailer') {
                $payload['mailer'] = $header->getFieldBody();
            }

            if ($name === 'X-Mailcoach-Fake') {
                $payload['fake'] = $header->getFieldBody();
            }
        }

        return $payload;
    }

    protected function stringifyAddresses(array $addresses): array
    {
        $list = [];
        foreach ($addresses as $email => $name) {
            if ($name) {
                $list[] = sprintf('%s <%s>', $name, $email);
            } else {
                $list[] = $email;
            }
        }

        return $list;
    }

    protected function getAttachments(Swift_Mime_SimpleMessage $message): array
    {
        $attachments = [];

        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Mime_Attachment) {
                $filename = $child->getFilename();
                $attachment = [
                    'name'         => $filename,
                    'content'      => base64_encode($child->getBody()),
                    'content_type' => $child->getContentType(),
                ];

                if ($child->getDisposition() === 'inline') {
                    $attachment['content_id'] = 'cid:' . $filename;
                }

                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    protected function setBody(array &$payload, Swift_Mime_SimpleMessage $message)
    {
        $contentType = $message->getContentType();
        $body = $message->getBody();

        if ($contentType === 'text/plain') {
            $payload['text'] = $body;
        } elseif ($contentType === 'text/html') {
            $payload['html'] = $body;
        } else {
            foreach ($message->getChildren() as $child) {
                if ($child instanceof Swift_Mime_MimePart) {
                    if ($child->getContentType() === 'text/plain') {
                        $payload['text'] = $child->getBody();
                    } elseif ($child->getContentType() === 'text/html') {
                        $payload['html'] = $child->getBody();
                    }
                }
            }
            if ($body) {
                if (empty($payload['text']) && !empty($payload['html'])) {
                    $payload['text'] = $body;
                }
                if (!empty($payload['text']) && empty($payload['html'])) {
                    $payload['html'] = $body;
                }
            }
        }
    }

    protected function numberOfRecipients(Swift_Mime_SimpleMessage $message): int
    {
        return count((array)$message->getTo()) + count((array)$message->getCc()) + count((array)$message->getBcc());
    }

    public function registerPlugin(Swift_Events_EventListener $plugin) {}
}

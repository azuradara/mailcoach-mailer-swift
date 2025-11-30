<?php

namespace AzuraDara\MailcoachMailerSwift\Exceptions;

use Exception;

class NotAllowedToSendMail extends Exception
{
    public static function make(string $reason): NotAllowedToSendMail
    {
        return new self($reason);
    }
}

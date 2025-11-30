<?php

namespace Dara\MailcoachMailerSwift\Exceptions;

use Exception;

class NotAllowedToSendMail extends Exception
{
    public static function make(string $reason)
    {
        return new self($reason);
    }
}

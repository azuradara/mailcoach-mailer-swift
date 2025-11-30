<?php

namespace Dara\MailcoachMailerSwift\Exceptions;

use Exception;

class EmailNotValid extends Exception
{
    public static function make(string $reason): self
    {
        return new static("Could not send email because it's not valid. Mailcoach responded with: {$reason}");
    }
}

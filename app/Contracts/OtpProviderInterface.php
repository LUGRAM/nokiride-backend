<?php

namespace App\Contracts;

interface OtpProviderInterface
{
    public function send(string $phoneNumber, string $code): void;
}

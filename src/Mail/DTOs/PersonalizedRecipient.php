<?php

namespace App\Mail\DTOs;

class PersonalizedRecipient
{
    private string $email;
    private array $personalizedVars;

    public function __construct(string $email, array $personalizedVars = [])
    {
        $this->email = $email;
        $this->personalizedVars = $personalizedVars;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPersonalizedVars(): array
    {
        return $this->personalizedVars;
    }
} 
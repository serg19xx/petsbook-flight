<?php

namespace App\Mail\Contracts;

use App\Mail\DTOs\PersonalizedRecipient;

interface MailProviderInterface
{
    /**
     * @param string|PersonalizedRecipient[] $to
     * @param string $subject
     * @param string $body
     * @param array $attachments
     * @param array $templateData
     * @param string|null $templateId
     * @return bool
     */
    public function send(
        string|array $to, 
        string $subject, 
        string $body, 
        array $attachments = [], 
        array $templateData = [],
        ?string $templateId = null
    ): bool;
    
    public function getConfig(): array;
    
    /**
     * @return bool
     */
    public function supportsPersonalization(): bool;
}
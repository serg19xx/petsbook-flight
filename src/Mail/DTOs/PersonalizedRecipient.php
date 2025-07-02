<?php

namespace App\Mail\DTOs;

use Twig\Environment;

class PersonalizedRecipient
{
    private string $subject;
    private string $template;
    private array $data;
    private static ?Environment $twig = null;

    public function __construct(string $subject, string $template, array $data = [])
    {
        $this->subject = $subject;
        $this->template = $template;
        $this->data = $data;
    }

    public static function setTwig(Environment $twig): void
    {
        self::$twig = $twig;
    }

    public static function getTwigInstance(): ?Environment
    {
        return self::$twig;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Render subject and template using Twig and $data.
     * Returns array: ['subject' => ..., 'template' => ...]
     * Ensures UTF-8 encoding for correct Cyrillic rendering.
     */
    public function render(): array
    {
        if (!self::$twig) {
            return [
                'subject' => $this->ensureUtf8($this->subject),
                'template' => $this->ensureUtf8($this->template)
            ];
        }

        $renderedSubject = self::$twig->createTemplate($this->subject)->render($this->data);
        $renderedTemplate = self::$twig->createTemplate($this->template)->render($this->data);

        return [
            'subject' => $this->ensureUtf8($renderedSubject),
            'template' => $this->ensureUtf8($renderedTemplate)
        ];
    }

    /**
     * Ensure string is UTF-8 encoded.
     */
    private function ensureUtf8(string $value): string
    {
        return mb_detect_encoding($value, 'UTF-8', true) ? $value : mb_convert_encoding($value, 'UTF-8');
    }
}
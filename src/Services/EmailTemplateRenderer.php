<?php

namespace App\Services;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

class EmailTemplateRenderer
{
    protected Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader());
    }

    /**
     * Рендерит шаблоны body и subject по отдельности и возвращает массив с результатами.
     *
     * @param string $bodyTemplate
     * @param string $subjectTemplate
     * @param array $data
     * @return array ['body' => ..., 'subject' => ...]
     */
    public function render(string $bodyTemplate, string $subjectTemplate, array $data = []): array
    {
        $bodyTemplateName = md5($bodyTemplate);
        $subjectTemplateName = md5($subjectTemplate);

        $this->twig->getLoader()->setTemplate($bodyTemplateName, $bodyTemplate);
        $this->twig->getLoader()->setTemplate($subjectTemplateName, $subjectTemplate);

        return [
            'body' => $this->twig->render($bodyTemplateName, $data),
            'subject' => $this->twig->render($subjectTemplateName, $data),
        ];
    }
}

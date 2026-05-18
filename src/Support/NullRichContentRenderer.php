<?php

namespace Lalalili\CourseCore\Support;

use Lalalili\CourseCore\Contracts\RichContentRendererContract;

class NullRichContentRenderer implements RichContentRendererContract
{
    public function renderContent(mixed $content): ?string
    {
        if ($content === null || $content === '') {
            return null;
        }

        if (is_array($content)) {
            return json_encode($content) ?: null;
        }

        return (string) $content;
    }

    public function renderFaqs(mixed $faqData): array
    {
        $faqs = is_array($faqData) ? $faqData : json_decode((string) $faqData, true);

        if (! is_array($faqs)) {
            return [];
        }

        $result = [];
        foreach ($faqs as $faq) {
            if (! isset($faq['question'])) {
                continue;
            }
            $answer = $faq['answer'] ?? null;
            $result[] = [
                'question' => $faq['question'],
                'answer' => is_string($answer) ? $answer : null,
            ];
        }

        return $result;
    }
}

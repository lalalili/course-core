<?php

namespace Lalalili\CourseCore\Contracts;

interface RichContentRendererContract
{
    /**
     * Convert a Tiptap JSON document (string or array) to an HTML string.
     * Returns null when $content is empty / null.
     */
    public function renderContent(mixed $content): ?string;

    /**
     * Convert a FAQ array (or JSON string) to [{question, answer}] with HTML answers.
     *
     * @return list<array{question: string, answer: string|null}>
     */
    public function renderFaqs(mixed $faqData): array;
}

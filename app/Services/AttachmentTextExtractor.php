<?php

namespace App\Services;

use App\Models\MessageAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class AttachmentTextExtractor
{
    private const MAX_CHARS = 100_000;

    /**
     * @var list<string>
     */
    private const TEXT_MIMES = [
        'text/plain',
        'text/markdown',
        'text/csv',
        'text/x-markdown',
        'application/json',
        'application/csv',
    ];

    /**
     * @var list<string>
     */
    private const TEXT_EXTS = [
        'txt', 'md', 'markdown', 'csv', 'json', 'log', 'pdf', 'docx',
    ];

    public function isSupported(?string $mime, ?string $originalName = null): bool
    {
        $mime = strtolower(trim((string) $mime));
        $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));

        if (in_array($mime, self::TEXT_MIMES, true)) {
            return true;
        }
        if ($mime === 'application/pdf'
            || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return true;
        }
        if ($ext !== '' && in_array($ext, self::TEXT_EXTS, true)) {
            return true;
        }

        return false;
    }

    public function ensureExtracted(MessageAttachment $attachment): MessageAttachment
    {
        $status = (string) ($attachment->extract_status ?: MessageAttachment::EXTRACT_IDLE);

        if ($status === MessageAttachment::EXTRACT_DONE) {
            return $attachment;
        }

        // Уже размечено (в т.ч. неподдерживаемый mime) — не гоняем повторно.
        if ($status === MessageAttachment::EXTRACT_ERROR) {
            return $attachment;
        }

        if (! $this->isSupported($attachment->mime, $attachment->original_name)) {
            return $this->markUnsupported($attachment);
        }

        return $this->extract($attachment);
    }

    public function extract(MessageAttachment $attachment): MessageAttachment
    {
        if (! $this->isSupported($attachment->mime, $attachment->original_name)) {
            return $this->markUnsupported($attachment);
        }

        $attachment->extract_status = MessageAttachment::EXTRACT_PENDING;
        $attachment->extract_error = null;
        $attachment->save();

        try {
            if (! Storage::disk('public')->exists($attachment->path)) {
                throw new \RuntimeException('Файл вложения не найден на диске.');
            }

            $absolute = Storage::disk('public')->path($attachment->path);
            $mime = strtolower(trim((string) ($attachment->mime ?? '')));
            $name = strtolower((string) $attachment->original_name);
            $ext = pathinfo($name, PATHINFO_EXTENSION);

            $text = $this->extractByType($absolute, $mime, $ext);
            $text = $this->normalizeText($text);

            if ($text === '') {
                $attachment->extract_status = MessageAttachment::EXTRACT_ERROR;
                $attachment->extracted_text = null;
                $attachment->extract_error = 'Не удалось извлечь текст из файла.';
                $attachment->save();

                return $attachment;
            }

            $attachment->extract_status = MessageAttachment::EXTRACT_DONE;
            $attachment->extracted_text = mb_substr($text, 0, self::MAX_CHARS);
            $attachment->extract_error = null;
            $attachment->save();
        } catch (Throwable $exception) {
            Log::warning('AttachmentTextExtractor failed: '.$exception->getMessage(), [
                'attachment_id' => $attachment->id,
            ]);
            $attachment->extract_status = MessageAttachment::EXTRACT_ERROR;
            $attachment->extracted_text = null;
            $attachment->extract_error = mb_substr($exception->getMessage(), 0, 1000);
            $attachment->save();
        }

        return $attachment;
    }

    private function markUnsupported(MessageAttachment $attachment): MessageAttachment
    {
        $mime = trim((string) ($attachment->mime ?: 'unknown'));
        $attachment->extract_status = MessageAttachment::EXTRACT_ERROR;
        $attachment->extracted_text = null;
        $attachment->extract_error = 'Формат без текстового извлечения ('.$mime.')';
        $attachment->save();

        return $attachment;
    }

    private function extractByType(string $absolute, string $mime, string $ext): string
    {
        if (in_array($mime, self::TEXT_MIMES, true)
            || in_array($ext, ['txt', 'md', 'markdown', 'csv', 'json', 'log'], true)) {
            return (string) file_get_contents($absolute);
        }

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return $this->extractPdf($absolute);
        }

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            || $ext === 'docx') {
            return $this->extractDocx($absolute);
        }

        throw new \RuntimeException('Формат не поддерживается для извлечения текста ('.$mime.').');
    }

    private function extractDocx(string $absolute): string
    {
        $zip = new ZipArchive();
        if ($zip->open($absolute) !== true) {
            throw new \RuntimeException('Не удалось открыть DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            throw new \RuntimeException('В DOCX нет word/document.xml.');
        }

        $xml = preg_replace('/<w:tab[^\/]*\/>/i', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p>/i', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:tr>/i', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $text;
    }

    private function extractPdf(string $absolute): string
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($absolute);

            return (string) $pdf->getText();
        }

        $raw = (string) file_get_contents($absolute);
        if ($raw === '') {
            throw new \RuntimeException('Пустой PDF.');
        }

        $chunks = [];
        if (preg_match_all('/\((\\\\.|[^\\\\)])*\)/s', $raw, $matches)) {
            foreach ($matches[0] as $match) {
                $inner = substr($match, 1, -1);
                $inner = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $inner);
                $inner = preg_replace('/\\\\([0-7]{1,3})/', '', $inner) ?? $inner;
                if (preg_match('/[а-яА-Яa-zA-Z0-9]{2,}/u', $inner)) {
                    $chunks[] = $inner;
                }
            }
        }

        $text = trim(implode(' ', $chunks));
        if ($text === '') {
            throw new \RuntimeException(
                'Не удалось извлечь текст из PDF (установите smalot/pdfparser или используйте текстовый PDF).'
            );
        }

        return $text;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }
}

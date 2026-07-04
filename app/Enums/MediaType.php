<?php

namespace App\Enums;

enum MediaType: string
{
    case Image = 'afbeelding';
    case Video = 'video';
    case Document = 'document';
    case Other = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Afbeelding',
            self::Video => 'Video',
            self::Document => 'Document',
            self::Other => 'Overig',
        };
    }

    public static function fromMime(string $mimeType): self
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::Image;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return self::Video;
        }

        return match ($mimeType) {
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain' => self::Document,
            default => self::Other,
        };
    }
}

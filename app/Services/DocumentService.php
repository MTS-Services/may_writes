<?php

namespace App\Services;

use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;

class DocumentService
{
    /**
     * Filesystem disk used for generated client DOCX files (same as `FILESYSTEM_DISK` / `filesystems.default`).
     */
    public static function documentsDisk(): Filesystem
    {
        return Storage::disk((string) config('filesystems.default'));
    }

    /**
     * @param  array<string, string>  $brief
     * @return array{path: string, filename: string, absolute_path: string}
     */
    public function generateVersionDocument(TrelloTask $task, TrelloTaskVersion $version, array $brief): array
    {
        $disk = self::documentsDisk();
        $safeName = Str::slug($task->customer->name);
        $safeTitle = Str::slug($task->title, '_');
        $cardId = $task->trello_card_id;

        $phpWord = new PhpWord;
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_US));
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();
        $section->addText($brief['title'] ?? 'Writing Brief', ['bold' => true, 'size' => 18, 'color' => '0D0D0B'], ['spaceAfter' => 240]);
        $section->addText('Client: '.$task->customer->name, ['size' => 10, 'color' => '7A7870']);
        $section->addText('Plan: '.($task->customer->plan?->name ?? 'N/A'), ['size' => 10, 'color' => '7A7870']);
        $section->addText('Version: '.$version->version_number, ['size' => 10, 'color' => '7A7870']);
        $section->addText('Processed: '.now()->format('F j, Y g:i A'), ['size' => 10, 'color' => '7A7870']);

        if ($version->was_truncated) {
            $section->addText(
                $version->truncated_notice ?? 'Description was truncated to match plan word limit.',
                ['size' => 10, 'color' => 'B45309', 'italic' => true],
                ['spaceAfter' => 120],
            );
        }

        $section->addLine(['weight' => 1, 'color' => 'E6E4DE', 'width' => 400, 'spaceAfter' => 240]);

        $sections = [
            'Description' => 'description_summary',
            'Content Type' => 'content_type',
            'Goal & Objective' => 'goal_objective',
            'Target Audience' => 'target_audience',
            'Tone & Style' => 'tone_style',
            'Length' => 'length_words',
            'CTA & Recommendations' => 'cta_recommendations',
            'References & Examples' => 'references_examples',
            'Additional Requirements' => 'additional_requirements',
            'Writer Notes' => 'writer_notes',
        ];

        foreach ($sections as $heading => $key) {
            $value = trim((string) ($brief[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            $section->addText($heading, ['bold' => true, 'size' => 12], ['spaceBefore' => 120, 'spaceAfter' => 60]);
            $section->addText($value, ['size' => 11]);
        }

        $section->addTextBreak(2);
        $section->addText('Original Request', ['bold' => true, 'size' => 12]);
        $section->addText($version->title, ['italic' => true]);

        if ($version->description) {
            $section->addText($version->description, ['color' => '7A7870', 'size' => 10]);
        }

        $filename = 'v'.$version->version_number.'_'.date('Y-m-d').'_'.$safeTitle.'.docx';
        $relativePath = 'clients/'.$safeName.'/'.$cardId.'/'.$filename;

        $disk->makeDirectory('clients/'.$safeName.'/'.$cardId);

        $absolutePath = $disk->path($relativePath);
        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        return [
            'path' => $relativePath,
            'filename' => $filename,
            'absolute_path' => $absolutePath,
        ];
    }
}

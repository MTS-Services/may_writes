<?php

namespace App\Services;

use App\Models\TrelloTask;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;

class DocumentService
{
    public string $storagePath;

    public function __construct()
    {
        $this->storagePath = storage_path('app/clients');
        File::ensureDirectoryExists($this->storagePath);
    }

    /**
     * @return array{path:string,filename:string,absolute_path:string}
     */
    public function generateTaskDocument(TrelloTask $task, string $summary): array
    {
        $safeName = Str::slug($task->customer->name);
        $safeTitle = Str::slug($task->title, '_');
        $directory = "{$this->storagePath}/{$safeName}";

        File::ensureDirectoryExists($directory);

        $phpWord = new PhpWord;
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_US));
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();
        $section->addText('Writing Brief — '.$task->title, ['bold' => true, 'size' => 18, 'color' => '0D0D0B'], ['spaceAfter' => 240]);
        $section->addText('Client: '.$task->customer->name, ['size' => 10, 'color' => '7A7870']);
        $section->addText('Plan: '.($task->customer->plan?->name ?? 'N/A'), ['size' => 10, 'color' => '7A7870']);
        $section->addText('Submitted: '.$task->created_at->format('F j, Y'), ['size' => 10, 'color' => '7A7870']);
        $section->addText('Trello Card ID: '.$task->trello_card_id, ['size' => 10, 'color' => '7A7870']);
        $section->addLine(['weight' => 1, 'color' => 'E6E4DE', 'width' => 400, 'spaceAfter' => 240]);

        foreach (preg_split('/\r\n|\r|\n/', $summary) as $line) {
            if ($line === '') {
                $section->addTextBreak(1);

                continue;
            }

            if (str_starts_with(trim($line), '**') && str_ends_with(trim($line), '**')) {
                $section->addText(trim($line, '*'), ['bold' => true, 'size' => 12]);

                continue;
            }

            $section->addText($line);
        }

        $section->addTextBreak(2);
        $section->addText('Original Request', ['bold' => true, 'size' => 12]);
        $section->addText($task->title, ['italic' => true]);

        if ($task->description) {
            $section->addText($task->description, ['color' => '7A7870', 'size' => 10]);
        }

        $filename = date('Y-m-d').'_'.$safeTitle.'.docx';
        $relativePath = 'clients/'.$safeName.'/'.$filename;
        $absolutePath = storage_path('app/'.$relativePath);
        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        return [
            'path' => $relativePath,
            'filename' => $filename,
            'absolute_path' => $absolutePath,
        ];
    }
}

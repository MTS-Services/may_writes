<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrelloSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_board_id',
        'background_id',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyImportLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyImportLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyImportLog query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyImportLog extends Model
{
    // 🚀 Avisa o Laravel qual é o nome exato da tabela no banco!
    protected $table = "legacy_import_logs";

    protected $guarded = [];
}

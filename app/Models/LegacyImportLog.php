<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyImportLog extends Model
{
    // 🚀 Avisa o Laravel qual é o nome exato da tabela no banco!
    protected $table = "legacy_imports_log";

    protected $guarded = [];
}

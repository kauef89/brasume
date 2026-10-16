<?php

use App\Models\Tenant;
use App\Models\LegacyOrder;
use Illuminate\Support\Facades\DB;

tenancy()->initialize(Tenant::find('nativa'));

LegacyOrder::where('import_tag', 'like', 'WP_DB_%')->delete();

$enriched = LegacyOrder::where('import_tag', 'like', '% | WP_DB_%')->get();

foreach($enriched as $order) {
    $cleanTag = explode(' | WP_DB_', $order->import_tag)[0];

    $order->update([
        'number' => null,
        'order_type' => null,
        'import_tag' => trim($cleanTag)
    ]);

    DB::table('legacy_order_items')->where('legacy_order_id', $order->id)->delete();
    DB::table('legacy_order_payments')->where('legacy_order_id', $order->id)->delete();
}

echo "✅ Banco de dados limpo! As Notas Fiscais foram resetadas e os duplicados apagados.\n";

tenancy()->end();

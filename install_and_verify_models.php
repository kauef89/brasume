<?php
$baseDir = __DIR__;

echo "================================================================================" . PHP_EOL;
echo "1. CRIANDO OS NOVOS MODELS EM app/Models/" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

// 1. OrderPaymentItem.php
file_put_contents($baseDir . '/app/Models/OrderPaymentItem.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentItem extends Pivot
{
    protected $table = 'order_payment_items';
    public $incrementing = true;
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:4',
        'amount'   => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'order_payment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->item();
    }
}
PHP);
echo " • OrderPaymentItem.php [OK]" . PHP_EOL;

// 2. ProductModifierGroup.php
file_put_contents($baseDir . '/app/Models/ProductModifierGroup.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifierGroup extends Pivot
{
    protected $table = 'modifier_group_product';
    public $incrementing = true;
    protected $guarded = [];

    protected $casts = [
        'excluded_options' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ProductModifier::class, 'modifier_group_id');
    }

    public function isOptionExcluded(int|string $optionId): bool
    {
        return in_array((int) $optionId, array_map('intval', $this->excluded_options ?? []), true);
    }
}
PHP);
echo " • ProductModifierGroup.php [OK]" . PHP_EOL;

// 3. PushSubscription.php
file_put_contents($baseDir . '/app/Models/PushSubscription.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';
    protected $guarded = [];

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }
}
PHP);
echo " • PushSubscription.php [OK]" . PHP_EOL;

// 4. ModifierItem.php
file_put_contents($baseDir . '/app/Models/ModifierItem.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierItem extends Model
{
    protected $table = 'modifier_items';
    protected $guarded = [];

    protected $casts = [
        'extra_price' => 'decimal:2',
    ];

    public function modifierOption(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_id');
    }

    public function ingredientProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
PHP);
echo " • ModifierItem.php [OK]" . PHP_EOL;

// 5. ShiftAttendance.php
file_put_contents($baseDir . '/app/Models/ShiftAttendance.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAttendance extends Model
{
    protected $table = 'shift_attendance';
    protected $guarded = [];

    protected $casts = [
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
        'daily_rate'     => 'decimal:2',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'account_payable_id');
    }

    public function payable(): BelongsTo
    {
        return $this->accountPayable();
    }
}
PHP);
echo " • ShiftAttendance.php [OK]" . PHP_EOL;

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "2. INJETANDO RELACIONAMENTOS NOS MODELS EXISTENTES" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

// Injeta em CashShift.php
$csPath = $baseDir . '/app/Models/CashShift.php';
$csCode = file_get_contents($csPath);
if (!str_contains($csCode, 'function dailyPayables()')) {
    $method = "\n    public function dailyPayables(): \\Illuminate\\Database\\Eloquent\\Relations\\HasManyThrough\n"
            . "    {\n"
            . "        return \$this->hasManyThrough(\n"
            . "            AccountPayable::class,\n"
            . "            ShiftAttendance::class,\n"
            . "            'cash_shift_id',\n"
            . "            'id',\n"
            . "            'id',\n"
            . "            'account_payable_id'\n"
            . "        );\n"
            . "    }\n";
    $csCode = preg_replace('/\}\s*$/', $method . "}\n", $csCode);
    file_put_contents($csPath, $csCode);
    echo " • CashShift::dailyPayables() [Injetado]" . PHP_EOL;
} else {
    echo " • CashShift::dailyPayables() [Já Existia]" . PHP_EOL;
}

// Injeta em Payment.php
$payPath = $baseDir . '/app/Models/Payment.php';
$payCode = file_get_contents($payPath);
if (!str_contains($payCode, 'function paymentItems()')) {
    $method = "\n    public function paymentItems(): \\Illuminate\\Database\\Eloquent\\Relations\\HasMany\n"
            . "    {\n"
            . "        return \$this->hasMany(OrderPaymentItem::class, 'order_payment_id');\n"
            . "    }\n";
    $payCode = preg_replace('/\}\s*$/', $method . "}\n", $payCode);
    file_put_contents($payPath, $payCode);
    echo " • Payment::paymentItems() [Injetado]" . PHP_EOL;
} else {
    echo " • Payment::paymentItems() [Já Existia]" . PHP_EOL;
}

// Injeta em OrderItem.php
$oiPath = $baseDir . '/app/Models/OrderItem.php';
$oiCode = file_get_contents($oiPath);
if (!str_contains($oiCode, 'function paymentAllocations()')) {
    $method = "\n    public function paymentAllocations(): \\Illuminate\\Database\\Eloquent\\Relations\\HasMany\n"
            . "    {\n"
            . "        return \$this->hasMany(OrderPaymentItem::class, 'order_item_id');\n"
            . "    }\n";
    $oiCode = preg_replace('/\}\s*$/', $method . "}\n", $oiCode);
    file_put_contents($oiPath, $oiCode);
    echo " • OrderItem::paymentAllocations() [Injetado]" . PHP_EOL;
} else {
    echo " • OrderItem::paymentAllocations() [Já Existia]" . PHP_EOL;
}

// Injeta em AccountReceivable.php
$arPath = $baseDir . '/app/Models/AccountReceivable.php';
if (file_exists($arPath)) {
    $arCode = file_get_contents($arPath);
    if (!str_contains($arCode, 'function acquirerTransaction()')) {
        $method = "\n    public function acquirerTransaction(): \\Illuminate\\Database\\Eloquent\\Relations\\HasOne\n"
                . "    {\n"
                . "        return \$this->hasOne(AcquirerTransaction::class, 'account_receivable_id');\n"
                . "    }\n";
        $arCode = preg_replace('/\}\s*$/', $method . "}\n", $arCode);
        file_put_contents($arPath, $arCode);
        echo " • AccountReceivable::acquirerTransaction() [Injetado]" . PHP_EOL;
    } else {
        echo " • AccountReceivable::acquirerTransaction() [Já Existia]" . PHP_EOL;
    }
}

// Injeta em User.php
$usrPath = $baseDir . '/app/Models/User.php';
$usrCode = file_get_contents($usrPath);
if (!str_contains($usrCode, 'function pushSubscriptions()')) {
    $method = "\n    public function pushSubscriptions(): \\Illuminate\\Database\\Eloquent\\Relations\\MorphMany\n"
            . "    {\n"
            . "        return \$this->morphMany(PushSubscription::class, 'subscribable');\n"
            . "    }\n";
    $usrCode = preg_replace('/\}\s*$/', $method . "}\n", $usrCode);
    file_put_contents($usrPath, $usrCode);
    echo " • User::pushSubscriptions() [Injetado]" . PHP_EOL;
} else {
    echo " • User::pushSubscriptions() [Já Existia]" . PHP_EOL;
}

// Injeta em Customer.php
$cstPath = $baseDir . '/app/Models/Customer.php';
$cstCode = file_get_contents($cstPath);
if (!str_contains($cstCode, 'function pushSubscriptions()')) {
    $method = "\n    public function pushSubscriptions(): \\Illuminate\\Database\\Eloquent\\Relations\\MorphMany\n"
            . "    {\n"
            . "        return \$this->morphMany(PushSubscription::class, 'subscribable');\n"
            . "    }\n";
    $cstCode = preg_replace('/\}\s*$/', $method . "}\n", $cstCode);
    file_put_contents($cstPath, $cstCode);
    echo " • Customer::pushSubscriptions() [Injetado]" . PHP_EOL;
} else {
    echo " • Customer::pushSubscriptions() [Já Existia]" . PHP_EOL;
}

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "3. RECARREGANDO AUTOLOADER DO COMPOSER E LIMPEZA DE CACHE" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
echo shell_exec('composer dump-autoload -o 2>&1 | tail -n 2');
echo shell_exec('php artisan optimize:clear 2>&1 | tail -n 2');

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "4. VERIFICAÇÃO AUTOMÁTICA VIA TINKER" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
$checkCommand = "php artisan tinker --execute=\"
echo '1. CashShift -> dailyPayables: ' . (method_exists(\\App\\Models\\CashShift::class, 'dailyPayables') ? 'OK' : 'FAIL') . PHP_EOL;
echo '2. Payment -> paymentItems: ' . (method_exists(\\App\\Models\\Payment::class, 'paymentItems') ? 'OK' : 'FAIL') . PHP_EOL;
echo '3. OrderItem -> paymentAllocations: ' . (method_exists(\\App\\Models\\OrderItem::class, 'paymentAllocations') ? 'OK' : 'FAIL') . PHP_EOL;
echo '4. OrderPaymentItem Model: ' . (class_exists(\\App\\Models\\OrderPaymentItem::class) ? 'OK' : 'FAIL') . PHP_EOL;
echo '5. PushSubscription Model: ' . (class_exists(\\App\\Models\\PushSubscription::class) ? 'OK' : 'FAIL') . PHP_EOL;
echo '6. ModifierItem Model: ' . (class_exists(\\App\\Models\\ModifierItem::class) ? 'OK' : 'FAIL') . PHP_EOL;
echo '7. ProductModifierGroup Model: ' . (class_exists(\\App\\Models\\ProductModifierGroup::class) ? 'OK' : 'FAIL') . PHP_EOL;
echo '8. AccountReceivable -> acquirerTransaction: ' . (method_exists(\\App\\Models\\AccountReceivable::class, 'acquirerTransaction') ? 'OK' : 'FAIL') . PHP_EOL;
\"";
echo shell_exec($checkCommand);
echo "================================================================================" . PHP_EOL;

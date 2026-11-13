<?php

namespace App\Models;

use App\Observers\CustomerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

#[ObservedBy([CustomerObserver::class])]
class Customer extends Model
{
    use Notifiable, HasPushSubscriptions;
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $appends = [
        'is_employee',
        'is_convenio',
    ];

    public function getIsEmployeeAttribute(): bool
    {
        return !empty($this->employee_id);
    }

    public function getIsConvenioAttribute(): bool
    {
        return !empty($this->supplier_id);
    }

    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => blank($value) ? null : preg_replace('/[^0-9]/', '', $value),
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => blank($value) ? null : preg_replace('/[^0-9]/', '', $value),
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    // Convênio: Empresa / Fornecedor conveniado ao qual o cliente pertence
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // RH: Cadastro de funcionário correspondente ao cliente
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }

    protected $casts = [
        'usual_payers' => 'array',
    ];

    /**
     * Registra ou incrementa um pagador usual (terceiro/familiar) vinculado a este cliente.
     */
    public function recordUsualPayer(?string $payerName, ?string $payerDocument = null): void
    {
        $cleanName = trim((string) $payerName);
        $cleanDoc  = preg_replace('/[^0-9]/', '', (string) $payerDocument);

        if ($cleanName === '' && $cleanDoc === '') {
            return;
        }

        // Ignora se o pagador já é exatamente o próprio nome do cliente
        if ($cleanName !== '' && Str::ascii(mb_strtolower($cleanName)) === Str::ascii(mb_strtolower(trim((string) $this->name)))) {
            return;
        }

        $payers = is_array($this->usual_payers) ? $this->usual_payers : [];
        $updated = false;

        foreach ($payers as &$item) {
            $sameDoc  = ($cleanDoc !== '' && ($item['document'] ?? '') === $cleanDoc);
            $sameName = ($cleanName !== '' && \Illuminate\Support\Str::ascii(mb_strtolower($item['name'] ?? '')) === \Illuminate\Support\Str::ascii(mb_strtolower($cleanName)));

            if ($sameDoc || $sameName) {
                $item['name']         = $cleanName ?: ($item['name'] ?? '');
                $item['document']     = $cleanDoc ?: ($item['document'] ?? null);
                $item['count']        = (int) ($item['count'] ?? 1) + 1;
                $item['last_paid_at'] = now()->toDateString();
                $updated = true;
                break;
            }
        }
        unset($item);

        if (!$updated) {
            $payers[] = [
                'name'         => $cleanName,
                'document'     => $cleanDoc ?: null,
                'count'        => 1,
                'last_paid_at' => now()->toDateString(),
            ];
        }

        $this->updateQuietly(['usual_payers' => $payers]);
    }

    /**
     * Verifica se um pagador (CPF/CNPJ ou nome) consta na lista de pagadores usuais deste cliente.
     */
    public function matchesUsualPayer(?string $payerName, ?string $payerDocument = null): bool
    {
        $payers = is_array($this->usual_payers) ? $this->usual_payers : [];
        if (empty($payers)) {
            return false;
        }

        $cleanName = \Illuminate\Support\Str::ascii(mb_strtolower(trim((string) $payerName)));
        $cleanDoc  = preg_replace('/[^0-9]/', '', (string) $payerDocument);

        foreach ($payers as $item) {
            if ($cleanDoc !== '' && !empty($item['document']) && $item['document'] === $cleanDoc) {
                return true;
            }
            if ($cleanName !== '' && !empty($item['name']) && \Illuminate\Support\Str::ascii(mb_strtolower(trim($item['name']))) === $cleanName) {
                return true;
            }
        }

        return false;
    }
}

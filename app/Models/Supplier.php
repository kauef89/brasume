<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountPayable> $accountPayables
 * @property-read int|null $account_payables_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Supplier> $branches
 * @property-read int|null $branches_count
 * @property-read Supplier|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read array $family_ids
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function parent()
    {
        return $this->belongsTo(Supplier::class, 'parent_id');
    }

    public function branches()
    {
        return $this->hasMany(Supplier::class, 'parent_id');
    }

    public function accountPayables()
    {
        return $this->hasMany(\App\Models\AccountPayable::class, 'supplier_id');
    }

    /**
     * 🚀 ACESSOR MÁGICO: Retorna os IDs da Matriz e de todas as Filiais.
     */
    public function getFamilyIdsAttribute(): array
    {
        // 1. Descobre o ID do "Pai" (Se não tiver parent_id, ele próprio é a matriz)
        $matrixId = $this->parent_id ?? $this->id;

        // 2. Busca todos os "Filhos" (Filiais) que apontam para esta Matriz
        $branchIds = self::where('parent_id', $matrixId)->pluck('id')->toArray();

        // 3. Junta a Matriz com as Filiais num único Array e limpa duplicações
        return array_unique(array_merge([$matrixId], $branchIds));
    }

    // 🚀 A TRAVA ABSOLUTA DO BANCO DE DADOS
        protected static function booted()
        {
            static::saving(function ($supplier) {
                if (!empty($supplier->document)) {
                    $exists = self::where('document', $supplier->document)
                        ->where('id', '!=', $supplier->id)
                        ->exists();

                    if ($exists) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'document' => "O CNPJ/CPF '{$supplier->document}' já está em uso por outro fornecedor na base de dados."
                        ]);
                    }
                }
            });
        }
}

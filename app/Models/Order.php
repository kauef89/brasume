<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model {
    use SoftDeletes;
    protected $guarded = []; // Libera as portas!

    public function customer() { return $this->belongsTo(Customer::class); }
    public function address() { return $this->belongsTo(Address::class); }
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }

    // 🚀 A RELAÇÃO QUE FALTAVA!
    public function employee() {
        // Como o sistema grava o auth()->id() no 'employee_id',
        // ele pertence à tabela de Usuários (Acessos/Operadores)
        return $this->belongsTo(User::class, 'employee_id');
    }
}

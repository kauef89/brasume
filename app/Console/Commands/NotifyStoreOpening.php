<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\RestaurantOps\StoreAvailabilityService;
use App\Notifications\StoreOpenedNotification;

class NotifyStoreOpening extends Command
{
    protected $signature = 'store:notify-opening';
    protected $description = 'Notifica clientes aguardando a abertura da loja e limpa a fila.';

    public function handle(StoreAvailabilityService $availabilityService)
    {
        // 🚀 OTIMIZAÇÃO EXTREMA: A primeira coisa a fazer é uma query ultra-leve.
        // O banco responde isso em menos de 1 milissegundo.
        $hasWaitingUsers = User::where('wants_opening_notification', true)->exists();

        // Se ninguém apertou o botão "Me Avise", o comando morre aqui!
        // Zero processamento, zero cálculo de matriz de horários.
        if (!$hasWaitingUsers) {
            return;
        }

        // Se passou daqui, é porque TEM clientes aguardando na fila desta loja.
        // AGORA SIM, fazemos o cálculo pesado para saber se a porta já abriu.
        $status = $availabilityService->getStoreStatus();

        if ($status['is_open'] || $status['is_pre_order']) {

            // Puxa a galera que estava esperando
            $waitingUsers = User::where('wants_opening_notification', true)->get();

            // Dispara o push em massa
            \Illuminate\Support\Facades\Notification::send($waitingUsers, new StoreOpenedNotification());

            // 🚀 LIMPEZA MESTRA: Zera a flag direto no banco de forma atômica para não mandar spam depois
            User::whereIn('id', $waitingUsers->pluck('id'))->update(['wants_opening_notification' => false]);

            $this->info("Notificados {$waitingUsers->count()} clientes sobre a abertura.");
        }
    }
}

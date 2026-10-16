<?php

namespace App\Services\RestaurantOps;

use App\Models\CompanySetting;
use App\Models\CashShift;
use Carbon\Carbon;

class StoreAvailabilityService
{
    public function getStoreStatus(): array
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        $now = Carbon::now();

        $status = [
            'is_open'            => false,
            'is_pre_order'       => false,
            'is_past_closing'    => false,
            'active_channels'    => [],
            'next_open_time'     => null,
            'current_close_time' => null,
            'last_closed_at'     => null,
            'reason'             => null
        ];

        if (!$settings) return $status;

        // ========================================================
        // 1. TRAVAS EMERGENCIAIS E OPERACIONAIS (Overrides Absolutos)
        // ========================================================

        // A) Fechamento por tempo indeterminado
        if (!empty($settings->is_closed_indefinitely)) {
            $status['reason'] = 'closed_indefinitely';
            return $status;
        }

        // B) Fechamento Operacional Manual (Corte de pedidos no Turno Ativo)
        $activeShift = CashShift::where('status', 'open')->latest('opened_at')->first();

        if ($activeShift && isset($activeShift->accepting_orders) && $activeShift->accepting_orders === false) {
            $status['is_open'] = false;
            $status['reason'] = 'manual_override_closed';

            $nextOpenings = $this->calculateNextOpenings($settings, $now);
            if (!empty($nextOpenings)) {
                $status['next_open_time'] = $nextOpenings[0]['start']->toIso8601String();
            }

            return $status;
        }

        // ========================================================
        // 2. AVALIAÇÃO DE HORÁRIOS PROGRAMADOS E CAIXA FÍSICO
        // ========================================================

        $isShiftOpen = CashShift::where('status', 'open')->exists();

        // Loja 24 Horas
        if (!empty($settings->is_24_hours)) {
            $status['is_open'] = true;
            $status['active_channels'] = ['delivery', 'counter', 'table'];
            return $status;
        }

        // Matriz semanal de horários
        $hours = is_string($settings->business_hours)
            ? json_decode($settings->business_hours, true)
            : ($settings->business_hours ?? []);

        $activeChannels = [];
        $nextOpenings = [];
        $pastClosings = [];
        $currentClosings = [];

        if (!empty($hours)) {
            foreach ($hours as $block) {
                $dayOfWeek = (int) $block['day_of_week'];
                $openStr = substr($block['open_time'], 0, 5);
                $closeStr = substr($block['close_time'], 0, 5);
                $channels = $block['active_channels'] ?? [];

                for ($i = -1; $i <= 7; $i++) {
                    $checkDate = $now->copy()->addDays($i);

                    if ($checkDate->dayOfWeek === $dayOfWeek) {
                        $start = $checkDate->copy()->setTimeFromTimeString($openStr);
                        $end = $checkDate->copy()->setTimeFromTimeString($closeStr);

                        // Vira a madrugada
                        if ($end->lessThan($start)) {
                            $end->addDay();
                        }

                        if ($now->between($start, $end)) {
                            $activeChannels = array_merge($activeChannels, $channels);
                            $currentClosings[] = $end->copy();
                        } elseif ($start->greaterThan($now)) {
                            $nextOpenings[] = [
                                'start'    => $start->copy(),
                                'end'      => $end->copy(),
                                'channels' => $channels
                            ];
                        }

                        if ($end->lessThanOrEqualTo($now)) {
                            $pastClosings[] = $end->copy();
                        }
                    }
                }
            }

            if (!empty($pastClosings)) {
                usort($pastClosings, fn($a, $b) => $b <=> $a);
                $status['last_closed_at'] = $pastClosings[0]->toIso8601String();
            }

            if (!empty($nextOpenings)) {
                usort($nextOpenings, fn($a, $b) => $a['start'] <=> $b['start']);
                $status['next_open_time'] = $nextOpenings[0]['start']->toIso8601String();
            }
        }

        $activeChannels = array_values(array_unique($activeChannels));
        $isScheduledOpen = !empty($activeChannels);

        // ========================================================
        // 3. INTEGRAÇÃO BIDIRECIONAL: CAIXA x HORÁRIOS PROGRAMADOS
        // ========================================================

        // CENÁRIO A: Caixa está ABERTO -> A loja opera imediatamente!
        if ($isShiftOpen) {
            $status['is_open'] = true;
            $status['active_channels'] = $isScheduledOpen ? $activeChannels : ['delivery', 'counter', 'table'];

            // 1. Horário de fechamento da janela em andamento
            if (!empty($currentClosings)) {
                usort($currentClosings, fn($a, $b) => $a <=> $b);
                $status['current_close_time'] = $currentClosings[0]->toIso8601String();
            }
            // 2. 🚀 REGRA 1: Caixa abriu mais cedo que o horário da escala
            elseif (!empty($nextOpenings)) {
                $nextBlock = $nextOpenings[0];
                if ($now->diffInHours($nextBlock['start'], false) <= 14) {
                    $status['current_close_time'] = $nextBlock['end']->toIso8601String();
                    if (!empty($nextBlock['channels'])) {
                        $status['active_channels'] = $nextBlock['channels'];
                    }
                }
            }

            // 3. 🚀 REGRA 2: Passou do horário regular de fechar, mas o turno continua aberto
            if (empty($status['current_close_time']) && !empty($pastClosings)) {
                $mostRecentPastClosing = $pastClosings[0];
                if ($now->diffInHours($mostRecentPastClosing) <= 12) {
                    $status['current_close_time'] = $mostRecentPastClosing->toIso8601String();
                    $status['is_past_closing'] = true;
                }
            }

            // Validação de extrapolação de horário
            if (!empty($status['current_close_time'])) {
                $closeDate = Carbon::parse($status['current_close_time']);
                if ($now->greaterThanOrEqualTo($closeDate)) {
                    $status['is_past_closing'] = true;
                }
            }

            return $status;
        }

        // CENÁRIO B: Caixa está FECHADO
        if ($isScheduledOpen) {
            if ($settings->enforce_cashier_open) {
                $status['is_open'] = false;
                $status['reason'] = 'cashier_closed';

                if ($settings->allow_pre_orders) {
                    $status['is_pre_order'] = true;
                    $status['active_channels'] = $activeChannels;
                } else {
                    $status['is_pre_order'] = false;
                    $status['active_channels'] = [];
                }

                if (!empty($currentClosings)) {
                    usort($currentClosings, fn($a, $b) => $a <=> $b);
                    $status['current_close_time'] = $currentClosings[0]->toIso8601String();
                }
            } else {
                $status['is_open'] = true;
                $status['active_channels'] = $activeChannels;

                if (!empty($currentClosings)) {
                    usort($currentClosings, fn($a, $b) => $a <=> $b);
                    $status['current_close_time'] = $currentClosings[0]->toIso8601String();
                }
            }
        } else {
            $status['is_open'] = false;
            $status['reason'] = 'outside_hours';

            if ($settings->allow_pre_orders && !empty($nextOpenings) && $settings->pre_order_buffer_minutes > 0) {
                $nextBlock = $nextOpenings[0];
                $diffMinutes = $now->diffInMinutes($nextBlock['start'], false);

                if ($diffMinutes > 0 && $diffMinutes <= $settings->pre_order_buffer_minutes) {
                    $status['is_pre_order'] = true;
                    $status['active_channels'] = $nextBlock['channels'];
                }
            }
        }

        return $status;
    }

    protected function calculateNextOpenings($settings, $now): array
    {
        $hours = is_string($settings->business_hours) ? json_decode($settings->business_hours, true) : ($settings->business_hours ?? []);
        $nextOpenings = [];

        foreach ($hours as $block) {
            $dayOfWeek = (int) $block['day_of_week'];
            $openStr = substr($block['open_time'], 0, 5);
            $closeStr = substr($block['close_time'], 0, 5);
            $channels = $block['active_channels'] ?? [];

            for ($i = -1; $i <= 7; $i++) {
                $checkDate = $now->copy()->addDays($i);
                if ($checkDate->dayOfWeek === $dayOfWeek) {
                    $start = $checkDate->copy()->setTimeFromTimeString($openStr);
                    $end = $checkDate->copy()->setTimeFromTimeString($closeStr);
                    if ($end->lessThan($start)) {
                        $end->addDay();
                    }
                    if ($start->greaterThan($now)) {
                        $nextOpenings[] = [
                            'start'    => $start->copy(),
                            'end'      => $end->copy(),
                            'channels' => $channels
                        ];
                    }
                }
            }
        }

        usort($nextOpenings, fn($a, $b) => $a['start'] <=> $b['start']);
        return $nextOpenings;
    }
}

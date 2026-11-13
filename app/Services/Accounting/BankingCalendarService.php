<?php

namespace App\Services\Accounting;

use Illuminate\Support\Carbon;

class BankingCalendarService
{
    /**
     * Verifica se uma data é dia útil bancário no Brasil (exclui sábados, domingos e feriados).
     */
    public function isBankingBusinessDay(Carbon $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return !in_array($date->toDateString(), $this->getHolidaysForYear((int) $date->year), true);
    }

    /**
     * Retorna o próximo dia útil bancário estritamente posterior à data informada.
     */
    public function nextBankingBusinessDay(Carbon $date): Carbon
    {
        $cursor = $date->copy()->startOfDay()->addDay();
        $guard = 0;

        while (!$this->isBankingBusinessDay($cursor) && $guard < 15) {
            $cursor->addDay();
            $guard++;
        }

        return $cursor;
    }

    /**
     * Calcula a janela exata de compensação bancária [minDate, maxDate] para um turno ou pedido.
     * - Dias úteis (Seg-Qui): D0 até D+1 (cobre turno noturno que vira a madrugada).
     * - Sex à noite / Sáb / Dom / Feriado: estende automaticamente até o próximo dia útil bancário.
     */
    public function getSettlementWindow(Carbon $startAt, ?Carbon $endAt = null): array
    {
        $startDay = $startAt->copy()->startOfDay();
        $effectiveEnd = ($endAt && $endAt->gt($startAt))
            ? $endAt->copy()->startOfDay()
            : $startDay->copy();

        // Se o fechamento já ocorreu no dia seguinte (ex: 01:30 da madrugada) e esse dia é útil,
        // ele próprio já é o D+1; se for sábado/domingo/feriado, avança até o próximo dia útil.
        if ($effectiveEnd->gt($startDay)) {
            $maxDay = $this->isBankingBusinessDay($effectiveEnd)
                ? $effectiveEnd
                : $this->nextBankingBusinessDay($effectiveEnd);
        } else {
            $maxDay = $this->nextBankingBusinessDay($startDay);
        }

        return [
            'min_date' => $startDay->toDateString(),
            'max_date' => $maxDay->toDateString(),
        ];
    }

    /**
     * Lista feriados bancários nacionais (fixos + móveis pela Páscoa) e regionais (SC).
     */
    public function getHolidaysForYear(int $year): array
    {
        $fixed = [
            sprintf('%04d-01-01', $year), // Confraternização Universal
            sprintf('%04d-04-21', $year), // Tiradentes
            sprintf('%04d-05-01', $year), // Dia do Trabalho
            sprintf('%04d-09-07', $year), // Independência do Brasil
            sprintf('%04d-10-12', $year), // Nossa Senhora Aparecida
            sprintf('%04d-11-02', $year), // Finados
            sprintf('%04d-11-15', $year), // Proclamação da República
            sprintf('%04d-11-20', $year), // Dia da Consciência Negra
            sprintf('%04d-12-25', $year), // Natal
        ];

        $easter = Carbon::createFromTimestamp(easter_date($year))->startOfDay();
        $movable = [
            $easter->copy()->subDays(48)->toDateString(), // Segunda de Carnaval (bancário)
            $easter->copy()->subDays(47)->toDateString(), // Terça de Carnaval (bancário)
            $easter->copy()->subDays(2)->toDateString(),  // Sexta-Feira Santa
            $easter->copy()->addDays(60)->toDateString(), // Corpus Christi
        ];

        return array_unique(array_merge($fixed, $movable));
    }
}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Monofásicos</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #111; }
        table.layout { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.layout td { border: none; padding: 2px 0; vertical-align: middle; }

        .header-line-2 { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; margin-bottom: 15px; width: 100%; }
        .header-line-2 td { font-size: 11px; }

        table.data { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        table.data th { background-color: #f1f1f1; text-transform: uppercase; font-size: 9px; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
    </style>
</head>
<body>

    <!-- LINHA UM: Empresa -->
    <table class="layout">
        <tr>
            @if($logoBase64)
            <td style="width: 80px; padding-right: 10px;">
                <img src="{{ $logoBase64 }}" style="max-height: 40px; max-width: 80px; display: block;">
            </td>
            @endif
            <td style="font-size: 11px;">
                <strong>{{ $settings->company_name ?? 'Razão Social Não Configurada' }}</strong> &nbsp;|&nbsp;
                CNPJ: {{ $settings->cnpj ?? 'N/A' }} &nbsp;|&nbsp;
                IE: {{ $settings->ie ?? 'N/A' }} <br>
                {{ $settings->street ?? '' }}, {{ $settings->number ?? '' }} - {{ $settings->neighborhood ?? '' }}, {{ $settings->city ?? '' }}/{{ $settings->state ?? '' }}
            </td>
        </tr>
    </table>

    <!-- LINHA DOIS: Info do Relatório -->
    <table class="layout header-line-2">
        <tr>
            <td style="width: 40%;"><strong>Relatório de Faturamento: Monofásicos (PIS/COFINS)</strong></td>
            <td style="width: 20%;">Período: {{ $startDate }} a {{ $endDate }}</td>
            <td style="width: 20%;">Emissão: {{ now()->format('d/m/Y H:i') }}</td>
            <td style="width: 20%; text-align: right;">Total Faturado: <strong>R$ {{ number_format($totalFaturado, 2, ',', '.') }}</strong></td>
        </tr>
    </table>

    <!-- DADOS -->
    <table class="data">
        <thead>
            <tr>
                <th style="width: 60%;">Produto</th>
                <th class="text-center" style="width: 20%;">Quantidade Faturada</th>
                <th class="text-right" style="width: 20%;">Valor Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $productName => $data)
                <tr>
                    <td><strong>{{ $productName }}</strong></td>
                    <td class="text-center">{{ number_format($data['total_qty'], 2, ',', '.') }}</td>
                    <td class="text-right fw-bold">R$ {{ number_format($data['total_value'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="padding: 20px;">Nenhum produto monofásico foi faturado (autorizado) no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>

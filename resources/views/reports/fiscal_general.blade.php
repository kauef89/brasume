<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Fiscal - Sintético</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 20px; color: #111; }
        table.layout { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.layout td { border: none; padding: 2px 0; vertical-align: middle; }

        .header-line-2 { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; margin-bottom: 15px; width: 100%; }
        .header-line-2 td { font-size: 11px; }

        table.data { width: 100%; border-collapse: collapse; font-size: 9px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 5px; text-align: left; }
        table.data th { background-color: #f1f1f1; text-transform: uppercase; font-size: 8px; }

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
            <td style="width: 35%;"><strong>Relatório Analítico de Emissões Fiscais (NFC-e)</strong></td>
            <td style="width: 25%;">Período: {{ $startDate }} a {{ $endDate }}</td>
            <td style="width: 20%;">Emissão: {{ now()->format('d/m/Y H:i') }}</td>
            <td style="width: 20%; text-align: right;">Total do Período: <strong>R$ {{ number_format($totalFaturado, 2, ',', '.') }}</strong></td>
        </tr>
    </table>

    <!-- DADOS -->
    <table class="data">
        <thead>
            <tr>
                <th style="width: 10%;">Data</th>
                <th class="text-center" style="width: 5%;">Mod</th>
                <th class="text-center" style="width: 5%;">Série</th>
                <th class="text-center" style="width: 8%;">Número</th>
                <th style="width: 30%;">Chave de Acesso</th>
                <th style="width: 12%;">Protocolo</th>
                <th style="width: 10%;">CPF/CNPJ Dest.</th>
                <th style="width: 10%;">Destinatário</th>
                <th class="text-right" style="width: 10%;">Valor Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($documents as $doc)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($doc->issue_date)->format('d/m/Y H:i') }}</td>
                    <td class="text-center">{{ $doc->model }}</td>
                    <td class="text-center">{{ $doc->series }}</td>
                    <td class="text-center fw-bold">{{ $doc->number }}</td>
                    <td style="font-family: monospace; font-size: 8px;">{{ $doc->access_key }}</td>
                    <td>{{ $doc->protocol }}</td>
                    <td>{{ $doc->order?->customer?->cpf ? substr($doc->order->customer->cpf, 0, 3) . '.***.***-**' : 'S/N' }}</td>
                    <td>{{ substr($doc->order?->customer_name ?? 'Consumidor Final', 0, 15) }}</td>
                    <td class="text-right fw-bold">R$ {{ number_format($doc->total_value, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px;">Nenhum documento fiscal autorizado no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>

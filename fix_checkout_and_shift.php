<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "1. A aplicar correção em OrderPaymentService.php..." . PHP_EOL;
$opsPath = app_path('Services/Orders/OrderPaymentService.php');
$opsContent = file_get_contents($opsPath);
$oldOps = '$shouldClose = ($order->status === \'dispatched\' && $order->payment_status === \'paid\')
            || ($order->type === \'counter\' && in_array($order->status, [\'ready\', \'dispatched\']) && $order->payment_status === \'paid\')
            || ($order->type === \'table\' && $order->payment_status === \'paid\');';

$newOps = '$isPos = in_array($order->source, [\App\Models\Order::SOURCE_POS_APP, \App\Models\Order::SOURCE_CASHIER], true);
        $shouldClose = ($order->status === \'dispatched\' && $order->payment_status === \'paid\')
            || ($order->type === \'counter\' && $order->payment_status === \'paid\' && ($isPos || in_array($order->status, [\'ready\', \'dispatched\'])))
            || ($order->type === \'table\' && $order->payment_status === \'paid\');';

if (str_contains($opsContent, '$order->type === \'counter\' && in_array($order->status, [\'ready\', \'dispatched\'])')) {
    $opsContent = str_replace($oldOps, $newOps, $opsContent);
    file_put_contents($opsPath, $opsContent);
    echo "   -> OrderPaymentService.php atualizado com sucesso." . PHP_EOL;
} else {
    echo "   -> Padrão já atualizado ou não localizado." . PHP_EOL;
}

echo "2. A aplicar correção em Checkout.vue..." . PHP_EOL;
$vuePath = resource_path('js/Pages/Pos/Checkout.vue');
$vueContent = file_get_contents($vuePath);
$oldWatcher = 'watch(
    () => [liveOrder.value?.payment_status, liveOrder.value?.status, liveOrder.value?.total, liveOrder.value?.paid_amount],
    ([payStatus, orderStatus, totalStr, paidStr]) => {
        const orderTotal = Number(totalStr || 0);
        const orderPaid = Number(paidStr || 0);

        if (orderStatus === \'closed\' || orderStatus === \'cancelled\' || (payStatus === \'paid\' && orderPaid >= orderTotal - 0.01)) {
            isPostPaymentPhase.value = true;
        } else {
            isPostPaymentPhase.value = false;
        }
    },
    { immediate: true }
);';

$newWatcher = 'watch(
    () => [liveOrder.value?.payment_status, liveOrder.value?.status, liveOrder.value?.total, liveOrder.value?.total_paid, liveOrder.value?.payments],
    ([payStatus, orderStatus, totalStr, totalPaidVal, paymentsList]) => {
        const orderTotal = Number(totalStr || 0);
        const calcPaid = totalPaidVal !== undefined && totalPaidVal !== null
            ? Number(totalPaidVal)
            : (Array.isArray(paymentsList) ? paymentsList.filter(p => p.status !== \'cancelled\').reduce((acc, p) => acc + Number(p.amount || 0), 0) : 0);

        const isFullyPaid = payStatus === \'paid\' || (calcPaid >= orderTotal - 0.05 && orderTotal > 0);
        const isClosedOrFinal = [\'closed\', \'completed\', \'delivered\'].includes(orderStatus);

        if (isClosedOrFinal || isFullyPaid) {
            isPostPaymentPhase.value = true;
        } else {
            isPostPaymentPhase.value = false;
        }
    },
    { immediate: true }
);';

if (str_contains($vueContent, 'liveOrder.value?.paid_amount')) {
    $vueContent = str_replace($oldWatcher, $newWatcher, $vueContent);
    file_put_contents($vuePath, $vueContent);
    echo "   -> Checkout.vue atualizado com sucesso." . PHP_EOL;
} else {
    echo "   -> Padrão já corrigido em Checkout.vue." . PHP_EOL;
}

echo "3. A recompilar frontend..." . PHP_EOL;
echo shell_exec('npm run build 2>&1 | tail -n 5');

echo "Concluído com sucesso!" . PHP_EOL;

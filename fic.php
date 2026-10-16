<?php

function replaceInFile($filePath, $search, $replace) {
    if (!file_exists($filePath)) return;
    $content = file_get_contents($filePath);
    if (str_contains($content, $search)) {
        $content = str_replace($search, $replace, $content);
        file_put_contents($filePath, $content);
        echo "✅ Corrigido: {$filePath}\n";
    }
}

// 1. BLINDAR useCustomerSync.js
$syncFile = 'resources/js/Pages/Customer/Composables/useCustomerSync.js';
if (file_exists($syncFile)) {
    $syncContent = file_get_contents($syncFile);

    // Substitui reload forçado de settings por reload reativo do Inertia
    $oldSettingsListener = "listenChannel('system', '.settings.updated', () => window.location.reload(true));";
    $newSettingsListener = "listenChannel('system', '.settings.updated', () => {
                router.reload({ only: ['storeStatus', 'companySettings'], preserveState: true, preserveScroll: true });
            });";

    if (str_contains($syncContent, $oldSettingsListener)) {
        $syncContent = str_replace($oldSettingsListener, $newSettingsListener, $syncContent);
        echo "✅ useCustomerSync.js: settings.updated protegido contra F5 físico!\n";
    }

    // Se houver .then(() => window.location.reload())
    $oldPromiseReload = ".then(() => window.location.reload())";
    $newPromiseReload = ".then(() => { router.reload({ preserveState: true, preserveScroll: true }); })";
    if (str_contains($syncContent, $oldPromiseReload)) {
        $syncContent = str_replace($oldPromiseReload, $newPromiseReload, $syncContent);
        echo "✅ useCustomerSync.js: promise reload suavizado!\n";
    }

    file_put_contents($syncFile, $syncContent);
}

// 2. BLINDAR CustomerApp.vue
replaceInFile(
    'resources/js/Pages/Customer/CustomerApp.vue',
    "router.reload({ only: ['myActiveOrders', 'companySettings'] });",
    "router.reload({ only: ['myActiveOrders', 'companySettings'], preserveState: true, preserveScroll: true });"
);

replaceInFile(
    'resources/js/Pages/Customer/CustomerApp.vue',
    "router.post(route('customer.profile.update'), formData, {\n        preserveScroll: true,\n        onSuccess:",
    "router.post(route('customer.profile.update'), formData, {\n        preserveScroll: true,\n        preserveState: true,\n        onSuccess:"
);

// 3. BLINDAR CustomerOpenTabs.vue
replaceInFile(
    'resources/js/Pages/Customer/CustomerOpenTabs.vue',
    "router.reload({\n        only: ['myActiveOrders'],\n    });",
    "router.reload({\n        only: ['myActiveOrders'],\n        preserveState: true,\n        preserveScroll: true,\n    });"
);

// 4. BLINDAR UpgradeAccountModal.vue
$upgradeFile = 'resources/js/Pages/Customer/Profile/UpgradeAccountModal.vue';
if (file_exists($upgradeFile)) {
    $upgradeContent = file_get_contents($upgradeFile);

    // Substitui o reload físico por reidratação do cliente
    if (str_contains($upgradeContent, 'window.location.reload();')) {
        $upgradeContent = str_replace(
            'window.location.reload();',
            "router.reload({ only: ['customer', 'auth'], preserveState: true, preserveScroll: true });",
            $upgradeContent
        );
    }

    // Adiciona preserveState no post de upgrade se não tiver
    $upgradeContent = preg_replace(
        '/router\.post\(route\(\'customer\.auth\.upgrade\'\),\s*\{([^}]+)\},\s*\{/s',
        "router.post(route('customer.auth.upgrade'), { $1 }, {\n        preserveState: true,\n        preserveScroll: true,",
        $upgradeContent
    );

    file_put_contents($upgradeFile, $upgradeContent);
    echo "✅ UpgradeAccountModal.vue blindado com sucesso!\n";
}

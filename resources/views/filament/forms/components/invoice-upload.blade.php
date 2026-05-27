<div x-data="{ fileName: '', isUploading: false }">
    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2" for="nfe_upload">
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Anexe o XML da Nota Fiscal</span>
    </label>
    
    <div class="flex items-center gap-x-4 mt-2">
        <x-filament::button tag="label" for="nfe_upload" color="primary" icon="heroicon-m-arrow-up-tray">
            Selecionar Autorizacao.xml
        </x-filament::button>

        <span x-show="isUploading" class="text-sm text-primary-600 font-medium animate-pulse">Lendo XML...</span>
        <span x-show="!isUploading" x-text="fileName || 'Nenhum arquivo selecionado'" class="text-sm text-gray-500 dark:text-gray-400"></span>
    </div>

    <input 
        type="file" 
        id="nfe_upload"
        accept=".xml"
        class="hidden"
        x-on:change="
            let file = $event.target.files[0];
            if(file) {
                fileName = file.name;
                isUploading = true;
                let reader = new FileReader();
                reader.onload = (e) => {
                    // Manda para o PHP e tira o 'Lendo XML...' quando o PHP responder
                    $wire.set('xmlContent', e.target.result).then(() => {
                        isUploading = false;
                    });
                };
                reader.readAsDataURL(file);
            }
        "
    >
</div>
<div x-data="{ fileName: '' }">
    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Arquivo do Certificado (.pfx)</span>
    </label>
    
    <input type="file" accept=".pfx,.p12"
        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
        x-on:change="
            const file = $event.target.files[0];
            if (file) {
                fileName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => {
                    $wire.set('data.certificate_path', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        "
    >
    
    <p class="mt-2 text-xs text-primary-600 font-semibold" 
       x-text="fileName ? 'Novo arquivo pronto para envio: ' + fileName : '{{ $getState() ? 'Um certificado já está salvo no cofre.' : 'Nenhum certificado selecionado.' }}'">
    </p>
</div>
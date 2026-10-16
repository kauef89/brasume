@props([
    'areHtmlErrorMessagesAllowed' => null,
    'errorMessage' => null,
    'errorMessages' => null,
    'field' => null,
    'hasErrors' => true,
    'hasInlineLabel' => null,
    'hasNestedRecursiveValidationRules' => null,
    'id' => null,
    'inlineLabelVerticalAlignment' => \Filament\Support\Enums\VerticalAlignment::Start,
    'isDisabled' => null,
    'label' => null,
    'labelPrefix' => null,
    'labelSrOnly' => null,
    'labelSuffix' => null,
    'labelTag' => 'label',
    'required' => null,
    'shouldShowAllValidationMessages' => null,
    'statePath' => null,
])

@php
    use Filament\Support\Enums\VerticalAlignment;
    use Illuminate\Support\Arr;
    use Illuminate\Support\MessageBag;
    use Illuminate\View\ComponentAttributeBag;

    // 🛡️ BLINDAGEM ABSOLUTA: O isset() impede qualquer erro do compilador restrito do Laravel
    $hasErrors = isset($hasErrors) ? $hasErrors : true;
    $errorMessage = isset($errorMessage) ? $errorMessage : null;
    $errorMessages = isset($errorMessages) ? $errorMessages : null;
    $field = isset($field) ? $field : null;
    $hasInlineLabel = isset($hasInlineLabel) ? $hasInlineLabel : null;
    $hasNestedRecursiveValidationRules = isset($hasNestedRecursiveValidationRules) ? $hasNestedRecursiveValidationRules : null;
    $id = isset($id) ? $id : null;
    $inlineLabelVerticalAlignment = isset($inlineLabelVerticalAlignment) ? $inlineLabelVerticalAlignment : VerticalAlignment::Start;
    $isDisabled = isset($isDisabled) ? $isDisabled : null;
    $label = isset($label) ? $label : null;
    $labelPrefix = isset($labelPrefix) ? $labelPrefix : null;
    $labelSrOnly = isset($labelSrOnly) ? $labelSrOnly : null;
    $labelSuffix = isset($labelSuffix) ? $labelSuffix : null;
    $labelTag = isset($labelTag) ? $labelTag : 'label';
    $required = isset($required) ? $required : null;
    $shouldShowAllValidationMessages = isset($shouldShowAllValidationMessages) ? $shouldShowAllValidationMessages : null;
    $statePath = isset($statePath) ? $statePath : null;
    $areHtmlErrorMessagesAllowed = isset($areHtmlErrorMessagesAllowed) ? $areHtmlErrorMessagesAllowed : null;

    // Fallbacks para objetos injetados nativamente pelo Laravel
    $errors = isset($errors) ? $errors : new MessageBag();
    $attributes = isset($attributes) ? $attributes : new ComponentAttributeBag();

    if ($field) {
        $hasInlineLabel = isset($hasInlineLabel) ? $hasInlineLabel : $field->hasInlineLabel();
        $hasNestedRecursiveValidationRules = isset($hasNestedRecursiveValidationRules) ? $hasNestedRecursiveValidationRules : $field instanceof \Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules;
        $id = isset($id) ? $id : $field->getId();
        $isDisabled = isset($isDisabled) ? $isDisabled : $field->isDisabled();
        $label = isset($label) ? $label : $field->getLabel();
        $labelSrOnly = isset($labelSrOnly) ? $labelSrOnly : $field->isLabelHidden();
        $required = isset($required) ? $required : $field->isMarkedAsRequired();
        $statePath = isset($statePath) ? $statePath : $field->getStatePath();
        $areHtmlErrorMessagesAllowed = isset($areHtmlErrorMessagesAllowed) ? $areHtmlErrorMessagesAllowed : $field->areHtmlValidationMessagesAllowed();
        $shouldShowAllValidationMessages = isset($shouldShowAllValidationMessages) ? $shouldShowAllValidationMessages : $field->shouldShowAllValidationMessages();
    }

    $aboveLabelSchema = $field?->getChildSchema($field::ABOVE_LABEL_SCHEMA_KEY)?->toHtmlString();
    $belowLabelSchema = $field?->getChildSchema($field::BELOW_LABEL_SCHEMA_KEY)?->toHtmlString();
    $beforeLabelSchema = $field?->getChildSchema($field::BEFORE_LABEL_SCHEMA_KEY)?->toHtmlString();
    $afterLabelSchema = $field?->getChildSchema($field::AFTER_LABEL_SCHEMA_KEY)?->toHtmlString();
    $aboveContentSchema = $field?->getChildSchema($field::ABOVE_CONTENT_SCHEMA_KEY)?->toHtmlString();
    $belowContentSchema = $field?->getChildSchema($field::BELOW_CONTENT_SCHEMA_KEY)?->toHtmlString();
    $beforeContentSchema = $field?->getChildSchema($field::BEFORE_CONTENT_SCHEMA_KEY)?->toHtmlString();
    $afterContentSchema = $field?->getChildSchema($field::AFTER_CONTENT_SCHEMA_KEY)?->toHtmlString();
    $aboveErrorMessageSchema = $field?->getChildSchema($field::ABOVE_ERROR_MESSAGE_SCHEMA_KEY)?->toHtmlString();
    $belowErrorMessageSchema = $field?->getChildSchema($field::BELOW_ERROR_MESSAGE_SCHEMA_KEY)?->toHtmlString();

    // 🚀 Extração limpa do helperText
    $hasHelperText = filled($belowContentSchema);
    $helperTextContent = $hasHelperText ? trim(strip_tags($belowContentSchema)) : null;

    $hasError = $hasErrors && (filled($errorMessage) || filled($errorMessages) || (filled($statePath) && ($errors->has($statePath) || ($hasNestedRecursiveValidationRules && $errors->has("{$statePath}.*")))));

    if ($hasError && filled($statePath) && blank($errorMessage) && blank($errorMessages)) {
        if ($shouldShowAllValidationMessages) {
            $errorMessages = $errors->has($statePath) ? $errors->get($statePath) : ($hasNestedRecursiveValidationRules ? $errors->get("{$statePath}.*") : []);

            if (count($errorMessages) === 1) {
                $errorMessage = Arr::first($errorMessages);
                $errorMessages = [];
            }
        } else {
            $errorMessage = $errors->has($statePath) ? $errors->first($statePath) : ($hasNestedRecursiveValidationRules ? $errors->first("{$statePath}.*") : null);
        }
    }
@endphp

<div
    data-field-wrapper
    {{
        $attributes
            ->merge($field?->getExtraFieldWrapperAttributes() ?? [], escape: false)
            ->class([
                'fi-fo-field',
                'fi-fo-field-has-inline-label' => $hasInlineLabel,
            ])
    }}
>
    @if (filled($label) && $labelSrOnly)
        <{{ $labelTag }}
            @if ($labelTag === 'label')
                for="{{ $id }}"
            @else
                id="{{ $id }}-label"
            @endif
            class="fi-fo-field-label fi-sr-only"
        >
            {{ $label }}
        </{{ $labelTag }}>
    @endif

    @if ((filled($label) && (! $labelSrOnly)) || $hasInlineLabel || $aboveLabelSchema || $belowLabelSchema || $beforeLabelSchema || $afterLabelSchema || $labelPrefix || $labelSuffix)
        <div
            @class([
                'fi-fo-field-label-col',
                "fi-vertical-align-{$inlineLabelVerticalAlignment->value}" => $hasInlineLabel,
            ])
        >
            {{ $aboveLabelSchema }}

            <div
                @class([
                    'fi-fo-field-label-ctn flex items-center gap-x-1.5',
                    ($label instanceof \Illuminate\View\ComponentSlot) ? $label->attributes->get('class') : null,
                ])
            >
                {{ $beforeLabelSchema }}

                @if ((filled($label) && (! $labelSrOnly)) || $labelPrefix || $labelSuffix)
                    <{{ $labelTag }}
                        @if ($labelTag === 'label')
                            for="{{ $id }}"
                        @else
                            id="{{ $id }}-label"
                        @endif
                        class="fi-fo-field-label"
                    >
                        {{ $labelPrefix }}

                        @if (filled($label) && (! $labelSrOnly))
                            <span class="fi-fo-field-label-content">
                                {{ $label }}@if ($required && (! $isDisabled))<sup class="fi-fo-field-label-required-mark text-danger-600">*</sup>@endif
                            </span>
                        @endif

                        {{ $labelSuffix }}
                    </{{ $labelTag }}>

                    {{-- 🚀 Tooltip Heroicons --}}
                    @if ($hasHelperText && filled($label) && (! $labelSrOnly))
                        <span
                            x-data="{}"
                            x-tooltip="{ content: @js($helperTextContent) }"
                            class="inline-flex items-center justify-center text-primary-600 dark:text-primary-400 hover:text-primary-500 cursor-help transition-colors"
                            tabindex="-1"
                        >
                            <x-filament::icon
                                icon="heroicon-m-question-mark-circle"
                                class="h-4 w-4"
                            />
                        </span>
                    @endif
                @endif

                {{ $afterLabelSchema }}
            </div>

            {{ $belowLabelSchema }}
        </div>
    @endif

    @if ((! \Filament\Support\is_slot_empty($slot)) || $hasError || $aboveContentSchema || $belowContentSchema || $beforeContentSchema || $afterContentSchema || $aboveErrorMessageSchema || $belowErrorMessageSchema)
        <div class="fi-fo-field-content-col">
            {{ $aboveContentSchema }}

            @if ($beforeContentSchema || $afterContentSchema)
                <div class="fi-fo-field-content-ctn">
                    {{ $beforeContentSchema }}

                    <div class="fi-fo-field-content">
                        {{ $slot }}
                    </div>

                    {{ $afterContentSchema }}
                </div>
            @else
                {{ $slot }}
            @endif

            {{-- Oculta o div inferior para alinhar as colunas perfeitamente --}}
            @if (! ($hasHelperText && filled($label) && (! $labelSrOnly)))
                {{ $belowContentSchema }}
            @endif

            @if ($hasError)
                {{ $aboveErrorMessageSchema }}

                @if (filled($errorMessages))
                    <ul data-validation-error class="fi-fo-field-wrp-error-list">
                        @foreach ($errorMessages as $errorMessage)
                            <li class="fi-fo-field-wrp-error-message">
                                {!! $areHtmlErrorMessagesAllowed ? $errorMessage : e($errorMessage) !!}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p data-validation-error class="fi-fo-field-wrp-error-message">
                        {{ $errorMessage }}
                    </p>
                @endif

                {{ $belowErrorMessageSchema }}
            @endif
        </div>
    @endif
</div>

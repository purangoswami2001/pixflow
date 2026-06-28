<div id="ppToastHost" class="pp-toast-host" aria-live="polite" aria-atomic="true"></div>

<div id="ppDialogRoot" class="pp-dialog-root hidden" aria-hidden="true"></div>

<div id="ppLightboxRoot" class="pp-lightbox-root hidden" aria-hidden="true"></div>

@if (session('status') || session('error') || session('warning'))
    <script type="application/json" id="ppFlashData">{!! json_encode([
        'status' => session('status'),
        'error' => session('error'),
        'warning' => session('warning'),
    ], JSON_THROW_ON_ERROR) !!}</script>
@endif

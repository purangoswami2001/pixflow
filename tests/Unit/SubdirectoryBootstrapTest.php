<?php

it('strips subdirectory prefix from request uri using app url path', function () {
    require_once dirname(__DIR__, 2) . '/bootstrap/subdirectory.php';

    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_URI'] = '/pixflow/public/admin/users';
    $_SERVER['QUERY_STRING'] = '';

    $basePath = resolveApplicationBasePath();
    stripSubdirectoryFromRequest($basePath);

    expect($basePath)->toBe('/pixflow/public');
    expect($_SERVER['REQUEST_URI'])->toBe('/admin/users');
});

it('does not strip when request is already at application root', function () {
    require_once dirname(__DIR__, 2) . '/bootstrap/subdirectory.php';

    $_SERVER['SCRIPT_NAME'] = '/pixflow/public/index.php';
    $_SERVER['REQUEST_URI'] = '/admin/users';
    $_SERVER['QUERY_STRING'] = '';

    stripSubdirectoryFromRequest(resolveApplicationBasePath());

    expect($_SERVER['REQUEST_URI'])->toBe('/admin/users');
});

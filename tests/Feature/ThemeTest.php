<?php

declare(strict_types=1);

use App\Domain\Enums\Role;

/*
|--------------------------------------------------------------------------
| Dark mode — the bootstrap in <head>, the brand colours and the toggle
|--------------------------------------------------------------------------
|
| Contract (2026-11-28):
|
|  - `public/js/theme.js` runs synchronously in <head> before the stylesheets
|    and puts `data-theme="light|dark"` on <html>. The CSP is `script-src 'self'`
|    with no `unsafe-inline`, so an inline script is not an option.
|  - the choice is kept in `localStorage["sk-theme"]` ("light"/"dark"); with no
|    saved value the system preference applies.
|  - <meta name="theme-color"> carries both brand colours in `data-theme-color-*`
|    and <meta name="color-scheme" content="light dark"> lets the browser paint
|    scrollbars and form controls in the chosen theme.
|  - the toggle lives in `.app-bar__actions` on logged-in screens only. Standalone
|    pages (login, offline, errors) and the A4 printouts have no toggle, and the
|    printouts do not load the theme script at all — a printout never changes
|    colour.
|
*/

/** The theme bootstrap tag as rendered, or '' when the page does not load it. */
function themeBootstrapTag(string $html): string
{
    return preg_match('#<script\b[^>]*src="[^"]*js/theme\.js[^"]*"[^>]*>\s*</script>#i', $html, $matches) === 1
        ? $matches[0]
        : '';
}

/** The `<meta name="…">` tag as rendered, or '' when the page does not carry it. */
function themeMetaTag(string $html, string $name): string
{
    $pattern = '#<meta\b[^>]*name="'.preg_quote($name, '#').'"[^>]*>#i';

    return preg_match($pattern, $html, $matches) === 1 ? $matches[0] : '';
}

/** An XPath handle on the rendered document, for structural checks. */
function themeXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

it('loads the theme bootstrap synchronously in <head>, before the stylesheets', function () {
    $response = $this->get('/login');

    $response->assertOk();

    $html = (string) $response->getContent();
    $tag = themeBootstrapTag($html);

    expect($tag)->not->toBe(
        '',
        'Strona logowania nie ładuje /js/theme.js — bez niego <html> nigdy nie dostaje data-theme, a CSP zabrania skryptu inline.'
    );

    expect(is_file(public_path('js/theme.js')))
        ->toBeTrue('Brak pliku public/js/theme.js — przeglądarka dostanie 404 i motyw się nie ustawi.');

    // `defer`/`async` would paint the light theme first and repaint afterwards,
    // which is the flash the head script exists to prevent.
    expect($tag)->not->toContain('defer')
        ->and($tag)->not->toContain('async');

    expect(themeXPath($html)->query('//head//script[contains(@src, "js/theme.js")]')->length)
        ->toBe(1, '<script src="…/js/theme.js"> musi być w <head>.');

    $scriptAt = strpos($html, $tag);
    $firstStylesheetAt = strpos($html, 'css/bootstrap.min.css');

    expect($scriptAt)->not->toBeFalse()
        ->and($firstStylesheetAt)->not->toBeFalse()
        ->and($scriptAt)->toBeLessThan(
            (int) $firstStylesheetAt,
            'theme.js musi być wczytany przed pierwszym arkuszem CSS.'
        );
});

it('paints the browser chrome in both brand colours on standalone pages', function (string $uri, string $label) {
    $response = $this->get($uri);

    $response->assertOk();

    $html = (string) $response->getContent();

    $themeColor = themeMetaTag($html, 'theme-color');

    expect($themeColor)->not->toBe('', $label.': brak <meta name="theme-color">.')
        ->and($themeColor)->toContain('content="#2d343a"')
        // Both colours live on the one tag, so the script can swap it without
        // adding or removing markup.
        ->and($themeColor)->toContain('data-theme-color-light="#2d343a"')
        ->and($themeColor)->toContain('data-theme-color-dark="#0c1012"');

    expect(str_contains(themeMetaTag($html, 'color-scheme'), 'content="light dark"'))
        ->toBeTrue($label.': meta color-scheme musi dopuszczać oba motywy.');

    // There is nothing to switch on a standalone page: the app bar exists only
    // on logged-in screens.
    expect(str_contains($html, 'data-theme-toggle'))
        ->toBeFalse($label.' nie powinna mieć przełącznika motywu.');
})->with([
    ['/login', 'Logowanie'],
    ['/offline', 'Strona offline'],
]);

it('keeps the theme metadata on the error page and no toggle there either', function () {
    $response = $this->get('/nie-ma-takiej-strony');

    $response->assertNotFound();

    $html = (string) $response->getContent();
    $themeColor = themeMetaTag($html, 'theme-color');

    expect($themeColor)->not->toBe('', 'Strona błędu nie ma <meta name="theme-color">.')
        ->and($themeColor)->toContain('data-theme-color-dark="#0c1012"')
        ->and(themeMetaTag($html, 'color-scheme'))->toContain('content="light dark"')
        ->and($html)->not->toContain('data-theme-toggle');
});

it('offers the theme toggle in the app bar on logged-in screens', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $response = $this->actingAsUser($user)->get('/');

    $response->assertOk();

    $html = (string) $response->getContent();
    $xpath = themeXPath($html);

    // Exactly one toggle, and it sits in the bar's action group — not in a
    // sidebar or a settings screen the technician would have to hunt for.
    $buttons = $xpath->query(
        '//div[contains(concat(" ", normalize-space(@class), " "), " app-bar__actions ")]//button[@data-theme-toggle]'
    );

    expect($buttons->length)->toBe(
        1,
        'Przełącznik motywu musi być dokładnie jeden i musi siedzieć w .app-bar__actions.'
    );

    $button = $buttons->item(0);
    expect($button)->not->toBeNull();

    expect($button->getAttribute('class'))->toBe('theme-toggle')
        ->and($button->getAttribute('type'))->toBe('button')
        // The bar is server-rendered in the light theme; the script flips the
        // state (and sets the label) once it knows the saved choice.
        ->and($button->getAttribute('aria-pressed'))->toBe('false')
        ->and($button->getAttribute('aria-label'))->toBe('Włącz tryb ciemny')
        ->and($button->getAttribute('title'))->toBe('Tryb ciemny');

    // Two icons, and CSS decides which one is visible: swapping the markup in
    // JavaScript would flash the wrong glyph on a slow load.
    expect($xpath->query('.//span[contains(@class, "theme-toggle__icon--moon")]', $button)->length)
        ->toBe(1, 'Brak ikony księżyca (span.theme-toggle__icon--moon).')
        ->and($xpath->query('.//span[contains(@class, "theme-toggle__icon--sun")]', $button)->length)
        ->toBe(1, 'Brak ikony słońca (span.theme-toggle__icon--sun).');

    // The logged-in layout carries the same bootstrap and colour metadata.
    expect(themeBootstrapTag($html))->not->toBe('', 'Układ aplikacji nie ładuje /js/theme.js.');

    $themeColor = themeMetaTag($html, 'theme-color');

    expect($themeColor)->toContain('data-theme-color-light="#2d343a"')
        ->and($themeColor)->toContain('data-theme-color-dark="#0c1012"')
        ->and(themeMetaTag($html, 'color-scheme'))->toContain('content="light dark"');
})->with('stores');

it('keeps a strict script policy, so the theme bootstrap must be a file', function (string $uri) {
    $response = $this->get($uri);

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toBe('', $uri.': brak nagłówka Content-Security-Policy.')
        ->and($csp)->toContain("script-src 'self'")
        ->and($csp)->not->toContain('unsafe-inline')
        ->and($csp)->not->toContain('nonce-');
})->with(['/login', '/offline']);

it('keeps the same strict script policy on a logged-in screen', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $csp = (string) $this->actingAsUser($user)
        ->get('/')
        ->assertOk()
        ->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self'")
        ->and($csp)->not->toContain('unsafe-inline')
        ->and($csp)->not->toContain('nonce-');
})->with('stores');

it('leaves the A4 printouts without the theme script and the toggle', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_model' => 'Dell Latitude 5420']);

    foreach (['acceptance' => 'Potwierdzenie przyjęcia', 'release' => 'Protokół wydania'] as $print => $heading) {
        $response = $this->actingAsUser($user)->get('/services/'.$service->id.'/print/'.$print);

        $response->assertOk()->assertSee($heading);

        $html = (string) $response->getContent();

        expect(themeBootstrapTag($html))->toBe(
            '',
            'Wydruk '.$print.' nie może ładować theme.js — dokument na papierze nie zmienia motywu.'
        );

        expect($html)->not->toContain('data-theme-toggle');
    }
})->with('stores');

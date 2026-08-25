<?php
declare(strict_types=1);

/**
 * Kleine, abhängigkeitsfreie SVG-Icons (Strichzeichnungen, currentColor).
 * Bewusst inline statt Icon-Font/CDN, damit die App auch ohne
 * Internetzugriff auf der Synology funktioniert.
 */
function svgIcon(string $name, int $size = 18): string
{
    $paths = match ($name) {
        'plus'    => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'eye'     => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'pencil'  => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'   => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'back'    => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'check'   => '<polyline points="20 6 9 17 4 12"/>',
        'x'       => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'save'    => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        default   => '<circle cx="12" cy="12" r="9"/>',
    };
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true" focusable="false">' . $paths . '</svg>';
}

/** Icon-Button (Link) mit Tooltip statt Text. */
function iconLinkBtn(string $href, string $icon, string $label, string $variant = 'secondary', array $attrs = []): string
{
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= ' ' . $k . '="' . h($v) . '"';
    }
    return '<a href="' . h($href) . '" class="icon-btn ' . h($variant) . '" title="' . h($label)
         . '" aria-label="' . h($label) . '"' . $attrStr . '>' . svgIcon($icon) . '</a>';
}

/** Icon-Button (button-Element) mit Tooltip statt Text. */
function iconBtn(string $icon, string $label, string $variant = 'secondary', array $attrs = []): string
{
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= ' ' . $k . '="' . h($v) . '"';
    }
    return '<button type="button" class="icon-btn ' . h($variant) . '" title="' . h($label)
         . '" aria-label="' . h($label) . '"' . $attrStr . '>' . svgIcon($icon) . '</button>';
}

/**
 * Icon-Button, der eine schreibende Aktion per POST auslöst (Anlegen, Löschen).
 * Bewusst ein Formular statt eines Links: ein GET-Link kann vom Browser
 * vorausgeladen oder per Reload wiederholt werden und würde dann ungewollt
 * Datensätze anlegen bzw. löschen.
 *
 * $confirm landet als data-confirm-Attribut in der Seite; assets/app.js fragt
 * vor dem Absenden nach. Der Text wird dadurch genau einmal HTML-kodiert und
 * bleibt auch mit Apostroph in der Seriennummer korrekt.
 */
function iconFormBtn(string $action, string $icon, string $label, string $variant = 'secondary', array $hidden = [], string $confirm = ''): string
{
    $fields = '';
    foreach ($hidden as $k => $v) {
        $fields .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return '<form method="post" action="' . h($action) . '" class="inline-form"'
         . ($confirm !== '' ? ' data-confirm="' . h($confirm) . '"' : '') . '>'
         . $fields
         . '<button type="submit" class="icon-btn ' . h($variant) . '" title="' . h($label)
         . '" aria-label="' . h($label) . '">' . svgIcon($icon) . '</button>'
         . '</form>';
}

/**
 * Sprachumschalter für die Topbar. Die aktive Sprache ist als solche markiert;
 * der Link behält die übrigen Parameter der aktuellen Seite bei.
 */
function langSwitcher(): string
{
    $out = '<div class="lang-switch" role="group" aria-label="Language">';
    foreach (languages() as $code => $label) {
        $active = $code === currentLang();
        $out .= '<a href="' . h(langUrl($code)) . '" class="lang-opt' . ($active ? ' active' : '') . '"'
              . ($active ? ' aria-current="true"' : '') . ' hreflang="' . h($code) . '">' . h($label) . '</a>';
    }
    return $out . '</div>';
}

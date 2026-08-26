<?php
declare(strict_types=1);

$GLOBALS['T'] = ['pass' => 0, 'fail' => 0];

/** Vergleicht streng (===) und protokolliert das Ergebnis. */
function t_is(string $desc, mixed $got, mixed $want): void
{
    if ($got === $want) {
        $GLOBALS['T']['pass']++;
        printf("  ok   %s\n", $desc);
        return;
    }
    $GLOBALS['T']['fail']++;
    printf("  FAIL %s\n         erwartet: %s\n         erhalten: %s\n",
        $desc, var_export($want, true), var_export($got, true));
}

function t_summary(): int
{
    printf("\n%d bestanden, %d fehlgeschlagen\n", $GLOBALS['T']['pass'], $GLOBALS['T']['fail']);
    return $GLOBALS['T']['fail'] === 0 ? 0 : 1;
}

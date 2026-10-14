<?php

/**
 * Ensures every class and named method in the project code carries a PHPDoc block.
 *
 * Scans app/, database/factories and database/seeders — vendor and third-party
 * code are never scanned. Anonymous classes and closures are exempt.
 * Exits non-zero when a docblock is missing, so it can run as a pre-commit
 * hook and CI step:  php scripts/check-phpdocs.php
 */

/** Directories (relative to the project root) that must be fully documented. */
const PHPDOC_DIRECTORIES = ['app', 'database/factories', 'database/seeders'];

/**
 * Run the check over all configured directories and print the result.
 */
function main(): int
{
    $missing = [];
    $files = 0;

    foreach (PHPDOC_DIRECTORIES as $directory) {
        $path = dirname(__DIR__).'/'.$directory;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $files++;
            $missing = [...$missing, ...findMissingDocblocks($file->getPathname())];
        }
    }

    if ($missing !== []) {
        echo "✖ Fehlende PHPDoc-Blöcke:\n";
        foreach ($missing as $entry) {
            echo "  {$entry}\n";
        }
        echo "\nJede Klasse und jede Methode braucht einen /** … */-Block direkt davor.\n";

        return 1;
    }

    echo "✔ PHPDocs vollständig ({$files} Dateien geprüft).\n";

    return 0;
}

/**
 * Collect all classes/methods without a docblock in one file.
 *
 * @return list<string> Entries in the form "file:line: kind name".
 */
function findMissingDocblocks(string $file): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $missing = [];

    foreach ($tokens as $index => $token) {
        if (! is_array($token)) {
            continue;
        }
        [$id, , $line] = $token;
        $isClassLike = in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true);

        if (! $isClassLike && $id !== T_FUNCTION) {
            continue;
        }

        // Skip "Foo::class" constants and anonymous classes ("new class").
        if ($isClassLike) {
            $previous = previousMeaningfulToken($tokens, $index - 1);
            if (is_array($previous) && in_array($previous[0], [T_DOUBLE_COLON, T_NEW], true)) {
                continue;
            }
        }

        // Only named declarations need docs: after "function" (and an optional
        // "&") a T_STRING must follow, otherwise it is a closure.
        $next = nextMeaningfulIndex($tokens, $index + 1);
        if (! is_array($tokens[$next])) {
            continue;
        }
        if ($tokens[$next][1] === '&') {
            $next = nextMeaningfulIndex($tokens, $next + 1);
        }
        $nextToken = $tokens[$next];
        if (! is_array($nextToken) || $nextToken[0] !== T_STRING) {
            continue;
        }

        if (! hasDocBlock($tokens, $index)) {
            $kind = $isClassLike ? 'class' : 'method';
            $missing[] = "{$file}:{$line}: {$kind} {$nextToken[1]}";
        }
    }

    return $missing;
}

/**
 * Find the next token that is not whitespace or a comment.
 */
function nextMeaningfulIndex(array $tokens, int $index): int
{
    $count = count($tokens);
    while ($index < $count) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $index++;

            continue;
        }

        return $index;
    }

    return $index;
}

/**
 * Find the previous token that is not whitespace or a comment.
 */
function previousMeaningfulToken(array $tokens, int $index): array|string|null
{
    while ($index >= 0) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $index--;

            continue;
        }

        return $token;
    }

    return null;
}

/**
 * Check whether the declaration at the given token index is directly preceded
 * by a docblock, skipping modifiers, whitespace and attribute groups.
 */
function hasDocBlock(array $tokens, int $index): bool
{
    $modifiers = [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY];
    $attributeDepth = 0;

    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if ($attributeDepth > 0) {
            // Walk backwards through an attribute group: "]" opens, "#[" closes
            // the group, nested "[…]" pairs are balanced along the way.
            if ($token === ']') {
                $attributeDepth++;
            } elseif ($token === '[') {
                $attributeDepth--;
            } elseif (is_array($token) && $token[0] === T_ATTRIBUTE) {
                $attributeDepth--;
            }

            continue;
        }

        if ($token === ']') {
            $attributeDepth = 1;

            continue;
        }

        if (is_array($token)) {
            if ($token[0] === T_DOC_COMMENT) {
                return true;
            }
            if (in_array($token[0], $modifiers, true)) {
                continue;
            }
        }

        // Anything else (code, line comment) between docblock and declaration
        // means the declaration is undocumented.
        return false;
    }

    return false;
}

exit(main());

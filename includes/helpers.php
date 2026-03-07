<?php
/**
 * Helpers for Taskora
 */

function taskora_escape(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Very small Markdown-like renderer:
 * - **bold** -> <strong>
 * - lines starting with "- " -> <ul><li>
 * - lines starting with "1. " / "2. " ... -> <ol><li>
 * - new lines -> <br>
 *
 * Output is safe HTML generated from escaped text.
 */
function taskora_render_description(?string $text): string {
    if ($text === null) return '';
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Escape everything first (prevents XSS)
    $esc = taskora_escape($text);

    // Bold: **text**
    $esc = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $esc);

    $lines = explode("\n", $esc);
    $out = [];
    $inUl = false;
    $inOl = false;

    $closeLists = function() use (&$out, &$inUl, &$inOl) {
        if ($inUl) { $out[] = '</ul>'; $inUl = false; }
        if ($inOl) { $out[] = '</ol>'; $inOl = false; }
    };

    foreach ($lines as $line) {
        $trim = ltrim($line);

        if (preg_match('/^- (.+)$/', $trim, $m)) {
            if ($inOl) { $out[] = '</ol>'; $inOl = false; }
            if (!$inUl) { $out[] = '<ul>'; $inUl = true; }
            $out[] = '<li>' . $m[1] . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trim, $m)) {
            if ($inUl) { $out[] = '</ul>'; $inUl = false; }
            if (!$inOl) { $out[] = '<ol>'; $inOl = true; }
            $out[] = '<li>' . $m[1] . '</li>';
            continue;
        }

        // normal line
        $closeLists();
        if ($trim === '') {
            $out[] = '<br>';
        } else {
            $out[] = $line . '<br>';
        }
    }

    $closeLists();
    return implode("\n", $out);
}

/**
 * Normalize status names between UI and DB.
 */
function taskora_normalize_status(?string $status): string {
    $status = strtolower(trim((string)$status));
    $map = [
        'todo' => 'ready',
        'to_do' => 'ready',
        'ready' => 'ready',
        'in_progress' => 'progress',
        'progress' => 'progress',
        'review' => 'review',
        'done' => 'done',
        'zamkniete' => 'done',
        'zamknięte' => 'done',
    ];
    return $map[$status] ?? 'ready';
}

<?php

/**
 * Build GitHub wiki pages from docs/TUTORIAL.md (the single source of truth).
 *
 * Splits the tutorial on its top-level "## N." headings into one wiki page each,
 * plus Home and _Sidebar, written into <outdir>. The wiki-sync workflow then pushes
 * the result to the repo wiki. Do NOT hand-edit the wiki — edit docs/TUTORIAL.md and
 * let CI regenerate it.
 *
 * Page filenames are the heading text with spaces → hyphens, which is exactly how
 * GitHub maps a wiki page title to its file, so the generated files overwrite the
 * existing pages instead of creating duplicates.
 *
 * Usage: php .github/build-wiki.php docs/TUTORIAL.md _wiki
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

$input  = $argv[1] ?? '';
$outDir = $argv[2] ?? '';
if ('' === $input || '' === $outDir) {
    fwrite(STDERR, "usage: php build-wiki.php <tutorial.md> <outdir>\n");
    exit(2);
}

$md = @file_get_contents($input);
if (false === $md) {
    fwrite(STDERR, "error: cannot read {$input}\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0o777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "error: cannot create {$outDir}\n");
    exit(1);
}

$lines = explode("\n", $md);

// Collect top-level "## " headings that live OUTSIDE fenced code blocks.
$inFence  = false;
$headings = [];
foreach ($lines as $i => $line) {
    if (preg_match('/^\s*```/', $line)) {
        $inFence = !$inFence;
        continue;
    }
    if ($inFence) {
        continue;
    }
    if (preg_match('/^## (.+?)\s*$/', $line, $m)) {
        $headings[] = ['title' => trim($m[1]), 'line' => $i];
    }
}

if ([] === $headings) {
    fwrite(STDERR, "error: no '## ' headings found in {$input}\n");
    exit(1);
}

// Everything before the first "## " heading is the Home preamble (title, intro,
// the wiki note). The original "## Contents" list is dropped and regenerated.
$preamble = trim(implode("\n", array_slice($lines, 0, $headings[0]['line'])));

$pages = [];
$count = count($headings);
for ($h = 0; $h < $count; $h++) {
    $title = $headings[$h]['title'];
    if (!preg_match('/^\d+\./', $title)) {
        continue; // skip non-numbered H2 such as "Contents"
    }
    $start = $headings[$h]['line'];
    $end   = ($h + 1 < $count) ? $headings[$h + 1]['line'] : count($lines);

    $body = implode("\n", array_slice($lines, $start, $end - $start));
    $body = preg_replace('/^## /', '# ', $body, 1);   // promote section heading to page H1
    $body = trim($body);
    $body = (string) preg_replace('/\n+-{3,}\s*$/', '', $body); // drop trailing section divider
    $body = trim($body);

    $pages[] = [
        'title' => $title,
        'file'  => str_replace(' ', '-', $title) . '.md',
        'body'  => $body,
    ];
}

if ([] === $pages) {
    fwrite(STDERR, "error: no numbered '## N.' sections found in {$input}\n");
    exit(1);
}

$banner = "<!-- AUTO-GENERATED from docs/TUTORIAL.md — do NOT edit here; edit the tutorial in the repo "
        . "and CI will overwrite this page. -->\n\n";

// Home.md — preamble + a Contents list that links to the pages by wiki title.
$contents = "## Contents\n\n";
foreach ($pages as $n => $p) {
    $contents .= ($n + 1) . '. [[' . $p['title'] . "]]\n";
}
file_put_contents($outDir . '/Home.md', $banner . $preamble . "\n\n" . $contents . "\n");

// _Sidebar.md — navigation shown on every wiki page.
$sidebar = "### rector-xoops\n\n- [[Home]]\n";
foreach ($pages as $p) {
    $sidebar .= '- [[' . $p['title'] . "]]\n";
}
file_put_contents($outDir . '/_Sidebar.md', $banner . $sidebar);

// One file per numbered section.
foreach ($pages as $p) {
    file_put_contents($outDir . '/' . $p['file'], $banner . $p['body'] . "\n");
}

printf("Wrote %d pages + Home + _Sidebar to %s/\n", count($pages), $outDir);
foreach ($pages as $p) {
    echo "  - {$p['file']}\n";
}

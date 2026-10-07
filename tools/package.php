<?php

require_once dirname(__DIR__) . '/src/Analyzer.php';

$root = dirname(__DIR__);
$version = \Nakaryu\ShortcodeAudit\Analyzer::VERSION;
$allowedDirectories = ['src', 'includes', 'assets'];
$allowedFiles = ['nakaryu-shortcode-audit.php', 'readme.txt', 'README.md', 'LICENSE', 'CHANGELOG.md', 'CONTRIBUTING.md', 'SECURITY.md', 'docs/DE.md', 'docs/demo.html', 'docs/RELEASE.md', 'docs/VALIDATION.md'];
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP zip extension is required to build a package.\n");
    exit(1);
}
if (!is_dir($root . '/dist')) {
    mkdir($root . '/dist', 0755, true);
}
$path = $root . '/dist/nakaryu-shortcode-audit-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create package.');
}
$files = [];
foreach ($allowedFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException('Required release file missing: ' . $file);
    }
    $files[] = $file;
}
foreach ($allowedDirectories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && !$file->isLink() && in_array($file->getExtension(), ['php', 'css'], true)) {
            $files[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
}
sort($files);
foreach ($files as $file) {
    $zip->addFile($root . '/' . $file, 'nakaryu-shortcode-audit/' . $file);
}
$zip->close();
echo 'Built ' . basename($path) . ' with ' . count($files) . " allowlisted files.\n";

<?php
// download_zip.php

// Ensure ZipArchive is available
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo "Error: ZipArchive class not found. Please ensure the PHP Zip extension is enabled.";
    exit;
}
$targetFormatToDir = [
    // IEEE
    'IEEEtran Conference Format' => 'ieee_conference',
    'IEEEtran Journal Format'    => 'ieee_journal',
    'IEEE Access Format'         => 'ieee_access',

    // MDPI
    'MDPI' => 'mdpi',

    // Springer
    'Springer LNCS Conference Format (llncs)'       => 'springer_llncs',
    'Elsevier Article - Review (elsarticle review)'  => 'elsevier_elsarticle_review',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo "Error: This script only accepts POST requests.";
    exit;
}

$latexContent = $_POST['latex_content'] ?? '';
$formatChoice = $_POST['format_choice'] ?? '';

if (empty($latexContent) || empty($formatChoice)) {
    http_response_code(400); // Bad Request
    echo "Error: Missing LaTeX content or format choice.";
    exit;
}

if (!isset($targetFormatToDir[$formatChoice])) {
    http_response_code(400);
    error_log("Unknown format choice or no template directory mapping for: " . $formatChoice);
    echo "Error: Unknown format choice ('" . htmlspecialchars($formatChoice) . "') or no template directory mapping configured. Please check server logs and configuration.";
    exit;
}

$templateDirName = $targetFormatToDir[$formatChoice];
$templatePath = __DIR__ . '/latex_templates/' . $templateDirName;

$generatedTexFilename = 'main.tex'; 
$zipDownloadName = preg_replace('/[^a-z0-9_]/i', '_', strtolower($templateDirName)) . '_package.zip';


if (!is_dir($templatePath)) {
    error_log("Template directory not found: " . $templatePath . ". Sending .tex file only for format: " . $formatChoice);
    header('Content-Type: application/x-tex; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $generatedTexFilename . '"'); 
    header('Content-Length: ' . strlen($latexContent));
    echo $latexContent;
    exit;
}

$zip = new ZipArchive();
$zipFileName = tempnam(sys_get_temp_dir(), 'latex_pkg_') . '.zip';

if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    error_log("Cannot create ZIP archive: " . $zipFileName);
    echo "Error: Cannot create ZIP archive. Check server permissions or temp directory.";
    exit;
}

$zip->addFromString($generatedTexFilename, $latexContent);

$templateBasePath = realpath($templatePath);
if ($templateBasePath) { 
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($templateBasePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($templateBasePath) + 1);
            if ($relativePath === false || empty($relativePath)) { 
                $relativePath = $file->getFilename();
            }
            $zip->addFile($filePath, $relativePath);
        }
    }
} else {
    error_log("Failed to resolve realpath for template directory: " . $templatePath);
    $zip->addFromString('ERROR_NOTE.txt', "Could not read template files from: " . $templateDirName);
}


$zip->close();

if (!file_exists($zipFileName)) {
    http_response_code(500);
    error_log("ZIP file was not created or is missing after close: " . $zipFileName);
    echo "Error: ZIP file creation failed unexpectedly.";
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipDownloadName . '"');
header('Content-Length: ' . filesize($zipFileName));
header('Pragma: no-cache');
header('Expires: 0');

readfile($zipFileName);
unlink($zipFileName); 
exit;
?>

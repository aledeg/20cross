<?php

require_once 'vendor/autoload.php';
require_once 'Issue.php';

// Verify if pdftk is installed
exec('which pdftk', $output, $havePdftk);
if (0 !== $havePdftk) {
    echo 'Pdftk is not installed', PHP_EOL;
    echo 'On Debian distributions, run sudo apt-get install pdftk', PHP_EOL;
}
// Verify if pdfgrep is installed
exec('which pdfgrep', $output, $havePdfgrep);
if (0 !== $havePdfgrep) {
    echo 'Pdfgrep is not installed', PHP_EOL;
    echo 'On Debian distributions, run sudo apt-get install pdfgrep', PHP_EOL;
}
// Exit if libraries are not installed
if ((bool) $havePdftk || (bool) $havePdfgrep) {
    exit(1);
}

// Retrieve parameters
$options = getopt('', [
    "issue:",
    "keep",
    "to:",
]);

// Retrieve date parameter
$inputDates = 'now';
if (array_key_exists('issue', $options)) {
    $inputDates = $options['issue'];
}
if (!is_array($inputDates)) {
    $inputDates = [$inputDates];
}
$issues = array_map(function ($a) {return new Issue($a);}, $inputDates);

$fileAliases = '';
$pageAliases = '';
$messageBody = '';

foreach ($issues as $index => $issue) {
    // Download from website
    $src = @fopen($issue->getURL(), 'r');
    if (!$src) {
        continue;
    }
    $dest = fopen($issue->getFilename(), 'w');
    stream_copy_to_stream($src, $dest);

    // Find which page we need
    $value = exec(sprintf('pdfgrep -n \'Horoscope\' %s', $issue->getFilename()));
    list($pageNumber,$content) = explode(':', $value);
    $issue->setExtractionInfo($index, $pageNumber, $content);

    // Generating strings for later use
    $fileAliases .= ' ' . $issue->getFileAlias();
    $pageAliases .= ' ' . $issue->getPageAlias();
    $messageBody .= sprintf(" - %s : %s\n", $issue, $issue->getLevel());
}

// Generate the final pdf file
exec(sprintf('pdftk %s cat %s output mots.pdf', $fileAliases, $pageAliases));

if (!file_exists('mots.pdf')) {
    exit(1);
}

// Send email containing the page(s)
if (array_key_exists('to', $options)) {
    $message = Swift_Message::newInstance()
        ->setSubject(sprintf('Puzzle - %s', implode(' - ', $issues)))
        ->addPart($messageBody, 'text/plain')
        ->setFrom([
            'alexis.degrugillier@stadline.com' => 'Alexis',
        ])
        ->setTo($options['to'])
        ->attach(Swift_Attachment::fromPath('mots.pdf'));

    $transport = Swift_MailTransport::newInstance();
    $mailer = Swift_Mailer::newInstance($transport);
    $mailer->send($message);
}

// Delete files
if (!array_key_exists('keep', $options)) {
    $pdfs = glob('*.pdf');
    foreach ($pdfs as $pdf) {
        unlink($pdf);
    }
}

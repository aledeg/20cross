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

foreach ($issues as $index => $issue) {
    // Download from website
    $src = @fopen($issue->getURL(), 'r');
    if (!$src) {
        continue;
    }
    $dest = fopen($issue->getFilename(), 'w');
    stream_copy_to_stream($src, $dest);

    // Find which page we need
    $value = exec(sprintf('pdfgrep -i -n \'horoscope\' %s', $issue->getFilename()));
    list($pageNumber,) = explode(':', $value);
    $issue->setExtractionInfo($index, $pageNumber);
}

// Generate the final pdf file
$fileAliases = array_reduce($issues, function($carry, $item) {
    return sprintf('%s %s', $carry, $item->getFileAlias());
}, '');
$pageAliases = array_reduce($issues, function($carry, $item) {
    return sprintf('%s %s', $carry, $item->getPageAlias());
}, '');
exec(sprintf('pdftk %s cat %s output mots.pdf', $fileAliases, $pageAliases));

// Send email containing the page
if (array_key_exists('to', $options)) {
    $message = Swift_Message::newInstance()
        ->setSubject(sprintf('Puzzle - %s', implode(' - ', $issues)))
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

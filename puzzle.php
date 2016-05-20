<?php

require_once 'vendor/autoload.php';

// Verify if pdftk is installed
exec('which pdftk', $output, $havePdftk);
if (0 !== $havePdftk) {
    echo 'Pdftk is not installed', PHP_EOL;
    exit(1);
}
// Verify if pdfgrep is installed
exec('which pdfgrep', $output, $havePdfgrep);
if (0 !== $havePdfgrep) {
    echo 'Pdfgrep is not installed', PHP_EOL;
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

foreach ($inputDates as $inputDate) {
    $date = new \DateTime($inputDate);
    $formattedDate = $date->format('Ymd');

    // Download from website
    $src = @fopen(sprintf('http://pdf.20mn.fr/2016/quotidien/%s_PAR.pdf?1', $formattedDate), 'r');
    if (!$src) {
        continue;
    }
    $dest = fopen('paper.pdf', 'w');
    stream_copy_to_stream($src, $dest);

    // Find which page we need
    $value = exec('pdfgrep -i -n \'mots fl.*ch.*s N°\' paper.pdf');
    $splitedValue = explode(':', $value);
    $pageNumber = $splitedValue[0];

    // Extract the page we need
    exec(sprintf('pdftk paper.pdf cat %s output page%s.pdf', $pageNumber, $formattedDate));
}

if (!glob('page*.pdf')) {
    exit(1);
}

// Combine all pages
exec('pdftk page*.pdf cat output mots.pdf');

// Send email containing the page
if (array_key_exists('to', $options)) {
    $message = Swift_Message::newInstance()
        ->setSubject(sprintf('Puzzle - %s', $formattedDate))
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


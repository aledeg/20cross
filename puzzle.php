<?php

require_once 'vendor/autoload.php';

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
$inputDates = array_map(function ($a) {return new \DateTime($a);}, $inputDates);

foreach ($inputDates as $inputDate) {
    $formattedDate = $inputDate->format('Ymd');
    $formattedYear = $inputDate->format('Y');

    // Download from website
    $src = @fopen(sprintf('http://pdf.20mn.fr/%s/quotidien/%s_LIL.pdf?1', $formattedYear, $formattedDate), 'r');
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
    getPage('paper.pdf', $pageNumber, $formattedDate);
}

if (!glob('page*.pdf')) {
    exit(1);
}

// Combine all pages
exec('pdftk page*.pdf cat output mots.pdf');

// Send email containing the page
if (array_key_exists('to', $options)) {
    $formattedDates = array_map(function($a){return $a->format('d-m-Y');}, $inputDates);
    $message = Swift_Message::newInstance()
        ->setSubject(sprintf('Puzzle - %s', implode(' - ', $formattedDates)))
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

function getPage($filename, $pageNumber, $date) {
    $width = 3220;
    $height = 4720;
    $resizeFactor = 4;

    $im = new Imagick();
    $im->setResolution(600,600);
    $im->readimage(sprintf('%s[%s]', $filename, $pageNumber - 1));
    $im->cropImage($width, $height, 1970, 260);
    $im->setImagePage(0, 0, 0, 0);
    $im->resizeimage($width / $resizeFactor, $height / $resizeFactor, imagick::FILTER_LANCZOS, 1);
    $im->setimagecolorspace(imagick::COLORSPACE_GRAY);
    $im->setImageFormat('pdf');
    $im->writeImage(sprintf('page%s.pdf', $date));
    $im->clear();
    $im->destroy();
}

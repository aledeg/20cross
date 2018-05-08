<?php

class Issue {
    /** @var \DateTime */
    private $date;

    /** @var int */
    private $page;

    /** @var string */
    private $alias;

    public function __construct(string $date)
    {
        $this->date = new \DateTime($date);
    }

    public function getURL(): string
    {
        return sprintf(
            'http://pdf.20mn.fr/%1$s/quotidien/%2$s_LIL.pdf?1',
            $this->date->format('Y'),
            $this->date->format('Ymd')
        );
    }

    public function getFilename(): string
    {
        return sprintf('page%s.pdf', $this->date->format('Ymd'));
    }

    public function getFileAlias(): string
    {
        return sprintf('%s=%s', $this->alias, $this->getFilename());
    }

    public function getPageAlias(): string
    {
        return sprintf('%s%s', $this->alias, $this->page);
    }

    public function __toString(): string
    {
        return $this->date->format('d-m-Y');
    }

    public function setExtractionInfo(int $index, int $page)
    {
        $this->page = $page;
        $this->alias = chr($index + 65);
    }
}

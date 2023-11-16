<?php

namespace Underhood\Command;

use Psr\Log\LoggerAwareInterface;
use Symfony\Component;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\BrowserKit;
use Symfony\Component\Console;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Class DefaultCommand
 */
class Parse extends Console\Command\Command
{
    /** @var string */
    const ARGUMENT_NAME = 'search_text';
    /** @var int */
    const HTTP_CLIENT_LOGGER_VERBOSITY = Console\Output\OutputInterface::VERBOSITY_NORMAL; // Console\Output\OutputInterface::VERBOSITY_DEBUG;

    /** @var string todo: make as cli option */
    protected $initUrl = 'https://search.ipaustralia.gov.au/trademarks/search/advanced';
    /** @var BrowserKit\HttpBrowser */
    protected $browser = null;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->addArgument(self::ARGUMENT_NAME, Console\Input\InputArgument::REQUIRED|Console\Input\InputArgument::IS_ARRAY)
        ;
    }

    /**
     * @param bool $useShared
     *
     * @return BrowserKit\HttpBrowser
     */
    protected function getBrowser(bool $useShared = true): BrowserKit\HttpBrowser
    {
        if (null === $this->browser || !$useShared) {
            $client = HttpClient::create();
            if ($client instanceof LoggerAwareInterface) {
                $client->setLogger(new Console\Logger\ConsoleLogger(
                    new Console\Output\ConsoleOutput(self::HTTP_CLIENT_LOGGER_VERBOSITY)
                ));
            }

            $this->browser = new BrowserKit\HttpBrowser($client);
        }

        return $this->browser;
    }

    /**
     * @param Crawler $crawler
     * @param string  $selector
     * @param mixed   $default
     *
     * @return string
     */
    protected function getNodeText(Crawler $crawler, string $selector, $default = null): ?string
    {
        $node = $crawler->filter($selector);
        if (!$node->count()) {
            return $default;
        }

        return $node->text($default);
    }

    /**
     * @param Crawler $crawler
     * @param string  $selector
     * @param string  $attr
     * @param mixed   $default
     *
     * @return string
     */
    protected function getNodeAttr(Crawler $crawler, string $selector, string $attr, $default = null): ?string
    {
        $node = $crawler->filter($selector);

        return 1 == $node->count() ? $node->attr($attr) : $default;
    }

    /**
     * @param Crawler $crawler
     *
     * @return array
     */
    protected function getTrParsedData(Crawler $crawler): array
    {
        return [
            'number' => $this->getNodeText($crawler, 'td.number a'),
            'url_logo' => $this->getNodeAttr($crawler, 'td.trademark.image img', 'src'),
            'name' => $this->getNodeText($crawler, 'td.trademark.words'),
            'class' => $this->getNodeText($crawler, 'td.classes'),
            'status' => $this->getNodeText($crawler, 'td.status span') ?? $this->getNodeText($crawler, 'td.status'),
            'url_details_page' => $this->getNodeAttr($crawler, 'td.number a', 'href'),
        ];
    }

    /**
     * @param Crawler $crawler
     *
     * @return array
     */
    protected function getPageParsedData(Crawler $crawler): array
    {
        return $crawler->filter('#resultsTable tbody tr')->each(function (Crawler $trCrawler, $i) {
            return $this->getTrParsedData($trCrawler);
        });
    }

    /**
     * @param Crawler $crawler
     *
     * @return Crawler|null
     */
    protected function getNextResultsPage(Crawler $crawler): ?Crawler
    {
        $nextPageNodes = $crawler->filter('#pageContent div.pagination-buttons a.js-nav-next-page');
        if (!$nextPageNodes->count()) {
            return null;
        }

        return $this->getBrowser()->request('GET', $nextPageNodes->first()->link()->getUri());
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): ?int
    {
        $result = [];
        $searchText = implode(' ', $input->getArgument(self::ARGUMENT_NAME));

        // request search page to have cookies and csrf field
        $browser = $this->getBrowser();
        $searchPageCrawler = $browser->request('GET', $this->initUrl);
        // fill form fields
        $form = $searchPageCrawler->filter('#basicSearchForm')->form();
        $form['wv[0]'] = $searchText;

        // get 1st search results page
        $crawler = $browser->submit($form);
        $result = array_merge($result, $this->getPageParsedData($crawler));

        // traverse pages
        while ($crawler = $this->getNextResultsPage($crawler)) {
            $result = array_merge($result, $this->getPageParsedData($crawler));
        }

        echo json_encode($result, JSON_UNESCAPED_SLASHES);

        return 0; // Command::SUCCESS;
    }
}

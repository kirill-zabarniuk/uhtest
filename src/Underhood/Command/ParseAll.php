<?php

namespace Underhood\Command;

use Psr\Log\LoggerAwareInterface;
use Symfony\Component;
use Symfony\Component\BrowserKit;
use Symfony\Component\Console;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Class DefaultCommand
 */
class ParseAll extends Console\Command\Command
{
    /** @var string */
    protected $initUrl = 'https://search.ipaustralia.gov.au/trademarks/search/advanced';
    /** @var string */
    protected $searchUrl = 'https://search.ipaustralia.gov.au/trademarks/search/doSearch';
    /** @var BrowserKit\HttpBrowser */
    protected $browser = null;

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
                    new Console\Output\ConsoleOutput(Console\Output\OutputInterface::VERBOSITY_DEBUG)
                ));
            }

            $this->browser = new BrowserKit\HttpBrowser($client);
        }

        return $this->browser;
    }

    /**
     * @param BrowserKit\HttpBrowser $browser
     * @param string|null            $url
     *
     * @return Component\DomCrawler\Crawler|null
     */
    protected function initBrowser(BrowserKit\HttpBrowser $browser, string $url = null): ?Component\DomCrawler\Crawler
    {
        if (null === $url) {
            $url = $this->initUrl;
        }

        return $browser->request('GET', $url);
    }

    /**
     * @param array  $body
     * @param string $method
     *
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function request(array $body = [], string $method = 'POST'): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $rq = HttpClient::create();

        return $rq->request($method, $this->searchUrl, [
            'body' => $body,
        ]);
    }

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $browser = $this->getBrowser();
        $initCrawler = $this->initBrowser($browser);
        $csrfNode = $initCrawler->filter('#basicSearchForm input[name="_csrf"]');
        if (!$csrfNode->count()) {
            throw new \Exception('csrf node not found');
        }
        $csrf = $csrfNode->attr('value');

        $crawler = $browser->request('POST', $this->searchUrl, [
            '_csrf' => $csrf,
            'wv[0]' => 'abc',
        ]);
        var_dump($browser->getCookieJar()->allRawValues($this->searchUrl));

        /** @var BrowserKit\Response $response */
        $response = $browser->getResponse();
        var_dump($response->getContent());

        return 0; // Command::SUCCESS;
    }
}

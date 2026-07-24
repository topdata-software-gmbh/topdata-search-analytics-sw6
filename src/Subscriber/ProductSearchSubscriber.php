<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSearchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_SEARCH_RESULT => 'onSearchResult',
            ProductEvents::PRODUCT_SUGGEST_RESULT => 'onSearchResult',
        ];
    }

    public function onSearchResult($event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Skip AJAX filter / pagination / sorting requests on the main search page
        if ($route === 'frontend.search.page' && $request->isXmlHttpRequest()) {
            return;
        }

        $term = $request->get('search');

        if ($term === null || trim($term) === '') {
            return;
        }

        $term = mb_strtolower(trim($term));
        if (mb_strlen($term) > 255) {
            $term = mb_substr($term, 0, 255);
        }

        if (mb_strlen($term) < 2) {
            return;
        }

        $resultCount = $event->getResult()->getTotal();
        $sessionToken = $event->getSalesChannelContext()->getToken();

        try {
            // Deduplicate: check the last log for this session token
            $lastLog = $this->connection->fetchAssociative(
                'SELECT `id`, `term`, `result_count`, `created_at`
                 FROM `tdsa_search_log`
                 WHERE `session_token` = :session_token
                 ORDER BY `created_at` DESC
                 LIMIT 1',
                ['session_token' => $sessionToken]
            );

            if ($lastLog) {
                $lastCreatedAt = new \DateTime($lastLog['created_at']);
                $now = new \DateTime();
                $timeDiff = $now->getTimestamp() - $lastCreatedAt->getTimestamp();

                // If the term is identical and was logged within the last 15 seconds
                if ($lastLog['term'] === $term && $timeDiff <= 15) {
                    $this->connection->executeStatement(
                        'UPDATE `tdsa_search_log`
                         SET `result_count` = :result_count, `created_at` = :now
                         WHERE `id` = :id',
                        [
                            'result_count' => $resultCount,
                            'now' => $now->format('Y-m-d H:i:s.v'),
                            'id' => $lastLog['id'],
                        ]
                    );
                    return;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Search log dedup lookup failed, falling back to insert', [
                'error' => $e->getMessage(),
                'term' => $term,
                'session_token' => $sessionToken,
            ]);
        }

        try {
            $this->connection->executeStatement(
                'INSERT INTO `tdsa_search_log` (`id`, `session_token`, `term`, `result_count`, `created_at`)
                 VALUES (:id, :session_token, :term, :result_count, :now)',
                [
                    'id' => Uuid::randomBytes(),
                    'session_token' => $sessionToken,
                    'term' => $term,
                    'result_count' => $resultCount,
                    'now' => (new \DateTime())->format('Y-m-d H:i:s.v')
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Search log insert failed', [
                'error' => $e->getMessage(),
                'term' => $term,
                'session_token' => $sessionToken,
            ]);
        }
    }
}

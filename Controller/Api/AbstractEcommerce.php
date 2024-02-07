<?php

namespace Apsis\One\Controller\Api;

use Apsis\One\Controller\Api\Carts\Index;
use Apsis\One\Controller\Api\Carts\Items;
use Apsis\One\Controller\Api\Profiles\Index as ProfilesIndex;
use Apsis\One\Service\ProfileService;
use Apsis\One\Service\Sub\SubAbandonedService;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Throwable;

abstract class AbstractEcommerce extends AbstractApi
{
    const SCHEMA = [];

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = [
        'Schema' => ['GET', 'HEAD'],
        'Items' => ['GET', 'HEAD']
    ];

    /**
     * @var QuoteCollectionFactory
     */
    private QuoteCollectionFactory $quoteCollectionFactory;

    /**
     * @var SubAbandonedService
     */
    private SubAbandonedService $subAbandonedService;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ProfileService $service
     * @param CustomerFactory $customerFactory
     * @param EncryptorInterface $encryptor
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param SubAbandonedService $subAbandonedService
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        ProfileService $service,
        CustomerFactory $customerFactory,
        EncryptorInterface $encryptor,
        QuoteCollectionFactory $quoteCollectionFactory,
        SubAbandonedService $subAbandonedService
    ) {
        parent::__construct($request, $response, $service, $customerFactory, $encryptor);
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->subAbandonedService = $subAbandonedService;
    }

    /**
     * @return ResponseInterface
     */
    public function getSchema(): ResponseInterface
    {
        return $this->sendResponse(200, null, json_encode(static::SCHEMA));
    }

    /**
    /**
     * @return ResponseInterface
     */
    public function getItems(): ResponseInterface
    {
        try {
            $records = $this->getRecords();
            if (is_int($records)) {
                return $this->sendErrorInResponse($records);
            }
            return $this->sendResponse(200, null, json_encode($records));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return int|array
     */
    protected function getRecords(): int|array
    {
        try {
            $collection = $this->getQuoteCollection();
            if (is_int($collection)) {
                return $collection;
            }

            if (empty($collection)) {
                return [];
            }

            $records = [];
            $carts = $this->subAbandonedService->aggregateCartsData($collection, true);
            foreach ($carts as $cart) {
                if ($this instanceof Index) {
                    $dataArr = $this->getDataArr($cart);
                    if (! empty($dataArr)) {
                        $records[] = $dataArr;
                    }
                } elseif ($this instanceof Items && ! empty($cart['items']) && ! empty($cart['cart_id'])) {
                    $dataArr = $this->getDataArrForCartItems($cart['items'], $cart['cart_id']);
                    if (! empty($dataArr)) {
                        $records = array_merge($records, $dataArr);
                    }
                }
            }
            return $records;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param array $cartItems
     * @param int $cartId
     *
     * @return array
     */
    protected function getDataArrForCartItems(array $cartItems, int $cartId): array
    {
        try {
            $records = [];
            foreach ($cartItems as $item) {
                $item['cart_id'] = $cartId;
                $dataArr = $this->getDataArr($item);
                if (! empty($dataArr)) {
                    $records[] = $dataArr;
                }
            }
            return $records;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function getDataArr(array $data): array
    {
        try {
            $dataArr = [];
            foreach (static::SCHEMA as $field) {
                $codeName = $field['code_name'];
                $type = $field['type'];
                $value = $data[$codeName] ?? null;

                if (is_null($value)) {
                    $dataArr[$codeName] = null;
                } elseif ($type === 'integer' || $type === ProfilesIndex::ENUM_UNIX_S) {
                    $dataArr[$codeName] = ($value === '') ? null : (integer) $value;
                } elseif ($type === 'double') {
                    $dataArr[$codeName] = ($value === '') ? null : round($value, 2);
                } elseif ($type === 'string') {
                    $dataArr[$codeName] = (string) $value;
                }
            }
            return $dataArr;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @return QuoteCollection|array|int
     */
    protected function getQuoteCollection(): QuoteCollection|array|int
    {
        try {
            $collection = $this->quoteCollectionFactory->create();
            $cartIds = ! empty($this->queryParams['cart_ids']) ?
                explode(',', (string) $this->queryParams['cart_ids']) : null;
            if (isset($this->queryParams['cart_ids']) && empty($cartIds)) {
                return [];
            }

            if (is_array($cartIds)) {
                $collection->addFieldToFilter('entity_id', ['in' => $cartIds]);
            }

            $profileIds = ! empty($this->queryParams['profile_keys']) ?
                explode(',', (string) $this->queryParams['profile_keys']) : null;
            if (isset($this->queryParams['profile_keys']) && empty($profileIds)) {
                return [];
            }

            if (is_array($profileIds)) {
                $profileCollection = $this->service->getProfileCollection()->getCollection('id', $profileIds);
                if (! $profileCollection->getSize()) {
                    return [];
                }

                $collection->addFieldToFilter(
                    'customer_email',
                    ['in' => $profileCollection->getColumnValues('email')]
                );
            }

            if (isset($this->queryParams['from']) || isset($this->queryParams['to'])) {
                if (empty($this->queryParams['from']) || empty($this->queryParams['to'])) {
                    return [];
                }

                $from = (int) $this->queryParams['from'];
                $to = (int) $this->queryParams['to'];
                $updated = [
                    'from' => $this->service->getDateTimeFromTimeAndTimeZone('@'. $from/1000)->format('Y-m-d H:i:s'),
                    'to' => $this->service->getDateTimeFromTimeAndTimeZone('@'. $to/1000)->format('Y-m-d H:i:s'),
                    'date' => true,
                ];
                $collection->addFieldToFilter('main_table.updated_at', $updated);
            }

            $collection->addFieldToFilter('is_active', 1)->addFieldToFilter('items_count', ['gt' => 0]);
            return $this->setPaginationOnCollection($collection, 'entity_id');
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }
}

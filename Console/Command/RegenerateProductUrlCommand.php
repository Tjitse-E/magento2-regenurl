<?php

namespace Iazel\RegenProductUrl\Console\Command;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\State;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateProductUrlCommand extends Command
{
    /**
     * @var ProductUrlRewriteGenerator\Proxy
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var UrlPersistInterface\Proxy
     */
    protected $urlPersist;

    /**
     * @var ProductRepositoryInterface\Proxy
     */
    protected $collection;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;
    /**
     * @var StoreManagerInterface\Proxy
     */
    private $storeManager;

    /**
     * RegenerateProductUrlCommand constructor.
     * @param State $state
     * @param Collection\Proxy $collection
     * @param ProductUrlRewriteGenerator\Proxy $productUrlRewriteGenerator
     * @param UrlPersistInterface\Proxy $urlPersist
     * @param StoreManagerInterface\Proxy $storeManager
     */
    public function __construct(
        State $state,
        Collection\Proxy $collection,
        ProductUrlRewriteGenerator\Proxy $productUrlRewriteGenerator,
        UrlPersistInterface\Proxy $urlPersist,
        StoreManagerInterface\Proxy $storeManager
    ) {
        $this->state = $state;
        $this->collection = $collection;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
        parent::__construct();
        $this->storeManager = $storeManager;
    }

    protected function configure()
    {
        $this->setName('regenerate:product:url')
            ->setDescription('Regenerate url for given products')
            ->addArgument(
                'pids',
                InputArgument::IS_ARRAY,
                'Products to regenerate'
            )
            ->addOption(
                'store',
                's',
                InputOption::VALUE_REQUIRED,
                'Use the specific Store View',
                Store::DEFAULT_STORE_ID
            )
            ->addOption(
                'only-visible',
                null,
                InputOption::VALUE_OPTIONAL,
                'Only generate urls for products that are visible',
                false
            );
        return parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode('adminhtml');
        }

        $store_id = $input->getOption('store');
        $stores = $this->storeManager->getStores(false);

        if (!is_numeric($store_id)) {
            $store_id = $this->getStoreIdByCode($store_id, $stores);
        }

        foreach ($stores as $store) {
            // If store has been given through option, skip other stores
            if ($store_id != Store::DEFAULT_STORE_ID and $store->getId() != $store_id) {
                continue;
            }

            $this->collection->addStoreFilter($store_id)->setStoreId($store_id);

            // Visibility filter
            if ((bool)$input->getOption('only-visible')) {
                $output->writeln("<info>Only regenerating visbile products.</info>");
                $this->collection->addFieldToFilter(
                    'visibility',
                    [
                        'in' => [
                            Visibility::VISIBILITY_BOTH,
                            Visibility::VISIBILITY_IN_CATALOG,
                            Visibility::VISIBILITY_IN_SEARCH
                        ]
                    ]
                );
            }

            $pids = $input->getArgument('pids');
            if (!empty($pids)) {
                $this->collection->addIdFilter($pids);
            }

            $this->collection->addAttributeToSelect(['url_path', 'url_key']);

            $count = $this->collection->count();
            $output->writeln("<info>{$count} products found for store {$store_id}. Start regeneration.</info>");

            $list = $this->collection->load();
            $regenerated = 0;
            foreach ($list as $product) {
                echo 'Regenerating urls for ' . $product->getSku() . ' (' . $product->getId(
                    ) . ') in store ' . $store->getName() . PHP_EOL;
                $product->setStoreId($store_id);

                $this->urlPersist->deleteByData(
                    [
                        UrlRewrite::ENTITY_ID => $product->getId(),
                        UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                        UrlRewrite::REDIRECT_TYPE => 0,
                        UrlRewrite::STORE_ID => $store_id
                    ]
                );

                $newUrls = $this->productUrlRewriteGenerator->generate($product);
                try {
                    $this->urlPersist->replace($newUrls);
                    $regenerated += count($newUrls);
                } catch (\Exception $e) {
                    $output->writeln(
                        sprintf(
                            '<error>Duplicated url for store ID %d, product %d (%s) - %s Generated URLs:' . PHP_EOL . '%s</error>' . PHP_EOL,
                            $store_id,
                            $product->getId(),
                            $product->getSku(),
                            $e->getMessage(),
                            implode(PHP_EOL, array_keys($newUrls))
                        )
                    );
                }
            }
            $output->writeln('Done regenerating. Regenerated ' . $regenerated . ' urls for store ' . $store->getName());
        }
    }

    private function getStoreIdByCode($store_id, $stores)
    {
        foreach ($stores as $store) {
            if ($store->getCode() == $store_id) {
                return $store->getId();
            }
        }

        return Store::DEFAULT_STORE_ID;
    }
}

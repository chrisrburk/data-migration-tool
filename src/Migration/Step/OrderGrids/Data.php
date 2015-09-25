<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Migration\Step\OrderGrids;

use Migration\App\Step\StageInterface;
use Migration\Config;
use Migration\Handler;
use Migration\Resource;
use Migration\Resource\Record;
use Migration\App\ProgressBar;
use Migration\Logger\Manager as LogManager;
use Migration\Logger\Logger;
use Migration\Resource\Adapter\Mysql;

/**
 * Class Data
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Data implements StageInterface
{
    /**
     * @var Resource\Source
     */
    protected $source;

    /**
     * @var Mysql
     */
    protected $destinationAdapter;

    /**
     * @var Resource\Destination
     */
    protected $destination;

    /**
     * @var ProgressBar\LogLevelProcessor
     */
    protected $progress;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Resource\RecordFactory
     */
    protected $recordFactory;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param ProgressBar\LogLevelProcessor $progress
     * @param Resource\Source $source
     * @param Resource\Destination $destination
     * @param Resource\RecordFactory $recordFactory
     * @param Logger $logger
     * @param Helper $helper
     * @param Config $config
     */
    public function __construct(
        ProgressBar\LogLevelProcessor $progress,
        Resource\Source $source,
        Resource\Destination $destination,
        Resource\RecordFactory $recordFactory,
        Logger $logger,
        Helper $helper,
        Config $config
    ) {
        $this->source = $source;
        $this->destination = $destination;
        $this->destinationAdapter = $this->destination->getAdapter();
        $this->progress = $progress;
        $this->recordFactory = $recordFactory;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->config = $config;
    }

    /**
     * @return bool
     */
    public function perform()
    {
        $this->progress->start($this->getIterationsCount(), LogManager::LOG_LEVEL_INFO);
        foreach ($this->getDocumentList() as $methodToExecute => $document) {
            $destinationDocumentName = $document['destination'];
            $this->destination->clearDocument($destinationDocumentName);
            $this->progress->start(1, LogManager::LOG_LEVEL_DEBUG);

            $sourceGridDocument = array_flip($this->helper->getDocumentList())[$destinationDocumentName];
            $entityIdsSelect = $this->getEntityIdsSelect($sourceGridDocument);
            if ($entityIdsSelect) {
                $this->destination->getAdapter()->insertFromSelect(
                    $this->{$methodToExecute}($document['columns'], new \Zend_Db_Expr($entityIdsSelect)),
                    $this->destination->addDocumentPrefix($destinationDocumentName),
                    [],
                    \Magento\Framework\Db\Adapter\AdapterInterface::INSERT_ON_DUPLICATE
                );
            }
            $this->progress->finish(LogManager::LOG_LEVEL_DEBUG);
        }
        $this->progress->finish(LogManager::LOG_LEVEL_INFO);
        return true;
    }

    /**
     * @return int
     */
    protected function getIterationsCount()
    {
        return count($this->getDocumentList());
    }

    /**
     * @param array $columns
     * @param \Zend_Db_Expr $entityIdsSelect
     * @return \Magento\Framework\DB\Select
     */
    public function getSelectSalesOrderGrid(array $columns, \Zend_Db_Expr $entityIdsSelect)
    {
        foreach ($columns as $key => $value) {
            $columns[$key] = new \Zend_Db_Expr($value);
        }
        /** @var \Magento\Framework\DB\Select $select */
        $select = $this->destinationAdapter->getSelect();
        $select->from(['sales_order' => $this->destination->addDocumentPrefix('sales_order')], [])
            ->joinLeft(
                ['sales_shipping_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_order.shipping_address_id = sales_shipping_address.entity_id',
                []
            )->joinLeft(
                ['sales_billing_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_order.billing_address_id = sales_billing_address.entity_id',
                []
            )->where('sales_order.entity_id in (?)', $entityIdsSelect);
        $select->columns($columns);
        return $select;
    }

    /**
     * @param array $columns
     * @param \Zend_Db_Expr $entityIdsSelect
     * @return \Magento\Framework\DB\Select
     */
    public function getSelectSalesInvoiceGrid(array $columns, \Zend_Db_Expr $entityIdsSelect)
    {
        foreach ($columns as $key => $value) {
            $columns[$key] = new \Zend_Db_Expr($value);
        }
        /** @var \Magento\Framework\DB\Select $select */
        $select = $this->destinationAdapter->getSelect();
        $select->from(['sales_invoice' => $this->destination->addDocumentPrefix('sales_invoice')], [])
            ->joinLeft(
                ['sales_order' => $this->destination->addDocumentPrefix('sales_order')],
                'sales_invoice.order_id = sales_order.entity_id',
                []
            )->joinLeft(
                ['sales_shipping_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_invoice.shipping_address_id = sales_shipping_address.entity_id',
                []
            )->joinLeft(
                ['sales_billing_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_invoice.billing_address_id = sales_billing_address.entity_id',
                []
            )->where('sales_invoice.entity_id in (?)', $entityIdsSelect);
        $select->columns($columns);
        return $select;
    }

    /**
     * @param array $columns
     * @param \Zend_Db_Expr $entityIdsSelect
     * @return \Magento\Framework\DB\Select
     */
    public function getSelectSalesShipmentGrid(array $columns, \Zend_Db_Expr $entityIdsSelect)
    {
        foreach ($columns as $key => $value) {
            $columns[$key] = new \Zend_Db_Expr($value);
        }
        /** @var \Magento\Framework\DB\Select $select */
        $select = $this->destinationAdapter->getSelect();
        $select->from(['sales_shipment' => $this->destination->addDocumentPrefix('sales_shipment')], [])
            ->joinLeft(
                ['sales_order' => $this->destination->addDocumentPrefix('sales_order')],
                'sales_shipment.order_id = sales_order.entity_id',
                []
            )->joinLeft(
                ['sales_shipping_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_shipment.shipping_address_id = sales_shipping_address.entity_id',
                []
            )->joinLeft(
                ['sales_billing_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_shipment.billing_address_id = sales_billing_address.entity_id',
                []
            )->where('sales_shipment.entity_id in (?)', $entityIdsSelect);
        $select->columns($columns);
        return $select;
    }

    /**
     * @param array $columns
     * @param \Zend_Db_Expr $entityIdsSelect
     * @return \Magento\Framework\DB\Select
     */
    public function getSelectSalesCreditmemoGrid(array $columns, \Zend_Db_Expr $entityIdsSelect)
    {
        foreach ($columns as $key => $value) {
            $columns[$key] = new \Zend_Db_Expr($value);
        }
        /** @var \Magento\Framework\DB\Select $select */
        $select = $this->destinationAdapter->getSelect();
        $select->from(['sales_creditmemo' => $this->destination->addDocumentPrefix('sales_creditmemo')], [])
            ->joinLeft(
                ['sales_order' => $this->destination->addDocumentPrefix('sales_order')],
                'sales_creditmemo.order_id = sales_order.entity_id',
                []
            )->joinLeft(
                ['sales_shipping_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_creditmemo.shipping_address_id = sales_shipping_address.entity_id',
                []
            )->joinLeft(
                ['sales_billing_address' => $this->destination->addDocumentPrefix('sales_order_address')],
                'sales_creditmemo.billing_address_id = sales_billing_address.entity_id',
                []
            )->where('sales_creditmemo.entity_id in (?)', $entityIdsSelect);
        $select->columns($columns);
        return $select;
    }

    /**
     * @return array
     */
    protected function getDocumentList()
    {
        return $this->helper->getSelectData();
    }

    /**
     * @param string $sourceGridDocumentName
     * @return \Magento\Framework\DB\Select
     */
    protected function getEntityIdsSelect($sourceGridDocumentName)
    {
        /** @var \Migration\Resource\Adapter\Mysql $adapter */
        $adapter = $this->source->getAdapter();
        /** @var \Magento\Framework\DB\Select $select */
        $select = $adapter->getSelect();
        $schema = $this->config->getSource()['database']['name'];
        $select->from($this->source->addDocumentPrefix($sourceGridDocumentName), 'entity_id', $schema);
        return $select;
    }
}

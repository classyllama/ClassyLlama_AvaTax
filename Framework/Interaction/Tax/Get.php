<?php
/**
 * ClassyLlama_AvaTax
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2016 Avalara, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace ClassyLlama\AvaTax\Framework\Interaction\Tax;

use ClassyLlama\AvaTax\Api\RestTaxInterface;
use ClassyLlama\AvaTax\Framework\Interaction\TaxCalculation;
use ClassyLlama\AvaTax\Framework\Interaction\Tax;
use ClassyLlama\AvaTax\Model\Logger\AvaTaxLogger;

class Get
{
    /**
     * @var TaxCalculation
     */
    protected $taxCalculation;

    /**
     * @var Tax
     */
    protected $interactionTax;

    /**
     * @var Get\ResponseFactory
     */
    protected $getTaxResponseFactory;

    /**
     * @var AvaTaxLogger
     */
    protected $avaTaxLogger;

    /**
     * @var RestTaxInterface
     */
    protected $taxService;

    /**
     * @param TaxCalculation $taxCalculation
     * @param Tax $interactionTax
     * @param Get\ResponseFactory $getTaxResponseFactory
     * @param AvaTaxLogger $avaTaxLogger
     * @param RestTaxInterface $taxService
     */
    public function __construct(
        TaxCalculation $taxCalculation,
        Tax $interactionTax,
        Get\ResponseFactory $getTaxResponseFactory,
        AvaTaxLogger $avaTaxLogger,
        RestTaxInterface $taxService
    ) {
        $this->taxCalculation = $taxCalculation;
        $this->interactionTax = $interactionTax;
        $this->getTaxResponseFactory = $getTaxResponseFactory;
        $this->avaTaxLogger = $avaTaxLogger;
        $this->taxService = $taxService;
    }

    /**
     * Process invoice or credit memo
     *
     * @param \Magento\Sales\Api\Data\InvoiceInterface|\Magento\Sales\Api\Data\CreditmemoInterface $object
     * @return \ClassyLlama\AvaTax\Api\Data\GetTaxResponseInterface
     * @throws \ClassyLlama\AvaTax\Exception\TaxCalculationException
     */
    public function processSalesObject($object)
    {
        $storeId = $object->getStoreId();
        $taxService = $this->taxService;
        try {
            /** @var $getTaxRequest \Magento\Framework\DataObject */
            $getTaxRequest = $this->interactionTax->getTaxRequestForSalesObject($object);
        } catch (\Exception $e) {
            $message = __('Error while building the request to send to AvaTax. ');
            $this->avaTaxLogger->error(
                $message,
                [ /* context */
                    'entity_id' => $object->getEntityId(),
                    'object_class' => get_class($object),
                    'exception' => sprintf(
                        'Exception message: %s%sTrace: %s',
                        $e->getMessage(),
                        "\n",
                        $e->getTraceAsString()
                    ),
                ]
            );
            throw new \ClassyLlama\AvaTax\Exception\TaxCalculationException($message . $e->getMessage(), $e->getCode(), $e);
        }

        if (is_null($getTaxRequest)) {
            $message = '$getTaxRequest was empty so not running getTax request.';
            $this->avaTaxLogger->warning($message);
            throw new \ClassyLlama\AvaTax\Exception\TaxCalculationException($message);
        }

        try {
            /** @var \ClassyLlama\AvaTax\Framework\Interaction\Rest\Tax\Result $getTaxResult */
            $getTaxResult = $taxService->getTax(
                $getTaxRequest,
                null,
                $storeId,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                [\ClassyLlama\AvaTax\Api\RestTaxInterface::FLAG_FORCE_NEW_RATES => true]
            );

            // Since credit memo tax amounts come back from AvaTax as negative numbers, get absolute value
            $avataxTaxAmount = abs($getTaxResult->getTotalTax());
            $unbalanced = ($avataxTaxAmount != $object->getBaseTaxAmount());

            /** @var $response \ClassyLlama\AvaTax\Api\Data\GetTaxResponseInterface */
            $response = $this->getTaxResponseFactory->create();
            $response->setIsUnbalanced($unbalanced)
                ->setBaseAvataxTaxAmount($avataxTaxAmount);

            return $response;
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            $this->avaTaxLogger->error($message);
            throw new \ClassyLlama\AvaTax\Exception\TaxCalculationException($message);
        }
    }

    /**
     * Convert quote/order/invoice/creditmemo to the AvaTax object and request tax from the Get Tax API
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Tax\Api\Data\QuoteDetailsInterface $taxQuoteDetails
     * @param \Magento\Tax\Api\Data\QuoteDetailsInterface $baseTaxQuoteDetails
     * @param \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @return array
     * @throws \ClassyLlama\AvaTax\Exception\TaxCalculationException
     * @throws \Exception
     */
    public function getTaxDetailsForQuote(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Tax\Api\Data\QuoteDetailsInterface $taxQuoteDetails,
        \Magento\Tax\Api\Data\QuoteDetailsInterface $baseTaxQuoteDetails,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
    ) {
        $storeId = $quote->getStoreId();
        $taxService = $this->taxService;
        try {
            // Total quantity of an item can be determined by multiplying parent * child quantity, so it's necessary
            // to calculate total quantities on a list of all items
            $this->taxCalculation->calculateTotalQuantities($taxQuoteDetails->getItems());
            $this->taxCalculation->calculateTotalQuantities($baseTaxQuoteDetails->getItems());

            // Taxes need to be calculated on the base prices/amounts, not the current currency prices. As a result of this,
            // only the $baseTaxQuoteDetails will have taxes calculated for it. The taxes for the current currency will be
            // calculated by multiplying the base tax rates * currency conversion rate.
            /** @var $getTaxRequest \Magento\Framework\DataObject */
            $getTaxRequest = $this->interactionTax
                ->getTaxRequestForQuote($quote, $baseTaxQuoteDetails, $shippingAssignment);

            if (is_null($getTaxRequest)) {
                $message = __('$quote was empty or address was not valid so not running getTax request.');
                throw new \ClassyLlama\AvaTax\Exception\TaxCalculationException($message);
            }

            $getTaxResult = $taxService->getTax($getTaxRequest, null, $storeId);

            $store = $quote->getStore();
            $baseTaxDetails = $this->taxCalculation->calculateTaxDetails(
                $baseTaxQuoteDetails,
                $getTaxResult,
                true,
                $store
            );
            /**
             * If quote is using a currency other than the base currency, calculate tax details for both quote
             * currency and base currency. Otherwise use the same tax details object.
             */
            if ($quote->getBaseCurrencyCode() != $quote->getQuoteCurrencyCode()) {
                $taxDetails = $this->taxCalculation->calculateTaxDetails(
                    $taxQuoteDetails,
                    $getTaxResult,
                    false,
                    $store
                );
            } else {
                $taxDetails = $baseTaxDetails;
            }

            $avaTaxMessages = [];

            if($getTaxResult->getMessages() !== null) {
                $landedCostMessages = array_filter(
                    $getTaxResult->getMessages(),
                    function ($message) {
                        return \in_array($message->getRefersTo(), ['Customs', 'LandedCost']);
                    }
                );

                $avaTaxMessages = array_map(
                    function ($message) {
                        return $message->getSummary();
                    },
                    $landedCostMessages
                );
            }


            return [
                $taxDetails,
                $baseTaxDetails,
                $avaTaxMessages
            ];
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            $this->avaTaxLogger->error($message);
            throw new \ClassyLlama\AvaTax\Exception\TaxCalculationException($message);
        }
    }
}

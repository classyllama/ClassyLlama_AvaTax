<?php

namespace ClassyLlama\AvaTax\Test\UnitFramework\Interaction;

use AvaTax\LineFactory;
use AvaTax\Line as AvaTaxLine;
use ClassyLlama\AvaTax\Framework\Interaction\MetaData\MetaDataObjectFactory;
use ClassyLlama\AvaTax\Framework\Interaction\MetaData\MetaDataObject;
use ClassyLlama\AvaTax\Helper\Config;
use ClassyLlama\AvaTax\Helper\TaxClass;
use ClassyLlama\AvaTax\Model\Logger\AvaTaxLogger;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Catalog\Model\ResourceModel\Product as ResourceProduct;

class LineTest extends \PHPUnit\Framework\TestCase
{
    const LINE_NO = '1234567890';
    const LINE_ITEM_CODE = 'SKU';
    const LINE_TAX_CODE = 'PC040100';
    const LINE_EXEMPTION_NO = '9876543210';
    const LINE_DESCRIPTION = 'Product description';
    const LINE_QTY = '4.50';
    const LINE_AMOUNT = '69.99';
    const LINE_DISCOUNTED = false;
    const LINE_TAX_INCLUDED = false;
    const LINE_REF_1 = 'Reference 1';
    const LINE_REF_2 = 'Reference 2';

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var TaxClass
     */
    private $taxClassHelper;

    /**
     * @var AvaTaxLogger
     */
    private $avaTaxLogger;

    /**
     * @var MetaDataObjectFactory
     */
    private $metaDataObjectFactory;

    /**
     * @var MetaDataObject
     */
    private $metaDataObject;

    /**
     * @var LineFactory
     */
    private $lineFactory;

    /**
     * @var ResourceProduct
     */
    private $resourceProduct;

    /**
     * @var AvaTaxLine
     */
    private $avaTaxLine;

    /**
     * Setup unit test
     *
     */
    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);

        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->taxClassHelper = $this->getMockBuilder(TaxClass::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->avaTaxLogger = $this->getMockBuilder(AvaTaxLogger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->metaDataObjectFactory = $this->getMockBuilder(MetaDataObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->metaDataObject = $this->getMockBuilder(MetaDataObject::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->lineFactory = $this->getMockBuilder(LineFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->resourceProduct = $this->getMockBuilder(ResourceProduct::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->avaTaxLine = $this->objectManager->getObject('AvaTax\Line');
    }

    /**
     * Test tax code
     *
     */
    public function testGetLine()
    {
        $this->lineFactory->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->avaTaxLine));

        $this->metaDataObjectFactory->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->metaDataObject));

        $this->metaDataObject->expects($this->once())
            ->method('validateData')
            ->with($this->getData())
            ->will($this->returnValue($this->getData()));

        $this->line = $this->objectManager->getObject(
            'ClassyLlama\AvaTax\Framework\Interaction\Line',
            [
                'config' => $this->config,
                'taxClassHelper' => $this->taxClassHelper,
                'avaTaxLogger' => $this->avaTaxLogger,
                'metaDataObjectFactory' => $this->metaDataObjectFactory,
                'lineFactory' => $this->lineFactory,
                'resourceProduct' => $this->resourceProduct
            ]
        );

        $result = $this->line->getLine($this->getData());

        $this->assertEquals(self::LINE_NO, $result->getNo());
        $this->assertEquals(self::LINE_ITEM_CODE, $result->getItemCode());
        $this->assertEquals(self::LINE_TAX_CODE, $result->getTaxCode());
        $this->assertEquals(self::LINE_EXEMPTION_NO, $result->getExemptionNo());
        $this->assertEquals(self::LINE_DESCRIPTION, $result->getDescription());
        $this->assertEquals(self::LINE_QTY, $result->getQty());
        $this->assertEquals(self::LINE_AMOUNT, $result->getAmount());
        $this->assertEquals(self::LINE_DISCOUNTED, $result->getDiscounted());
        $this->assertEquals(self::LINE_TAX_INCLUDED, $result->getTaxIncluded());
        $this->assertEquals(self::LINE_REF_1, $result->getRef1());
        $this->assertEquals(self::LINE_REF_2, $result->getRef2());
    }

    /**
     * Returns the line data.
     *
     * @return array
     */
    private function getData()
    {
        return [
            'No' => self::LINE_NO,
            'ItemCode' => self::LINE_ITEM_CODE,
            'TaxCode' => self::LINE_TAX_CODE,
            'ExemptionNo' => self::LINE_EXEMPTION_NO,
            'Description' => self::LINE_DESCRIPTION,
            'Qty' => self::LINE_QTY,
            'Amount' => self::LINE_AMOUNT, // Required, but $0 value is acceptable so removing required attribute.
            'Discounted' => self::LINE_DISCOUNTED,
            'TaxIncluded' => self::LINE_TAX_INCLUDED,
            'Ref1' => self::LINE_REF_1,
            'Ref2' => self::LINE_REF_2,
        ];
    }
}
<?php


namespace Cyberpunkspike\Clean\Console\Command;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AttributeOptions extends Command
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {

        $this
            // ...
            ->addArgument(
                'attributes',
                InputArgument::IS_ARRAY,
                'Attributes to clean options (separated by space)'
            )
        ;

        $this->setName('cyberpunkspike:clean:attribute-options')
            ->setDescription('Remove Unused Attribute Options');

        parent::configure();
    }


    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory
     */
    protected $attrOptionCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    protected $eavAttributeRepository;

    /**
     * @var \Magento\Eav\Api\AttributeOptionManagementInterface
     */
    protected $attributeOptionManagement;



    /**
     * Options constructor.
     */
    public function __construct(
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attributeOptionCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Eav\Api\AttributeOptionManagementInterface $attributeOptionManagement,
        \Magento\Eav\Api\AttributeRepositoryInterface $eavAttributeRepositoryInterface
    )
    {
        $this->attrOptionCollectionFactory = $attributeOptionCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->eavAttributeRepository = $eavAttributeRepositoryInterface;

        parent::__construct();
    }

    /**
     * Get attribute by code.
     *
     * @param string $attributeCode
     * @return \Magento\Eav\Api\Data\AttributeInterface
     * @throws NoSuchEntityException
     */
    protected function getAttribute($attributeCode)
    {
        return $this->eavAttributeRepository->get(
            \Magento\Catalog\Model\Product::ENTITY,
            $attributeCode
        );
    }


    /**
     * @param \Magento\Eav\Api\Data\AttributeInterface $attribute
     * @return array
     */
    protected function getAllOptions(\Magento\Eav\Api\Data\AttributeInterface $attribute)
    {
        $collection = $this->attrOptionCollectionFactory->create()
            ->setAttributeFilter($attribute->getAttributeId())
            ->setStoreFilter($attribute->getStoreId())
            ->load();

        /** @var \Magento\Eav\Api\Data\AttributeOptionInterface $option */
        foreach($collection as $option) {
            $values[] = $option->getValue();
        }
        /** @var array $values */
        return $values;
    }

    /**
     * @param \Magento\Eav\Api\Data\AttributeInterface $attribute
     * @return array
     */
    protected function getUsedOptions(\Magento\Eav\Api\Data\AttributeInterface $attribute)
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect($attribute->getAttributeCode())
            ->addAttributeToFilter($attribute->getAttributeCode(), ['neq' => ''])
            ->load();

        /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
        foreach ($collection as $product) {
            $values[] = $product->getAttributeText($attribute->getAttributeCode());
        }

        /** @var array $values */
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {

        $attributes = $input->getArgument('attributes');
        foreach ($attributes as $attribute_code)  {
            try {
                $attribute = $this->getAttribute($attribute_code);
            } catch (NoSuchEntityException $e) {
                $output->writeln('attribute '. $attribute_code . ', not found');
                continue;
            }

            $allOptions = $this->getAllOptions($attribute);
            $usedOptions = $this->getUsedOptions($attribute);
            $unusedOptions = array_diff($allOptions, $usedOptions);

            $option_text = [];
            foreach ($unusedOptions as $option) {
                try {
                    if ($this->attributeOptionManagement->delete($attribute->getEntityTypeId(), $attribute->getAttributeCode(), $attribute->getSource()->getOptionId($option))) {
                        $option_text[] = $option;
                    }
                } catch (InputException $e) {
                } catch (NoSuchEntityException $e) {
                } catch (StateException $e) {
                }
            }
            $output_text = 'values deleted ('.count($option_text).'): '.implode(', ', $option_text);


            $output->writeln('attribute '. $attribute_code . ', '.$output_text );
        }

    }

}

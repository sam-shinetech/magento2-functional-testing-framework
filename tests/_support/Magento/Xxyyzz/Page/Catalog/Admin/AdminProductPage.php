<?php
namespace Magento\Xxyyzz\Page\Catalog\Admin;

use Magento\Xxyyzz\Page\AbstractAdminPage;

class AdminProductPage extends AbstractAdminPage
{
    /**
     * Include url of current page.
     */
    public static $URL = '/admin/catalog/product/';

    /**
     * Buttons in product page.
     */
    public static $productSaveButton            = '#save-button';

    /**
     * Product data fields.
     */
    public static $productName                  = '.admin__field[data-index=name] input';
    public static $productSku                   = '.admin__field[data-index=sku] input';
    public static $productPrice                 = '.admin__field[data-index=price] input';
    public static $productQuantity              = '.admin__field[data-index=quantity_and_stock_status_qty] input';
    public static $productStockStatus           = '.admin__field[data-index=quantity_and_stock_status] select';
    public static $productTaxClass              = '.admin__field[data-index=tax_class_id] select';
    public static $producAttributeSetMultiSelect= '.admin__field[data-index=attribute_set_id]';
    public static $producAttributeMultiSelectText
        = '.admin__field[data-index=attribute_set_id] .action-select.admin__action-multiselect>div';
    public static $producCategoriesMultiSelect  = '.admin__field[data-index=category_ids]';
    public static $producCategoriesMultiSelectText
        = '.admin__field[data-index=category_ids] .admin__action-multiselect-crumb:nth-child(%s)>span';

    public static $productContentToggle            =
        '.fieldset-wrapper[data-index=content] .fieldset-wrapper-title[data-state-collapsible=%s]';
    public static $productSearchEngineOptimToggle  =
        '.fieldset-wrapper[data-index=search-engine-optimization] .fieldset-wrapper-title[data-state-collapsible=%s]';
    /**
     * Product form loading spinner.
     */
    public static $productFormLoadingSpinner
        = '.admin__form-loading-mask[data-component="product_form.product_form"] .spinner';

    public static $productUrlKey                   = '.admin__field[data-index=url_key] input';

    public function amOnAdminNewProductPage()
    {
        $I = $this->acceptanceTester;
        $I->waitForElementVisible(self::$productName, $this->pageloadTimeout);
        $I->seeInCurrentUrl(static::$URL . 'new');
    }

    public function amOnAdminEditProductPageById($id)
    {
        $I = $this->acceptanceTester;
        $I->amOnPage(self::route('edit/id/' . $id));
        $I->waitForElementVisible(self::$productName, $this->pageloadTimeout);
    }

    public function seeProductAttributeSet($name)
    {
        $I = $this->acceptanceTester;
        $I->assertEquals($name, $I->grabTextFrom(self::$producAttributeMultiSelectText));
    }

    public function seeProductName($name)
    {
        $I = $this->acceptanceTester;
        $I->seeInField(self::$productName, $name);
    }

    public function seeProductSku($name)
    {
        $I = $this->acceptanceTester;
        $I->seeInField(self::$productSku, $name);
    }

    public function seeProductPrice($name)
    {
        $I = $this->acceptanceTester;
        $I->seeInField(self::$productPrice, $name);
    }

    public function seeProductQuantity($name)
    {
        $I = $this->acceptanceTester;
        $I->seeInField(self::$productQuantity, $name);
    }

    public function seeProductStockStatus($name)
    {
        $I = $this->acceptanceTester;
        $I->seeOptionIsSelected(self::$productStockStatus, $name);
    }

    /**
     * @param array $names
     */
    public function seeProductCategories(array $names)
    {
        $I = $this->acceptanceTester;
        $count = 2;
        foreach ($names as $name) {
            $I->assertEquals($name, $I->grabTextFrom(sprintf(self::$producCategoriesMultiSelectText, $count)));
            $count += 1;
        }
    }

    public function seeProductUrlKey($name)
    {
        $I = $this->acceptanceTester;
        try {
            $I->click(sprintf(self::$productSearchEngineOptimToggle, 'closed'));
        } catch (\Exception $e) {
        }
        $I->seeInField(self::$productUrlKey, $name);
    }

    // Fill new product
    public function selectProductAttributeSet($name)
    {
        $I = $this->acceptanceTester;
        $I->searchAndMultiSelectOption(self::$producAttributeSetMultiSelect, [$name]);
    }

    public function fillFieldProductName($name)
    {
        $I = $this->acceptanceTester;
        $I->fillField(self::$productName, $name);
    }

    public function fillFieldProductSku($name)
    {
        $I = $this->acceptanceTester;
        $I->fillField(self::$productSku, $name);
    }

    public function fillFieldProductPrice($name)
    {
        $I = $this->acceptanceTester;
        $I->fillField(self::$productPrice, $name);
    }

    public function fillFieldProductQuantity($name)
    {
        $I = $this->acceptanceTester;
        $I->fillField(self::$productQuantity, $name);
    }

    public function selectProductStockStatus($name)
    {
        $I = $this->acceptanceTester;
        $I->selectOption(self::$productStockStatus, $name);
    }

    public function fillFieldProductUrlKey($name)
    {
        $I = $this->acceptanceTester;
        try {
            $I->click(sprintf(self::$productSearchEngineOptimToggle, 'closed'));
        } catch (\Exception $e) {
        }
        $I->fillField(self::$productUrlKey, $name);
    }

    /**
     * @param array $names
     */
    public function selectProductCategories(array $names)
    {
        $I = $this->acceptanceTester;
        $I->searchAndMultiSelectOption(self::$producCategoriesMultiSelect, $names, true);
    }

    public function saveProduct()
    {
        $I = $this->acceptanceTester;
        $I->performOn(self::$productSaveButton, ['click' => self::$productSaveButton]);
        $I->waitForElementNotVisible(self::$popupLoadingSpinner);
        $I->waitForElementNotVisible(self::$productFormLoadingSpinner);
        $I->waitForElementVisible(self::$successMessage);
    }
}
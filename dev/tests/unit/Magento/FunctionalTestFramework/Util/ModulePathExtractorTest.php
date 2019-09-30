<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace tests\unit\Magento\FunctionalTestFramework\Test\Util;

use Magento\FunctionalTestingFramework\Util\ModulePathExtractor;
use Magento\FunctionalTestingFramework\Util\MagentoTestCase;
use tests\unit\Util\MockModuleResolverBuilder;

class ModulePathExtractorTest extends MagentoTestCase
{
    /**
     * Mock test module paths
     *
     * @var array
     */
    private $mockTestModulePaths = [
        '/base/path/app/code/Magento/ModuleA/Test/Mftf' => ['Magento_ModuleA'],
        '/base/path/app/code/VendorB/ModuleB/Test/Mftf' => ['VendorB_ModuleB'],
        '/base/path/dev/tests/acceptance/tests/functional/Magento/ModuleCTest' => ['Magento_ModuleC'],
        '/base/path/dev/tests/acceptance/tests/functional/VendorD/ModuleDTest' => ['VendorD_ModuleD'],
        '/base/path/dev/tests/acceptance/tests/functional/FunctionalTest/SomeModuleE' => ['SomeModuleE'],
        '/base/path/vendor/magento/module-modulef/Test/Mftf' => ['Magento_ModuleF'],
        '/base/path/vendor/vendorg/module-moduleg-test' => ['VendorG_ModuleG'],
    ];

    /**
     * Validate module for app/code path
     *
     * @throws \Exception
     */
    public function testGetModuleAppCode()
    {
        $mockPath = '/base/path/app/code/Magento/ModuleA/Test/Mftf/Test/SomeTest.xml';

        $resolverMock = new MockModuleResolverBuilder();
        $resolverMock->setup($this->mockTestModulePaths);
        $extractor = new ModulePathExtractor();
        $this->assertEquals('ModuleA', $extractor->extractModuleName($mockPath));
    }

    /**
     * Validate vendor for app/code path
     *
     * @throws \Exception
     */
    public function testGetVendorAppCode()
    {
        $mockPath = '/base/path/app/code/VendorB/ModuleB/Test/Mftf/Test/SomeTest.xml';

        $resolverMock = new MockModuleResolverBuilder();
        $resolverMock->setup($this->mockTestModulePaths);
        $extractor = new ModulePathExtractor();
        $this->assertEquals('VendorB', $extractor->getExtensionPath($mockPath));
    }

    /**
     * Validate module for dev/tests path
     *
     * @throws \Exception
     */
    public function testGetModuleDevTests()
    {
        $mockPath = '/base/path/dev/tests/acceptance/tests/functional/Magento/ModuleCTest/Test/SomeTest.xml';

        $resolverMock = new MockModuleResolverBuilder();
        $resolverMock->setup($this->mockTestModulePaths);
        $extractor = new ModulePathExtractor();
        $this->assertEquals('ModuleC', $extractor->extractModuleName($mockPath));
    }

    /**
     * Validate vendor for dev/tests path
     *
     * @throws \Exception
     */
    public function testGetVendorDevTests()
    {
        $mockPath = '/base/path/dev/tests/acceptance/tests/functional/VendorD/ModuleDTest/Test/SomeTest.xml';

        $resolverMock = new MockModuleResolverBuilder();
        $resolverMock->setup($this->mockTestModulePaths);
        $extractor = new ModulePathExtractor();
        $this->assertEquals('VendorD', $extractor->getExtensionPath($mockPath));
    }

    /**
     * Validate module with no _
     *
     * @throws \Exception
     */
    public function testGetModule()
    {
        $mockPath = '/base/path/dev/tests/acceptance/tests/functional/FunctionalTest/SomeModuleE/Test/SomeTest.xml';

        $resolverMock = new MockModuleResolverBuilder();
        $resolverMock->setup($this->mockTestModulePaths);
        $extractor = new ModulePathExtractor();
        $this->assertEquals('NO MODULE DETECTED', $extractor->extractModuleName($mockPath));
    }

    /**
     * Validate module for vendor/tests path
     *
     * @throws \Exception
     */
    public function testGetModuleVendorDir()
    {
        $mockPath = '/base/path/vendor/magento/module-modulef/Test/Mftf/Test/SomeTest.xml';

        $resolverMock = new MockModuleResolverBuilder();
        $resolverMock->setup($this->mockTestModulePaths);
        $extractor = new ModulePathExtractor();
        $this->assertEquals('ModuleF', $extractor->extractModuleName($mockPath));
    }

    /**
     * Validate vendor for vendor path
     *
     * @throws \Exception
     */
    public function testGetVendorVendorDir()
    {
        $mockPath = '/base/path/vendor/vendorg/module-moduleg-test/Test/SomeTest.xml';

        $resolverMock = new MockModuleResolverBuilder();
        $resolverMock->setup($this->mockTestModulePaths);
        $extractor = new ModulePathExtractor();
        $this->assertEquals('VendorG', $extractor->getExtensionPath($mockPath));
    }
}

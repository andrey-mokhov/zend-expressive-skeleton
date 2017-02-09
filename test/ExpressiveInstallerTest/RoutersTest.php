<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-skeleton for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-skeleton/blob/master/LICENSE.md New BSD License
 */

namespace ExpressiveInstallerTest;

use App\Action\HomePageAction;
use App\Action\PingAction;
use ExpressiveInstaller\OptionalPackages;
use Zend\Expressive\Router;

class RoutersTest extends OptionalPackagesTestCase
{
    use ProjectSandboxTrait;

    private $expectedRoutes = [
        [
            'name'            => 'home',
            'path'            => '/',
            'middleware'      => HomePageAction::class,
            'allowed_methods' => ['GET'],
        ],
        [
            'name'            => 'api.ping',
            'path'            => '/api/ping',
            'middleware'      => PingAction::class,
            'allowed_methods' => ['GET'],
        ],
    ];

    /**
     * @param OptionalPackages
     */
    protected $installer;

    protected function setUp()
    {
        parent::setUp();
        $this->projectRoot = $this->copyProjectFilesToTempFilesystem();
        $this->installer   = $this->createOptionalPackages($this->projectRoot);
    }

    protected function tearDown()
    {
        parent::tearDown();
        chdir($this->packageRoot);
        $this->recursiveDelete($this->projectRoot);
        $this->tearDownAlternateAutoloader();
    }

    /**
     * @dataProvider routerProvider
     * @runInSeparateProcess
     */
    public function testRouter(
        $installType,
        $containerOption,
        $routerOption,
        $copyFilesKey,
        $expectedResponseStatusCode,
        $expectedRoutes,
        $expectedRouter
    ) {
        $this->prepareSandboxForInstallType($installType, $this->installer);

        // Install container
        $config = $this->getInstallerConfig($this->installer);
        $containerResult = $this->installer->processAnswer(
            $config['questions']['container'],
            $containerOption
        );
        $this->assertTrue($containerResult);

        // Install router
        $routerResult = $this->installer->processAnswer(
            $config['questions']['router'],
            $routerOption
        );
        $this->assertTrue($routerResult);

        // Test container
        $container = $this->getContainer();
        $this->assertTrue($container->has(Router\RouterInterface::class));

        // Test config
        $config = $container->get('config');
        $this->assertEquals(
            $expectedRouter,
            $config['dependencies']['invokables'][Router\RouterInterface::class]
        );

        // Test home page
        $setupRoutes = (strpos($copyFilesKey, 'minimal') !== 0);
        $response = $this->getAppResponse('/', $setupRoutes);
        $status = $response->getStatusCode();

        // Using assertTrue here because when assertEquals failed when using FastRoute,
        // it reported as a serialization error instead. See
        // https://github.com/sebastianbergmann/phpunit/issues/1515
        // for details. (Issue was never resolved)
        $this->assertTrue(
            $expectedResponseStatusCode === $status,
            sprintf("Expected response status '%s', received '%s'", $expectedResponseStatusCode, $status)
        );
    }

    public function routerProvider()
    {
        // @codingStandardsIgnoreStart
        // $containerOption, $routerOption, $copyFilesKey, $expectedResponseStatusCode, $expectedRoutes, $expectedRouter
        return [
            'aura-minimal'        => [OptionalPackages::INSTALL_MINIMAL, 3, 1, 'minimal-files', 404, [], Router\AuraRouter::class],
            'aura-flat'           => [OptionalPackages::INSTALL_FLAT, 3, 1, 'copy-files', 200, $this->expectedRoutes, Router\AuraRouter::class],
            'aura-modular'        => [OptionalPackages::INSTALL_MODULAR, 3, 1, 'copy-files', 200, $this->expectedRoutes, Router\AuraRouter::class],
            'fastroute-minimal'   => [OptionalPackages::INSTALL_MINIMAL, 3, 2, 'minimal-files', 404, [], Router\FastRouteRouter::class],
            'fastroute-flat'      => [OptionalPackages::INSTALL_FLAT, 3, 2, 'copy-files', 200, $this->expectedRoutes, Router\FastRouteRouter::class],
            'fastroute-modular'   => [OptionalPackages::INSTALL_MODULAR, 3, 2, 'copy-files', 200, $this->expectedRoutes, Router\FastRouteRouter::class],
            'zend-router-minimal' => [OptionalPackages::INSTALL_MINIMAL, 3, 3, 'minimal-files', 404, [], Router\ZendRouter::class],
            'zend-router-flat'    => [OptionalPackages::INSTALL_FLAT, 3, 3, 'copy-files', 200, $this->expectedRoutes, Router\ZendRouter::class],
            'zend-router-modular' => [OptionalPackages::INSTALL_MODULAR, 3, 3, 'copy-files', 200, $this->expectedRoutes, Router\ZendRouter::class],
        ];
        // @codingStandardsIgnoreEnd
    }
}

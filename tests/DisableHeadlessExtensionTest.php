<?php

namespace frontkom\BehatAutoDdevNonHeadless\Tests;

use frontkom\BehatAutoDdevNonHeadless\DisableHeadlessExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DisableHeadlessExtensionTest extends TestCase {

  protected function setUp(): void
  {
    putenv('IS_DDEV_PROJECT=true');
    putenv('BEHAT_HEADLESS');
  }

  protected function tearDown(): void
  {
    putenv('IS_DDEV_PROJECT');
    putenv('BEHAT_HEADLESS');
  }

  public function capabilityShapes(): array
  {
    return [
      'legacy chrome.switches' => [
        ['chrome' => ['switches' => ['--headless', '--no-sandbox']]],
        ['chrome' => ['switches' => ['--no-sandbox']]],
      ],
      'w3c under extra_capabilities' => [
        ['extra_capabilities' => ['goog:chromeOptions' => ['args' => ['--headless=new', '--no-sandbox']]]],
        ['extra_capabilities' => ['goog:chromeOptions' => ['args' => ['--no-sandbox']]]],
      ],
      'w3c at the top level' => [
        ['goog:chromeOptions' => ['args' => ['--headless', '--no-sandbox']]],
        ['goog:chromeOptions' => ['args' => ['--no-sandbox']]],
      ],
      'both shapes at once' => [
        [
          'chrome' => ['switches' => ['--headless']],
          'goog:chromeOptions' => ['args' => ['--headless=new', '--no-sandbox']],
        ],
        [
          'chrome' => ['switches' => []],
          'goog:chromeOptions' => ['args' => ['--no-sandbox']],
        ],
      ],
      'nothing to remove' => [
        ['chrome' => ['switches' => ['--no-sandbox']]],
        ['chrome' => ['switches' => ['--no-sandbox']]],
      ],
    ];
  }

  /**
   * @dataProvider capabilityShapes
   */
  public function testHeadlessIsRemoved(array $capabilities, array $expected): void
  {
    $container = $this->containerWithCapabilities($capabilities);
    (new DisableHeadlessExtension())->process($container);
    $this->assertSame($expected, $this->capabilities($container));
  }

  public function testBehatHeadlessKeepsTheBrowserHeadless(): void
  {
    putenv('BEHAT_HEADLESS=1');
    $capabilities = ['chrome' => ['switches' => ['--headless', '--no-sandbox']]];
    $container = $this->containerWithCapabilities($capabilities);
    (new DisableHeadlessExtension())->process($container);
    $this->assertSame($capabilities, $this->capabilities($container));
  }

  public function testNothingHappensOutsideDdev(): void
  {
    putenv('IS_DDEV_PROJECT=false');
    $capabilities = ['chrome' => ['switches' => ['--headless', '--no-sandbox']]];
    $container = $this->containerWithCapabilities($capabilities);
    (new DisableHeadlessExtension())->process($container);
    $this->assertSame($capabilities, $this->capabilities($container));
  }

  public function testOtherDriversAreLeftAlone(): void
  {
    $capabilities = ['chrome' => ['switches' => ['--headless']]];
    $container = $this->containerWithCapabilities($capabilities, 'Behat\Mink\Driver\BrowserKitDriver');
    (new DisableHeadlessExtension())->process($container);
    $this->assertSame($capabilities, $this->capabilities($container));
  }

  protected function containerWithCapabilities(array $capabilities, string $driverClass = 'Behat\Mink\Driver\Selenium2Driver'): ContainerBuilder
  {
    $driver = new Definition($driverClass, ['chrome', $capabilities, 'http://selenium-chrome:4444/wd/hub']);
    $session = new Definition('Behat\Mink\Session', [$driver]);
    $mink = new Definition('Behat\Mink\Mink');
    $mink->addMethodCall('registerSession', ['selenium2', $session]);
    $container = new ContainerBuilder();
    $container->setDefinition('mink', $mink);
    return $container;
  }

  protected function capabilities(ContainerBuilder $container): array
  {
    $calls = $container->getDefinition('mink')->getMethodCalls();
    return $calls[0][1][1]->getArgument(0)->getArgument(1);
  }

}

<?php

namespace frontkom\BehatAutoDdevNonHeadless;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DisableHeadlessExtension implements Extension {

  /**
   * Capability paths that can carry the --headless switch.
   *
   * Mink accepts both the legacy chrome.switches shape and the W3C
   * goog:chromeOptions one, the latter with or without extra_capabilities.
   */
  private const SELENIUM2_DRIVER = 'Behat\\Mink\\Driver\\Selenium2Driver';

  private const HEADLESS_PATHS = [
    ['chrome', 'switches'],
    ['extra_capabilities', 'goog:chromeOptions', 'args'],
    ['goog:chromeOptions', 'args'],
  ];

  public function load(ContainerBuilder $container, array $config) {}

  public function process(ContainerBuilder $container)
  {
    if (getenv('IS_DDEV_PROJECT') !== 'true') {
      return;
    }
    // Lets you reproduce the CI setup locally, and keeps the browser out of the
    // way on long runs.
    if (in_array(strtolower((string) getenv('BEHAT_HEADLESS')), ['1', 'true', 'yes'], TRUE)) {
      return;
    }
    $def = $container->getDefinition('mink');
    $calls = $def->getMethodCalls();
    $changed = FALSE;
    foreach ($calls as $delta => $call) {
      if ($call[0] !== 'registerSession') {
        continue;
      }
      if (empty($call[1][1]) || !$call[1][1] instanceof Definition) {
        continue;
      }
      $session_definition = $call[1][1];
      $arguments = $session_definition->getArguments();
      if (empty($arguments[0]) || !$arguments[0] instanceof Definition) {
        continue;
      }
      $driver = $arguments[0];
      // Any session name is fine, but only Selenium2 takes these capabilities.
      if (!is_a((string) $driver->getClass(), self::SELENIUM2_DRIVER, TRUE)) {
        continue;
      }
      $driver_args = $driver->getArguments();
      if (empty($driver_args[1]) || !is_array($driver_args[1])) {
        continue;
      }
      $capabilities = $this->removeHeadless($driver_args[1], $found);
      if (!$found) {
        continue;
      }
      $driver_args[1] = $capabilities;
      $driver->setArguments($driver_args);
      $arguments[0] = $driver;
      $session_definition->setArguments($arguments);
      $calls[$delta][1][1] = $session_definition;
      $changed = TRUE;
    }
    if ($changed) {
      $def->setMethodCalls($calls);
    }
  }

  /**
   * Removes every --headless switch from the known capability shapes.
   *
   * @param array $capabilities
   *   The driver capabilities.
   * @param bool|null $found
   *   Set to TRUE when a switch was removed.
   *
   * @return array
   *   The capabilities without any --headless switch.
   */
  protected function removeHeadless(array $capabilities, &$found = NULL)
  {
    $found = FALSE;
    foreach (self::HEADLESS_PATHS as $path) {
      $switches = $this->getByPath($capabilities, $path);
      if (!is_array($switches)) {
        continue;
      }
      $kept = array_values(array_filter($switches, function ($switch) {
        return strpos((string) $switch, '--headless') !== 0;
      }));
      if ($kept === array_values($switches)) {
        continue;
      }
      $capabilities = $this->setByPath($capabilities, $path, $kept);
      $found = TRUE;
    }
    return $capabilities;
  }

  /**
   * Reads a nested array key.
   *
   * @param array $array
   *   The array to read from.
   * @param string[] $path
   *   The keys to walk.
   *
   * @return mixed
   *   The value at the path, or NULL when the path does not exist.
   */
  protected function getByPath(array $array, array $path)
  {
    $value = $array;
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        return NULL;
      }
      $value = $value[$key];
    }
    return $value;
  }

  /**
   * Writes a nested array key.
   *
   * @param array $array
   *   The array to write to.
   * @param string[] $path
   *   The keys to walk.
   * @param mixed $value
   *   The value to set.
   *
   * @return array
   *   The array with the value set.
   */
  protected function setByPath(array $array, array $path, $value)
  {
    $key = array_shift($path);
    if (!$path) {
      $array[$key] = $value;
      return $array;
    }
    $child = isset($array[$key]) && is_array($array[$key]) ? $array[$key] : [];
    $array[$key] = $this->setByPath($child, $path, $value);
    return $array;
  }

  public function getConfigKey()
  {
    return 'disable_headless';
  }

  public function initialize(ExtensionManager $extensionManager) {}
  public function configure(ArrayNodeDefinition $builder) {}

}

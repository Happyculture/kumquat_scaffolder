<?php

namespace Drupal\Console\KumquatScaffolder;

use Drupal\Core\Serialization\Yaml;

/**
 * Helper trait to manipulate configuration files.
 */
trait ConfigManipulationTrait {

  /**
   * Extracts a Yaml config file content.
   *
   * @param string $filename
   *   The config file name.
   *
   * @return array
   *   The configuration data.
   */
  protected function readConfig($filename) {
    return Yaml::decode(file_get_contents($filename));
  }

  /**
   * Encodes an array of data and write it in a Yaml file.
   *
   * @param string $filename
   *   The config file name.
   * @param array $data
   *   The configuration data.
   */
  protected function writeConfig($filename, array $data) {
    $current_lines = count(file($filename));
    file_put_contents($filename, Yaml::encode($data));

    if (method_exists($this, 'trackGeneratedFile')) {
      $this->trackGeneratedFile($filename, $current_lines);
    }
  }

}

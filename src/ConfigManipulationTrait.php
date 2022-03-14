<?php

namespace Drupal\Console\KumquatScaffolder;

use Drupal\Core\Serialization\Yaml;

trait ConfigManipulationTrait {

  /**
   * Extracts a Yaml config file content.
   *
   * @param $filename
   * @return array
   */
  protected function readConfig($filename) {
    return Yaml::decode(file_get_contents($filename));
  }

  /**
   * Encodes an array of data and write it in a Yaml file.
   *
   * @param $filename
   * @param $data
   */
  protected function writeConfig($filename, $data) {
    $current_lines = count(file($filename));
    file_put_contents($filename, Yaml::encode($data));

    if (method_exists($this, 'trackGeneratedFile')) {
      $this->trackGeneratedFile($filename, $current_lines);
    }
  }

}

<?php

namespace Drupal\Console\KumquatScaffolder;

use Symfony\Component\Filesystem\Filesystem;

trait FileManipulationTrait {

  /**
   * @var \Symfony\Component\Filesystem\Filesystem;
   */
  protected $fs;

  /**
   * Gets an helper to manipulate files.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   */
  public function getFs() {
    if (NULL === $this->fs) {
      $this->fs = new Filesystem();
    }
    return $this->fs;
  }

}
